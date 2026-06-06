<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Removes EXIF / IPTC / XMP metadata from uploaded image files in place.
 *
 * Why we need this:
 *   Phone-camera photos of receipts (the dominant upload format) carry GPS
 *   coordinates, device serial, owner name, and capture timestamp in their
 *   EXIF block. Storing those alongside the receipt amounts to leaking
 *   the user's home/office location and device fingerprint to anyone who
 *   later gets access to the receipt — including support staff, the
 *   tenant's other users, and downstream OCR tooling. PDPA classifies
 *   precise location as personal data, so stripping it is the cheapest
 *   compliance win available.
 *
 * Implementation choices:
 *   - ImageMagick (`magick -strip`) is already a runtime dep for Tesseract
 *     OCR, so no new install pressure.
 *   - In-place strip: the file is rewritten before Laravel hands it off
 *     to the storage backend, so the cleaned bytes are what land on S3.
 *   - Best-effort: if `magick` isn't available, or fails on a particular
 *     file, we log and let the original upload through. Failing the
 *     upload outright would be a worse user experience than a tiny
 *     metadata leak that the surrounding controls already make hard
 *     to exploit.
 *   - PDFs are out of scope for this stripper — they have their own
 *     metadata model (Author, Producer, …). Use `qpdf --linearize` if
 *     we ever need that. Most receipt PDFs come from merchant systems
 *     and don't carry user PII anyway.
 */
class ImageMetadataStripper
{
    /**
     * Mime types we actually try to strip. Anything else short-circuits
     * to a no-op so e.g. PDFs never get fed to magick.
     */
    private const STRIPPABLE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/tiff',
    ];

    public function __construct(
        private readonly string $magickBinary = 'magick',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function strip(string $absolutePath, ?string $mimeType = null): bool
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }

        if ($mimeType !== null && ! in_array(strtolower($mimeType), self::STRIPPABLE_MIME_TYPES, true)) {
            return false;
        }

        try {
            $process = new Process([
                $this->magickBinary,
                $absolutePath,
                '-strip',
                $absolutePath,
            ]);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('ImageMetadataStripper: magick exited non-zero', [
                    'path' => $absolutePath,
                    'exit' => $process->getExitCode(),
                    'stderr' => $process->getErrorOutput(),
                ]);
                return false;
            }

            return true;
        } catch (ProcessTimedOutException $e) {
            Log::warning('ImageMetadataStripper: magick timed out', [
                'path' => $absolutePath,
                'timeout' => $this->timeoutSeconds,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('ImageMetadataStripper: failed to invoke magick', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
