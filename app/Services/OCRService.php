<?php

namespace App\Services;

use App\Models\Bill;
use App\Services\Ocr\OcrProviderResolver;
use App\Services\Ocr\OcrResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Public-facing OCR entry point. The shape of `process()`'s return value is
 * the historical contract relied on by BillController and BillService —
 * do not break it without coordinated callsite updates.
 *
 * The actual extraction is delegated to a provider resolved at runtime
 * from OcrSettings::current()->provider.
 */
class OCRService
{
    public function __construct(
        private OcrProviderResolver $resolver,
    ) {}

    /**
     * @param string $filePath Storage-relative path on the configured upload disk
     *                         (e.g. 'receipts/abc.jpg' on the 'public' disk).
     * @return array{status: string, data: ?array, error?: string, provider?: string}
     */
    public function process(string $filePath): array
    {
        try {
            $provider = $this->resolver->resolve();
            $result = $provider->extract($filePath);
            return $result->toLegacyArray();
        } catch (Throwable $e) {
            Log::error('[OCR] Unexpected exception during extraction', [
                'path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return OcrResult::failed(
                provider: 'unknown',
                error: 'OCR provider threw an unexpected error. Receipt was saved; please fill fields manually.',
            )->toLegacyArray();
        }
    }

    /**
     * Persist OCR output back onto a Bill row. Preserved from the original API
     * for any callers still using it directly (today there are none but keeping
     * the method signature stable is cheap insurance).
     */
    public function updateBillWithOCR(Bill $bill, array $ocrResult): void
    {
        if (($ocrResult['status'] ?? null) === OcrResult::STATUS_SUCCESS) {
            $bill->update([
                'ocr_status' => 'completed',
                'ocr_data' => $ocrResult['data'] ?? null,
            ]);
        } else {
            $bill->update(['ocr_status' => 'failed']);
        }
    }
}
