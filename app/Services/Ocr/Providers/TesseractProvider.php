<?php

namespace App\Services\Ocr\Providers;

use App\Models\OcrSettings;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\OcrResult;
use App\Services\Ocr\PdfPreprocessor;
use App\Services\Ocr\ReceiptParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;

/**
 * Local OCR using the Tesseract CLI binary, post-processed by ReceiptParser.
 *
 * Requires:
 *   - The `tesseract` binary on PATH (Linux: tesseract-ocr package)
 *   - Language packs matching the configured codes (e.g. tesseract-ocr-eng, tesseract-ocr-msa)
 *
 * The provider takes a storage-relative path on the 'public' disk
 * (matching how BillController writes receipts today). When/if the S3 storage
 * plan ships, we'll switch to reading bytes from the 's3' disk and writing
 * them to a temp file before invoking Tesseract.
 */
class TesseractProvider implements OcrProviderInterface
{
    public function __construct(
        private ReceiptParser $parser,
        private PdfPreprocessor $pdfPreprocessor,
    ) {}

    public function name(): string
    {
        return 'tesseract';
    }

    public function extract(string $imagePath): OcrResult
    {
        $absolutePath = $this->resolveAbsolutePath($imagePath);

        if (! $absolutePath) {
            return OcrResult::failed(
                provider: $this->name(),
                error: "Receipt file not found at $imagePath. Cannot run Tesseract.",
            );
        }

        // PDFs take a different code path: try text extraction first
        // (faster + more accurate for digital PDFs), fall back to rendering
        // page 1 to PNG and OCRing it.
        if ($this->isPdf($absolutePath)) {
            return $this->extractFromPdf($absolutePath);
        }

        return $this->extractFromImage($absolutePath);
    }

    private function extractFromImage(string $absolutePath, ?callable $cleanup = null): OcrResult
    {
        $settings = OcrSettings::current();
        $languages = $settings->tesseract_languages ?: 'eng+msa';

        try {
            $tesseract = (new TesseractOCR($absolutePath))
                ->lang(...explode('+', $languages));

            $rawText = $tesseract->run();
        } catch (TesseractOcrException $e) {
            Log::error('[OCR/Tesseract] Engine error', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);
            if ($cleanup) $cleanup();
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Tesseract OCR engine failed. Check that the tesseract binary and the requested language packs are installed on the server. Error: '.$e->getMessage(),
            );
        } catch (\Throwable $e) {
            Log::error('[OCR/Tesseract] Unexpected error', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);
            if ($cleanup) $cleanup();
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Unexpected error while running Tesseract: '.$e->getMessage(),
            );
        }

        if ($cleanup) $cleanup();

        if (trim($rawText) === '') {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Tesseract produced no readable text. The image may be too blurry, dark, or low-contrast.',
                rawText: $rawText,
            );
        }

        $fields = $this->parser->parse($rawText);
        return OcrResult::success($this->name(), $fields);
    }

    private function extractFromPdf(string $absolutePath): OcrResult
    {
        $pre = $this->pdfPreprocessor->preprocess($absolutePath);

        // Direct text extraction worked → parse and return without OCR.
        if ($pre['mode'] === 'text' && ! empty($pre['text'])) {
            $fields = $this->parser->parse($pre['text']);
            return OcrResult::success($this->name(), $fields);
        }

        // PDF→PNG fallback failed → return preprocessing error.
        if ($pre['error']) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Could not read PDF. '.$pre['error'],
            );
        }

        if (! $pre['image_path']) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'PDF preprocessing returned no usable output.',
            );
        }

        return $this->extractFromImage($pre['image_path'], $pre['cleanup']);
    }

    private function isPdf(string $absolutePath): bool
    {
        // Cheap MIME sniff via magic bytes; works for any extension/casing.
        $handle = fopen($absolutePath, 'rb');
        if (! $handle) return false;
        $header = fread($handle, 4);
        fclose($handle);
        return $header === '%PDF';
    }

    /**
     * Resolve a storage-relative path to an absolute filesystem path.
     * Falls back to checking sample assets in public/.
     */
    private function resolveAbsolutePath(string $imagePath): ?string
    {
        // 1. Storage 'public' disk (where BillController::uploadReceipt writes today)
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->path($imagePath);
        }

        // 2. Sample assets shipped under public/ (used by the test button)
        $publicSample = public_path($imagePath);
        if (file_exists($publicSample)) {
            return $publicSample;
        }

        // 3. Already an absolute path
        if (str_starts_with($imagePath, '/') && file_exists($imagePath)) {
            return $imagePath;
        }

        return null;
    }
}
