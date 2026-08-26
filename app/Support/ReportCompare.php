<?php

namespace App\Support;

final class ReportCompare
{
    public static function mergeLines(array $current, array $prior): array
    {
        $priorByCode = [];
        foreach ($prior as $row) {
            $priorByCode[$row['code']] = $row;
        }

        $seen = [];
        $out = [];
        foreach ($current as $row) {
            $compareAmount = (float) ($priorByCode[$row['code']]['amount'] ?? 0);
            $out[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'amount' => (float) $row['amount'],
                'compare_amount' => $compareAmount,
                'variance' => round($row['amount'] - $compareAmount, 2),
            ];
            $seen[$row['code']] = true;
        }

        foreach ($prior as $row) {
            if (isset($seen[$row['code']])) {
                continue;
            }

            $out[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'amount' => 0.0,
                'compare_amount' => (float) $row['amount'],
                'variance' => round(0 - $row['amount'], 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, name: string, type: string, debit: float, credit: float}>  $current
     * @param  list<array{code: string, name: string, type: string, debit: float, credit: float}>  $prior
     * @return list<array<string, mixed>>
     */
    public static function mergeTrialBalance(array $current, array $prior): array
    {
        $priorByCode = [];
        foreach ($prior as $row) {
            $priorByCode[$row['code']] = $row;
        }

        $seen = [];
        $out = [];
        foreach ($current as $row) {
            $prev = $priorByCode[$row['code']] ?? null;
            $out[] = [
                ...$row,
                'compare_debit'   => (float) ($prev['debit'] ?? 0),
                'compare_credit'  => (float) ($prev['credit'] ?? 0),
                'debit_variance'  => round($row['debit'] - (float) ($prev['debit'] ?? 0), 2),
                'credit_variance' => round($row['credit'] - (float) ($prev['credit'] ?? 0), 2),
            ];
            $seen[$row['code']] = true;
        }

        foreach ($prior as $row) {
            if (isset($seen[$row['code']])) {
                continue;
            }
            $out[] = [
                ...$row,
                'debit'           => 0.0,
                'credit'          => 0.0,
                'compare_debit'   => (float) $row['debit'],
                'compare_credit'  => (float) $row['credit'],
                'debit_variance'  => round(0 - (float) $row['debit'], 2),
                'credit_variance' => round(0 - (float) $row['credit'], 2),
            ];
        }

        return $out;
    }
}
