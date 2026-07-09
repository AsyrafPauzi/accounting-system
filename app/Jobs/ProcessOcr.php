<?php

namespace App\Jobs;

use App\Models\Bill;
use App\Services\OCRService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $path,
        public ?int $billId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OCRService $ocrService): void
    {
        Log::info('[OCR/Job] Starting background text extraction', [
            'path' => $this->path,
            'bill_id' => $this->billId,
        ]);

        $ocrResult = $ocrService->process($this->path);

        if ($this->billId) {
            $bill = Bill::find($this->billId);
            if ($bill) {
                $status = ($ocrResult['status'] ?? null) === 'success' ? 'completed' : 'failed';
                $bill->update([
                    'ocr_status' => $status,
                    'ocr_data' => $ocrResult['data'] ?? null,
                ]);
                Log::info('[OCR/Job] Bill updated with OCR results', [
                    'bill_id' => $this->billId,
                    'status' => $status,
                ]);
            }
        }

        // Cache the result for 15 minutes so the frontend can poll it if no bill existed yet
        Cache::put('ocr-result:' . $this->path, $ocrResult, now()->addMinutes(15));

        Log::info('[OCR/Job] Background text extraction completed', [
            'path' => $this->path,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[OCR/Job] Background OCR job failed', [
            'path' => $this->path,
            'bill_id' => $this->billId,
            'error' => $exception->getMessage(),
        ]);

        if ($this->billId) {
            if ($bill = Bill::find($this->billId)) {
                $bill->update(['ocr_status' => 'failed']);
            }
        }

        $failedResult = [
            'status' => 'failed',
            'data' => null,
            'error' => 'OCR background processing failed: ' . $exception->getMessage(),
        ];
        Cache::put('ocr-result:' . $this->path, $failedResult, now()->addMinutes(15));
    }
}
