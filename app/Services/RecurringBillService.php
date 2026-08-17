<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\RecurringBill;
use App\Support\DocumentNumber;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringBillService
{
    public function __construct(private BillService $bills) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function create(array $data, array $items): RecurringBill
    {
        return DB::transaction(function () use ($data, $items) {
            $startDate = Carbon::parse($data['start_date']);
            $nextRun = isset($data['next_run_date']) && $data['next_run_date']
                ? Carbon::parse($data['next_run_date'])
                : $startDate->copy();

            $template = RecurringBill::create([
                'name'               => $data['name'] ?? null,
                'supplier_id'        => $data['supplier_id'],
                'cadence'            => $data['cadence'],
                'interval'           => max(1, (int) ($data['interval'] ?? 1)),
                'start_date'         => $startDate->toDateString(),
                'end_date'           => isset($data['end_date']) && $data['end_date'] ? Carbon::parse($data['end_date'])->toDateString() : null,
                'next_run_date'      => $nextRun->toDateString(),
                'is_active'          => (bool) ($data['is_active'] ?? true),
                'auto_post'          => (bool) ($data['auto_post'] ?? false),
                'payment_terms_days' => (int) ($data['payment_terms_days'] ?? 30),
                'tax_amount'         => (float) ($data['tax_amount'] ?? 0),
                'notes'              => $data['notes'] ?? null,
                'created_by'         => $data['created_by'] ?? null,
            ]);

            $this->syncItems($template, $items);

            return $template->load('items');
        });
    }

    public function generateDue(?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ? $asOf->copy() : now();
        $generated = 0;

        RecurringBill::query()
            ->due($asOf instanceof Carbon ? $asOf : Carbon::instance($asOf))
            ->with('items')
            ->chunkById(50, function ($templates) use (&$generated, $asOf) {
                foreach ($templates as $template) {
                    try {
                        $this->generateOne($template, $asOf);
                        $generated++;
                    } catch (\Throwable $e) {
                        Log::error('Recurring bill generation failed', [
                            'recurring_bill_id' => $template->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $generated;
    }

    public function generateOne(RecurringBill $template, ?CarbonInterface $asOf = null): Bill
    {
        $asOf = $asOf ? $asOf->copy() : now();
        if (! $template->is_active) {
            throw new \LogicException('This recurring bill is paused.');
        }

        return DB::transaction(function () use ($template, $asOf) {
            $issueDate = $template->next_run_date && $template->next_run_date->lessThanOrEqualTo($asOf)
                ? $template->next_run_date->copy()
                : $asOf->copy();
            $dueDate = $issueDate->copy()->addDays((int) $template->payment_terms_days);

            $items = $template->items->map(fn ($i) => [
                'account_code' => $i->account_code,
                'description'  => $i->description,
                'quantity'     => (float) $i->quantity,
                'unit_amount'  => (float) $i->unit_amount,
                'amount'       => (float) $i->amount,
            ])->all();

            $bill = $this->bills->create([
                'bill_number' => DocumentNumber::next('bills', 'bill_number', 'BILL'),
                'supplier_id' => $template->supplier_id,
                'bill_date'   => $issueDate->toDateString(),
                'due_date'    => $dueDate->toDateString(),
                'tax_amount'  => (float) $template->tax_amount,
                'created_by'  => $template->created_by,
                'private_notes' => $template->notes,
            ], $items);

            if ($template->auto_post) {
                $this->bills->post($bill->fresh('items'));
            }

            $next = $template->advanceFrom($issueDate);
            $template->update([
                'last_run_date'          => $issueDate->toDateString(),
                'next_run_date'          => $next->toDateString(),
                'last_generated_bill_id' => $bill->id,
                'generated_count'        => (int) $template->generated_count + 1,
                'is_active'              => $template->end_date && $next->isAfter($template->end_date) ? false : $template->is_active,
            ]);

            return $bill;
        });
    }

    private function syncItems(RecurringBill $template, array $items): void
    {
        foreach (array_values($items) as $idx => $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unit_amount'] ?? $item['unit_price'] ?? $item['amount'] ?? 0);
            $template->items()->create([
                'account_code' => $item['account_code'],
                'description'  => $item['description'] ?? '',
                'quantity'     => $qty,
                'unit_amount'  => $unit,
                'amount'       => (float) ($item['amount'] ?? ($qty * $unit)),
                'sort_order'   => $idx,
            ]);
        }
    }
}
