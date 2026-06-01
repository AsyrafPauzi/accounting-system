<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Cleans up receipt images BEFORE handing them to Tesseract.
 *
 * Tesseract's accuracy on phone photos is dramatically improved by:
 *  - Rotating to text-up orientation (EXIF or content-driven)
 *  - Deskewing (so baselines are horizontal)
 *  - Resampling to 300 DPI (Tesseract's sweet spot)
 *  - Binarizing to black/white (removes paper colour, shadows, ink fading)
 *  - Trimming dark borders
 *
 * If ImageMagick isn't installed, the preprocessor logs a warning and returns
 * the input path verbatim — the rest of the OCR pipeline continues to work in
 * degraded mode rather than crashing.
 */
class ImagePreprocessor
{
    /** Soft cap so we don't blow up disk on huge uploads. */
    private const MAX_INPUT_BYTES = 30 * 1024 * 1024;

    private const PROCESS_TIMEOUT_SECONDS = 30;

    public function __construct(
        private ?string $magickBinary = null,
    ) {
        $this->magickBinary ??= $this->resolveBinary();
    }

    /**
     * Returns the absolute path to a cleaned-up version of the image, or the
     * input path verbatim if preprocessing is not available / not desired.
     *
     * Output files live under storage/framework/cache/ocr-preprocess/ and are
     * cleaned up by the caller (or by Laravel cache GC eventually).
     */
    public function process(string $absolutePath): string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            Log::warning('[OCR] ImagePreprocessor: input not readable, returning verbatim', ['path' => $absolutePath]);
            return $absolutePath;
        }

        if (filesize($absolutePath) > self::MAX_INPUT_BYTES) {
            Log::info('[OCR] ImagePreprocessor: input over size cap, skipping preprocess', ['path' => $absolutePath, 'bytes' => filesize($absolutePath)]);
            return $absolutePath;
        }

        if (! $this->magickBinary || ! is_executable($this->magickBinary)) {
            Log::info('[OCR] ImagePreprocessor: magick binary not available, skipping preprocess', ['binary' => $this->magickBinary]);
            return $absolutePath;
        }

        $outputDir = $this->ensureOutputDir();
        $output = $outputDir . DIRECTORY_SEPARATOR . 'ocr-' . bin2hex(random_bytes(6)) . '.png';

        // Tuning notes:
        //   -auto-orient        respects EXIF rotation flags from phone photos
        //   -deskew 40%         straightens crooked scans / hand-held captures
        //   -density 300        ensures Tesseract's preferred DPI for resampling
        //   -resize 2400x>      caps width so we don't OCR a 12MP photo at full res
        //   -colorspace Gray    drops colour info before threshold
        //   -threshold 50%      Otsu-ish binarization — pure black/white
        //   -trim +repage       crops dark borders that confuse PSM
        //   -strip              removes EXIF/colour profiles to keep file small
        $args = [
            $this->magickBinary,
            $absolutePath,
            '-auto-orient',
            '-deskew', '40%',
            '-density', '300',
            '-resize', '2400x>',
            '-colorspace', 'Gray',
            '-threshold', '50%',
            '-trim', '+repage',
            '-strip',
            $output,
        ];

        try {
            $process = new Process($args);
            $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (! is_file($output) || filesize($output) === 0) {
                Log::warning('[OCR] ImagePreprocessor: produced empty output, falling back to original', ['path' => $absolutePath]);
                @unlink($output);
                return $absolutePath;
            }

            return $output;
        } catch (\Throwable $e) {
            Log::warning('[OCR] ImagePreprocessor: failed, falling back to original', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);
            @unlink($output);
            return $absolutePath;
        }
    }

    private function ensureOutputDir(): string
    {
        $dir = function_exists('storage_path')
            ? storage_path('framework/cache/ocr-preprocess')
            : sys_get_temp_dir() . '/ocr-preprocess';

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function resolveBinary(): ?string
    {
        // Common locations + PATH lookup. We do NOT use `which` (subprocess) at
        // construction time because callers may instantiate this many times.
        $candidates = [
            '/opt/homebrew/bin/magick',
            '/usr/local/bin/magick',
            '/usr/bin/magick',
        ];

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) return $candidate;
        }

        // Fall back to PATH search.
        $path = trim((string) @shell_exec('command -v magick 2>/dev/null'));
        return $path !== '' ? $path : null;
    }
}
