<?php

namespace App\Services\Ocr\Providers;

use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\OcrResult;

/**
 * No-op provider used when OCR is administratively disabled.
 * Always returns a `failed` result without touching the file.
 */
class NullProvider implements OcrProviderInterface
{
    public function extract(string $imagePath): OcrResult
    {
        return OcrResult::failed(
            provider: $this->name(),
            error: 'OCR is currently disabled. The receipt was saved but no fields were extracted.',
        );
    }

    public function name(): string
    {
        return 'null';
    }
}
