<?php

namespace App\Services;

final class FxGainLossService
{
    public const GAIN_ACCOUNT = '4200';

    public const LOSS_ACCOUNT = '4300';

    public function isForeignCurrency(string $documentCurrency, string $baseCurrency): bool
    {
        return strtoupper($documentCurrency) !== strtoupper($baseCurrency);
    }

    public function resolvePaymentRate(float $documentRate, ?float $paymentRate): float
    {
        if ($paymentRate !== null && $paymentRate > 0) {
            return $paymentRate;
        }

        return $documentRate > 0 ? $documentRate : 1.0;
    }

    /**
     * Customer receipt — Dr bank at payment rate, Cr AR at document rate.
     *
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    public function invoiceReceiptLines(
        float $amountDocumentCurrency,
        float $documentRate,
        float $paymentRate,
        string $bankAccountCode,
        string $arAccountCode = '1100',
    ): array {
        $documentBase = round($amountDocumentCurrency * $documentRate, 2);
        $paymentBase = round($amountDocumentCurrency * $paymentRate, 2);

        return $this->appendFxLines([
            ['account_code' => $bankAccountCode, 'debit' => $paymentBase, 'credit' => 0],
            ['account_code' => $arAccountCode, 'debit' => 0, 'credit' => $documentBase],
        ], $paymentBase - $documentBase, receipt: true);
    }

    /**
     * Supplier payment — Dr AP at document rate, Cr bank at payment rate.
     *
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    public function billPaymentLines(
        float $amountDocumentCurrency,
        float $documentRate,
        float $paymentRate,
        string $bankAccountCode,
        string $apAccountCode = '2110',
    ): array {
        $documentBase = round($amountDocumentCurrency * $documentRate, 2);
        $paymentBase = round($amountDocumentCurrency * $paymentRate, 2);

        return $this->appendFxLines([
            ['account_code' => $apAccountCode, 'debit' => $documentBase, 'credit' => 0],
            ['account_code' => $bankAccountCode, 'debit' => 0, 'credit' => $paymentBase],
        ], $paymentBase - $documentBase, receipt: false);
    }

    /**
     * @param  list<array{account_code: string, debit: float, credit: float}>  $lines
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    private function appendFxLines(array $lines, float $fxDiff, bool $receipt): array
    {
        $fxDiff = round($fxDiff, 2);
        if (abs($fxDiff) < 0.01) {
            return $lines;
        }

        if ($receipt) {
            if ($fxDiff > 0) {
                $lines[] = ['account_code' => self::GAIN_ACCOUNT, 'debit' => 0, 'credit' => $fxDiff];
            } else {
                $lines[] = ['account_code' => self::LOSS_ACCOUNT, 'debit' => abs($fxDiff), 'credit' => 0];
            }

            return $lines;
        }

        if ($fxDiff > 0) {
            $lines[] = ['account_code' => self::LOSS_ACCOUNT, 'debit' => $fxDiff, 'credit' => 0];
        } else {
            $lines[] = ['account_code' => self::GAIN_ACCOUNT, 'debit' => 0, 'credit' => abs($fxDiff)];
        }

        return $lines;
    }

    /**
     * Month-end unrealized AR revaluation — positive adjustment increases AR (gain).
     *
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    public function unrealizedArLines(float $adjustment, string $arAccountCode = '1100'): array
    {
        $adjustment = round($adjustment, 2);
        if (abs($adjustment) < 0.01) {
            return [];
        }

        if ($adjustment > 0) {
            return [
                ['account_code' => $arAccountCode, 'debit' => $adjustment, 'credit' => 0],
                ['account_code' => self::GAIN_ACCOUNT, 'debit' => 0, 'credit' => $adjustment],
            ];
        }

        $abs = abs($adjustment);

        return [
            ['account_code' => self::LOSS_ACCOUNT, 'debit' => $abs, 'credit' => 0],
            ['account_code' => $arAccountCode, 'debit' => 0, 'credit' => $abs],
        ];
    }

    /**
     * Month-end unrealized AP revaluation — positive adjustment increases AP (loss).
     *
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    public function unrealizedApLines(float $adjustment, string $apAccountCode = '2110'): array
    {
        $adjustment = round($adjustment, 2);
        if (abs($adjustment) < 0.01) {
            return [];
        }

        if ($adjustment > 0) {
            return [
                ['account_code' => self::LOSS_ACCOUNT, 'debit' => $adjustment, 'credit' => 0],
                ['account_code' => $apAccountCode, 'debit' => 0, 'credit' => $adjustment],
            ];
        }

        $abs = abs($adjustment);

        return [
            ['account_code' => $apAccountCode, 'debit' => $abs, 'credit' => 0],
            ['account_code' => self::GAIN_ACCOUNT, 'debit' => 0, 'credit' => $abs],
        ];
    }
}
