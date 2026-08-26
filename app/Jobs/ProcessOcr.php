<?php

namespace App\Jobs;

use App\Models\Bill;
use App\Models\OcrJob;
use App\Services\OCRService;
use App\Support\OcrResultCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $path,
        public ?int $billId = null,
        public ?int $ocrJobId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OCRService $ocrService): void
    {
        Log::info('[OCR/Job] Starting background text extraction', [
            'path' => $this->path,
            'bill_id' => $this->billId,
            'ocr_job_id' => $this->ocrJobId,
        ]);

        $ocrJob = $this->resolveOcrJob();
        $ocrJob->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        $ocrResult = $ocrService->process($this->path);
        $success = ($ocrResult['status'] ?? null) === 'success';

        $ocrJob->update([
            'status' => $success ? 'ready' : 'failed',
            'parsed_data' => $ocrResult['data'] ?? null,
            'error_message' => $success ? null : ($ocrResult['error'] ?? 'OCR extraction failed.'),
        ]);

        if ($this->billId) {
            $bill = Bill::find($this->billId);
            if ($bill) {
                $status = $success ? 'completed' : 'failed';
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
        OcrResultCache::put($this->path, $ocrResult);

        Log::info('[OCR/Job] Background text extraction completed', [
            'path' => $this->path,
            'ocr_job_id' => $ocrJob->id,
            'status' => $ocrJob->status,
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
            'ocr_job_id' => $this->ocrJobId,
            'error' => $exception->getMessage(),
        ]);

        if ($ocrJob = $this->findOcrJob()) {
            $ocrJob->update([
                'status' => 'failed',
                'error_message' => 'OCR background processing failed: '.$exception->getMessage(),
            ]);
        }

        if ($this->billId) {
            if ($bill = Bill::find($this->billId)) {
                $bill->update(['ocr_status' => 'failed']);
            }
        }

        $failedResult = [
            'status' => 'failed',
            'data' => null,
            'error' => 'OCR background processing failed: '.$exception->getMessage(),
        ];
        OcrResultCache::put($this->path, $failedResult);
    }

    private function resolveOcrJob(): OcrJob
    {
        if ($job = $this->findOcrJob()) {
            return $job;
        }

        return OcrJob::create([
            'file_path' => $this->path,
            'status' => 'pending',
        ]);
    }

    private function findOcrJob(): ?OcrJob
    {
        if ($this->ocrJobId) {
            return OcrJob::find($this->ocrJobId);
        }

        return OcrJob::query()
            ->where('file_path', $this->path)
            ->whereNotIn('status', ['confirmed', 'discarded'])
            ->latest('id')
            ->first();
    }
}
