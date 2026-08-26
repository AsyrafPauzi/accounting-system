<?php

namespace App\Support;

use App\Models\TaxCode;
use Illuminate\Support\Collection;

final class TaxCodeResolver
{
    /**
     * @return Collection<int, array{id: int, code: string, name: string, rate: float, type: string}>
     */
    public static function activeOptions(): Collection
    {
        TaxCodeDefaults::seedMissing();

        return TaxCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'rate', 'type'])
            ->map(fn (TaxCode $c) => [
                'id'   => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'rate' => (float) $c->rate,
                'type' => $c->type,
            ]);
    }

    public static function find(?int $taxCodeId): ?TaxCode
    {
        if (! $taxCodeId) {
            return null;
        }

        return TaxCode::query()->find($taxCodeId);
    }

    public static function resolve(?int $taxCodeId, ?float $taxRate = null): ?TaxCode
    {
        if ($code = self::find($taxCodeId)) {
            return $code;
        }

        TaxCodeDefaults::seedMissing();

        $rate = (float) ($taxRate ?? 0);
        if ($rate >= 9.5) {
            return TaxCode::query()->where('code', 'ST-10')->first();
        }
        if ($rate >= 7.5) {
            return TaxCode::query()->where('code', 'SR-8')->first();
        }
        if ($rate > 0) {
            return TaxCode::query()->where('rate', $rate)->where('is_active', true)->first();
        }

        return TaxCode::query()->where('code', 'ES')->first()
            ?? TaxCode::query()->where('code', 'ZRL')->first();
    }

    public static function rate(?int $taxCodeId, ?float $fallbackRate = 0): float
    {
        return (float) (self::resolve($taxCodeId, $fallbackRate)?->rate ?? $fallbackRate ?? 0);
    }

    public static function outputAccount(?TaxCode $code): string
    {
        return $code?->output_account_code ?: '2100';
    }

    public static function inputAccount(?TaxCode $code): string
    {
        return $code?->input_account_code ?: '1110';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{tax_code_id: ?int, tax_rate: float}
     */
    public static function normalizeLineItem(array $item): array
    {
        $taxCodeId = isset($item['tax_code_id']) ? (int) $item['tax_code_id'] : null;
        $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
        $code = self::resolve($taxCodeId ?: null, $taxRate);

        return [
            'tax_code_id' => $code?->id,
            'tax_rate'    => (float) ($code?->rate ?? $taxRate),
        ];
    }
}
