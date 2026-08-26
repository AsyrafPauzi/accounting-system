<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Support\AccountingPeriodResolver;
use App\Support\DocumentNumber;
use App\Support\JournalWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class FixedAssetService
{
    public const PPE_ACCOUNT = '1500';

    public const ACCUM_DEP_ACCOUNT = '1510';

    public const DEP_EXPENSE_ACCOUNT = '5810';

    public function nextNumber(): string
    {
        return DocumentNumber::next('fixed_assets', 'asset_number', 'FA');
    }

    public function register(array $data): FixedAsset
    {
        return FixedAsset::create([
            'asset_number'            => $data['asset_number'] ?? $this->nextNumber(),
            'name'                    => $data['name'],
            'description'             => $data['description'] ?? null,
            'purchase_date'           => $data['purchase_date'],
            'cost'                    => round((float) $data['cost'], 2),
            'salvage_value'           => round((float) ($data['salvage_value'] ?? 0), 2),
            'useful_life_months'      => (int) $data['useful_life_months'],
            'asset_account_code'      => $data['asset_account_code'] ?? self::PPE_ACCOUNT,
            'accum_dep_account_code'  => $data['accum_dep_account_code'] ?? self::ACCUM_DEP_ACCOUNT,
            'dep_expense_account_code'=> $data['dep_expense_account_code'] ?? self::DEP_EXPENSE_ACCOUNT,
            'status'                  => 'active',
        ]);
    }

    public function update(FixedAsset $asset, array $data): FixedAsset
    {
        if ($asset->status !== 'active') {
            throw new LogicException('Disposed assets cannot be edited.');
        }

        $asset->update([
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'purchase_date'      => $data['purchase_date'],
            'cost'               => round((float) $data['cost'], 2),
            'salvage_value'      => round((float) ($data['salvage_value'] ?? 0), 2),
            'useful_life_months' => (int) $data['useful_life_months'],
        ]);

        return $asset->fresh();
    }

    /**
     * Post straight-line depreciation for one calendar month.
     */
    public function depreciateMonth(FixedAsset $asset, string $monthEndDate): FixedAsset
    {
        if ($asset->status !== 'active') {
            throw new LogicException('Cannot depreciate a disposed asset.');
        }

        $monthEnd = Carbon::parse($monthEndDate)->endOfMonth()->toDateString();
        AccountingPeriodResolver::assertOpenForDate($monthEnd);

        $monthStart = Carbon::parse($monthEnd)->startOfMonth()->toDateString();
        if ($asset->last_depreciated_month && Carbon::parse($asset->last_depreciated_month)->gte($monthStart)) {
            throw new LogicException('Depreciation for this month has already been posted.');
        }

        if ($asset->isFullyDepreciated()) {
            throw new LogicException('Asset is fully depreciated.');
        }

        $amount = min(
            $asset->monthlyDepreciation(),
            $asset->netBookValue() - (float) $asset->salvage_value,
        );
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            throw new LogicException('Nothing left to depreciate.');
        }

        return DB::transaction(function () use ($asset, $monthEnd, $monthStart, $amount) {
            JournalWriter::postSystem([
                'date'           => $monthEnd,
                'description'    => 'Depreciation: '.$asset->name.' ('.$asset->asset_number.')',
                'reference_type' => 'Fixed Asset Depreciation',
                'reference_id'   => $asset->id,
            ], [
                ['account_code' => $asset->dep_expense_account_code, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $asset->accum_dep_account_code, 'debit' => 0, 'credit' => $amount],
            ]);

            $asset->update([
                'accumulated_depreciation' => round((float) $asset->accumulated_depreciation + $amount, 2),
                'last_depreciated_month'   => $monthStart,
            ]);

            return $asset->fresh();
        });
    }

    /**
     * @return list<FixedAsset>
     */
    public function depreciateAllForMonth(string $monthEndDate): array
    {
        $updated = [];
        $assets = FixedAsset::query()->where('status', 'active')->orderBy('id')->get();

        foreach ($assets as $asset) {
            if ($asset->isFullyDepreciated()) {
                continue;
            }
            try {
                $updated[] = $this->depreciateMonth($asset, $monthEndDate);
            } catch (LogicException $e) {
                if (! str_contains($e->getMessage(), 'already been posted')) {
                    throw $e;
                }
            }
        }

        return $updated;
    }

    public function dispose(FixedAsset $asset, float $proceeds, string $disposalDate, string $bankAccountCode = '1200'): FixedAsset
    {
        if ($asset->status !== 'active') {
            throw new LogicException('Asset is already disposed.');
        }

        AccountingPeriodResolver::assertOpenForDate($disposalDate);
        $proceeds = round($proceeds, 2);
        $nbv = $asset->netBookValue();
        $gainLoss = round($proceeds - $nbv, 2);

        return DB::transaction(function () use ($asset, $proceeds, $disposalDate, $nbv, $gainLoss, $bankAccountCode) {
            $lines = [
                ['account_code' => $asset->accum_dep_account_code, 'debit' => (float) $asset->accumulated_depreciation, 'credit' => 0],
                ['account_code' => $asset->asset_account_code, 'debit' => 0, 'credit' => (float) $asset->cost],
            ];

            if ($proceeds > 0) {
                $lines[] = ['account_code' => $bankAccountCode, 'debit' => $proceeds, 'credit' => 0];
            }

            if ($gainLoss > 0) {
                $lines[] = ['account_code' => '4200', 'debit' => 0, 'credit' => $gainLoss];
            } elseif ($gainLoss < 0) {
                $lines[] = ['account_code' => '4300', 'debit' => abs($gainLoss), 'credit' => 0];
            }

            JournalWriter::postSystem([
                'date'           => $disposalDate,
                'description'    => 'Disposal: '.$asset->name.' ('.$asset->asset_number.')',
                'reference_type' => 'Fixed Asset Disposal',
                'reference_id'   => $asset->id,
            ], $lines);

            $asset->update([
                'status'            => 'disposed',
                'disposed_date'       => $disposalDate,
                'disposal_proceeds'   => $proceeds,
                'accumulated_depreciation' => (float) $asset->cost,
            ]);

            return $asset->fresh();
        });
    }
}
