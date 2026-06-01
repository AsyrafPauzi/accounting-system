<?php

namespace App\Services\Ocr;

interface OcrProviderInterface
{
    /**
     * Extract structured data from a receipt image.
     *
     * Implementations MUST NOT throw on extraction failure — they should
     * return an OcrResult with status=failed and a populated `error` field.
     * They MAY throw for genuinely unrecoverable problems (missing binary,
     * invalid configuration) so the caller can surface a 500 to admins.
     *
     * @param string $imagePath Storage-relative path to the receipt image
     *                          (e.g. 'receipts/abc123.jpg' on the active disk)
     */
    public function extract(string $imagePath): OcrResult;

    /**
     * Short identifier for logging and admin UI: 'tesseract' | 'gemini' | 'null'.
     */
    public function name(): string;
}
