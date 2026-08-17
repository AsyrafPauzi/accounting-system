<?php

namespace App\Services;

use App\Mail\InvoiceReminderEmail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class InvoiceReminderService
{
    /** Wave-style grid: days relative to due date. */
    public const DEFAULT_OFFSETS = [-14, -7, -3, 0, 3, 7, 14];

    /**
     * @return list<int>
     */
    public function offsetsFor(Invoice $invoice): array
    {
        $overrides = $invoice->reminder_overrides;
        if (is_array($overrides) && array_key_exists('offsets', $overrides)) {
            return array_values(array_map('intval', $overrides['offsets'] ?? []));
        }
        $tenant = function_exists('tenant') ? tenant() : null;
        $stored = $tenant->reminder_offsets ?? null;
        if (is_array($stored) && $stored !== []) {
            return array_values(array_map('intval', $stored));
        }

        return self::DEFAULT_OFFSETS;
    }

    /**
     * Which offset (if any) matches today for this invoice.
     */
    public function dueOffsetToday(Invoice $invoice, ?\DateTimeInterface $asOf = null): ?int
    {
        if (! $invoice->due_date || in_array($invoice->status, ['draft', 'void', 'paid'], true)) {
            return null;
        }
        $asOf = $asOf ? \Carbon\Carbon::instance(\Carbon\Carbon::parse($asOf)) : now();
        $days = $invoice->due_date->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false);
        $days = (int) $days;
        $offsets = $this->offsetsFor($invoice);
        if (! in_array($days, $offsets, true)) {
            return null;
        }
        if ((string) $invoice->reminder_stage === (string) $days
            && $invoice->last_reminded_at
            && $invoice->last_reminded_at->isSameDay($asOf)) {
            return null;
        }

        return $days;
    }

    public function send(Invoice $invoice, int $offset): void
    {
        $invoice->loadMissing('customer');
        $email = $invoice->customer?->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (($invoice->customer->invoice_delivery_method ?? 'email') === 'none') {
            return;
        }

        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        $downloadUrl = URL::temporarySignedRoute(
            'public.invoices.download',
            now()->addDays(30),
            [
                'uuid'      => $invoice->uuid,
                'tenant_id' => tenant()?->id,
            ]
        );

        Mail::to($email)->send(new InvoiceReminderEmail($invoice, $company, $downloadUrl, $offset));

        $invoice->forceFill([
            'last_reminded_at' => now(),
            'reminder_stage'   => (string) $offset,
        ])->save();
    }

    public function sendDueForTenant(): int
    {
        $sent = 0;
        Invoice::query()
            ->whereNotIn('status', ['draft', 'void', 'paid'])
            ->whereNotNull('due_date')
            ->with('customer')
            ->chunkById(100, function ($invoices) use (&$sent) {
                foreach ($invoices as $invoice) {
                    $offset = $this->dueOffsetToday($invoice);
                    if ($offset === null) {
                        continue;
                    }
                    try {
                        $this->send($invoice, $offset);
                        $sent++;
                    } catch (\Throwable) {
                        // Keep walking other invoices.
                    }
                }
            });

        return $sent;
    }
}
