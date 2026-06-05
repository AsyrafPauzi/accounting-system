<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Domain service for recurring invoice templates.
 *
 * Two responsibilities:
 *
 * 1. CRUD on the template: create, update, sync line items.
 * 2. Daily generation: walk every active template whose `next_run_date <= today`
 *    and materialise a fresh DRAFT invoice from it. The user posts those
 *    drafts manually — nothing auto-emails or auto-posts.
 */
class RecurringInvoiceService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * Create a recurring template + items inside a transaction.
     */
    public function create(array $data, array $items): RecurringInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $startDate = Carbon::parse($data['start_date']);
            $nextRun = isset($data['next_run_date']) && $data['next_run_date']
                ? Carbon::parse($data['next_run_date'])
                : $startDate->copy();

            $template = RecurringInvoice::create([
                'name'                => $data['name'] ?? null,
                'customer_id'         => $data['customer_id'],
                'cadence'             => $data['cadence'],
                'interval'            => max(1, (int) ($data['interval'] ?? 1)),
                'start_date'          => $startDate->toDateString(),
                'end_date'            => isset($data['end_date']) && $data['end_date'] ? Carbon::parse($data['end_date'])->toDateString() : null,
                'next_run_date'       => $nextRun->toDateString(),
                'is_active'           => (bool) ($data['is_active'] ?? true),
                'currency'            => strtoupper((string) ($data['currency'] ?? 'MYR')),
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? 1),
                'shipping_amount'     => (float) ($data['shipping_amount'] ?? 0),
                'payment_terms_days'  => (int) ($data['payment_terms_days'] ?? 30),
                'msic_code'           => $data['msic_code'] ?? '00000',
                'customer_notes'      => $data['customer_notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
                'created_by'          => $data['created_by'] ?? null,
            ]);

            $this->syncItems($template, $items);

            return $template->load('items');
        });
    }

    /**
     * Replace header + items on an existing template. Does NOT auto-bump
     * `next_run_date`; if the user is editing the schedule, they pick the
     * new next-run explicitly.
     */
    public function update(RecurringInvoice $template, array $data, array $items): void
    {
        DB::transaction(function () use ($template, $data, $items) {
            $template->update([
                'name'                => $data['name'] ?? null,
                'customer_id'         => $data['customer_id'],
                'cadence'             => $data['cadence'],
                'interval'            => max(1, (int) ($data['interval'] ?? 1)),
                'start_date'          => Carbon::parse($data['start_date'])->toDateString(),
                'end_date'            => isset($data['end_date']) && $data['end_date'] ? Carbon::parse($data['end_date'])->toDateString() : null,
                'next_run_date'       => isset($data['next_run_date']) && $data['next_run_date']
                    ? Carbon::parse($data['next_run_date'])->toDateString()
                    : $template->next_run_date,
                'is_active'           => (bool) ($data['is_active'] ?? true),
                'currency'            => strtoupper((string) ($data['currency'] ?? $template->currency ?? 'MYR')),
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? $template->exchange_rate ?? 1),
                'shipping_amount'     => (float) ($data['shipping_amount'] ?? 0),
                'payment_terms_days'  => (int) ($data['payment_terms_days'] ?? $template->payment_terms_days ?? 30),
                'msic_code'           => $data['msic_code'] ?? '00000',
                'customer_notes'      => $data['customer_notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
            ]);

            $template->items()->delete();
            $this->syncItems($template, $items);
        });
    }

    /**
     * Walk every active template whose next_run_date <= today and create a
     * draft Invoice from each. Returns the number of invoices generated.
     *
     * Designed to be idempotent per day — once it bumps `next_run_date` past
     * today, a second run that same day generates nothing.
     */
    public function generateDue(?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ? $asOf->copy() : now();

        $generated = 0;

        RecurringInvoice::query()
            ->due($asOf instanceof Carbon ? $asOf : Carbon::instance($asOf))
            ->with('items')
            ->chunkById(50, function ($templates) use (&$generated, $asOf) {
                foreach ($templates as $template) {
                    try {
                        $this->generateOne($template, $asOf);
                        $generated++;
                    } catch (\Throwable $e) {
                        Log::error('Recurring invoice generation failed', [
                            'recurring_invoice_id' => $template->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $generated;
    }

    /**
     * Materialise a draft Invoice from one template. Bumps `next_run_date`
     * forward by one cycle and increments `generated_count`. Auto-deactivates
     * the template if the new next-run would land after `end_date`.
     */
    public function generateOne(RecurringInvoice $template, ?CarbonInterface $asOf = null): Invoice
    {
        $asOf = $asOf ? $asOf->copy() : now();

        if (! $template->is_active) {
            throw new \LogicException('This recurring invoice is paused.');
        }

        if ($template->end_date && $template->end_date->isBefore($asOf)) {
            $template->update(['is_active' => false]);
            throw new \LogicException('This recurring invoice has ended.');
        }

        return DB::transaction(function () use ($template, $asOf) {
            $issueDate = $template->next_run_date && $template->next_run_date->lessThanOrEqualTo($asOf)
                ? $template->next_run_date->copy()
                : $asOf->copy();

            $dueDate = $issueDate->copy()->addDays((int) $template->payment_terms_days);

            $items = $template->items->map(fn ($i) => [
                'description'         => $i->description,
                'quantity'            => (float) $i->quantity,
                'unit_price'          => (float) $i->unit_price,
                'tax_rate'            => (float) $i->tax_rate,
                'discount_amount'     => (float) $i->discount_amount,
                'item_classification' => $i->item_classification ?: '022',
            ])->all();

            $invoice = $this->invoices->create([
                'invoice_number'  => $this->nextInvoiceNumber(),
                'msic_code'       => $template->msic_code ?: '00000',
                'customer_id'     => $template->customer_id,
                'issue_date'      => $issueDate->toDateString(),
                'due_date'        => $dueDate->toDateString(),
                'currency'        => $template->currency,
                'exchange_rate'   => $template->exchange_rate,
                'shipping_amount' => $template->shipping_amount,
                'customer_notes'  => $template->customer_notes,
                'show_signature'  => true,
                'created_by'      => $template->created_by,
            ], $items);

            // Advance the schedule. Anchor on the date we just used so the
            // cadence stays stable (e.g. always the 15th of each month) even
            // when the cron runs late.
            $newNextRun = $template->advanceFrom($issueDate);

            $patch = [
                'last_run_date'             => $issueDate->toDateString(),
                'last_generated_invoice_id' => $invoice->id,
                'generated_count'           => (int) $template->generated_count + 1,
                'next_run_date'             => $newNextRun->toDateString(),
            ];

            // If the next run would fall after end_date, auto-pause.
            if ($template->end_date && $newNextRun->isAfter($template->end_date)) {
                $patch['is_active'] = false;
            }

            $template->update($patch);

            return $invoice;
        });
    }

    private function syncItems(RecurringInvoice $template, array $items): void
    {
        foreach (array_values($items) as $idx => $item) {
            $template->items()->create([
                'product_id'          => $item['product_id'] ?? null,
                'item_classification' => $item['item_classification'] ?? null,
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_price'          => $item['unit_price'],
                'tax_rate'            => $item['tax_rate'] ?? 0,
                'discount_amount'     => $item['discount_amount'] ?? 0,
                'display_order'       => $item['display_order'] ?? $idx,
            ]);
        }
    }

    private function nextInvoiceNumber(): string
    {
        $last = Invoice::where('invoice_number', 'like', 'INV-%')->orderBy('id', 'desc')->first();
        if ($last && preg_match('/^INV-(\d+)$/', $last->invoice_number, $m)) {
            return 'INV-' . ((int) $m[1] + 1);
        }

        return 'INV-1';
    }
}
