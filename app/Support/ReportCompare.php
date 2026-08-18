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
}
