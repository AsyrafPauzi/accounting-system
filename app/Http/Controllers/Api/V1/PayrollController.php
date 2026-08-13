<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollRequest;
use App\Models\JournalEntry;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * POST /api/v1/payroll
 *
 * Records a payroll journal from an external HR/payroll app. Only RM
 * amounts are stored — no employee or payslip rows. Requires api.key +
 * api.signed, plus plan permission payroll.run (Corporate+).
 */
class PayrollController extends Controller
{
    public function store(StorePayrollRequest $request): JsonResponse
    {
        $tenant = tenant();
        if (! $tenant || ! $tenant->hasPlanPermission('payroll.run')) {
            return response()->json([
                'error'             => 'insufficient_scope',
                'error_description' => 'The tenant\'s current plan does not include payroll posting.',
            ], 403);
        }

        $data = $request->validated();

        if (! empty($data['reference_number'])) {
            $existing = JournalEntry::query()
                ->where('reference_number', $data['reference_number'])
                ->first();

            if ($existing) {
                return response()->json($this->formatResponse($existing), 200);
            }
        }

        try {
            $journal = app(PayrollService::class)->record($data);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error'             => 'payroll_invalid',
                'error_description' => $e->getMessage(),
                'message'           => $e->getMessage(),
            ], 422);
        }

        return response()->json($this->formatResponse($journal), 201);
    }

    private function formatResponse(JournalEntry $journal): array
    {
        $journal->loadMissing('items');

        $totalDebits  = round((float) $journal->items->sum('debit'), 2);
        $totalCredits = round((float) $journal->items->sum('credit'), 2);

        return [
            'journal_entry_id' => $journal->id,
            'reference_number' => $journal->reference_number,
            'date'             => $journal->date->toDateString(),
            'status'           => $journal->status,
            'total_debits'     => $totalDebits,
            'total_credits'    => $totalCredits,
        ];
    }
}
