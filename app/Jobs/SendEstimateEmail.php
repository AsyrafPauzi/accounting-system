<?php

namespace App\Jobs;

use App\Mail\EstimateEmail;
use App\Models\Estimate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Queues an estimate-email send. The controller validates the
 * recipient list and flips `last_emailed_status` to `pending` before
 * dispatching this job; on success / failure the job updates the row
 * to `sent` / `failed` so the UI can surface the actual delivery
 * outcome instead of just "we queued something".
 */
class SendEstimateEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $estimateId;
    public array $recipients;
    public array $company;

    public function __construct(int $estimateId, array $recipients, array $company = [])
    {
        $this->estimateId = $estimateId;
        $this->recipients = $recipients;
        $this->company = $company;
    }

    public function handle(): void
    {
        $estimate = Estimate::with(['items', 'customer'])->findOrFail($this->estimateId);

        $company = $this->company;
        if (empty($company)) {
            $company = config('invoice.company');
            if (function_exists('tenant') && tenant()) {
                $company = tenant()->getCompanyDetails();
            }
        }

        Mail::to($this->recipients)->send(new EstimateEmail($estimate, $company));

        $estimate->forceFill([
            'last_emailed_status' => 'sent',
            'last_emailed_at'     => now(),
            'last_emailed_error'  => null,
            'last_emailed_to'     => implode(',', $this->recipients),
        ])->save();

        // Auto-promote draft → sent the first time we email it. Manual
        // status transitions still work from the UI; this is just a
        // convenience so the timeline reflects reality.
        if ($estimate->status === 'draft') {
            $estimate->status = 'sent';
            $estimate->save();
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($estimate = Estimate::find($this->estimateId)) {
            $estimate->forceFill([
                'last_emailed_status' => 'failed',
                'last_emailed_error'  => $exception->getMessage(),
            ])->save();
        }

        Log::error('Failed to send estimate email', [
            'estimate_id' => $this->estimateId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
