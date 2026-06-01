<?php

namespace App\Services\Ocr;

/**
 * Cross-validates a parsed OCR result by checking whether the math adds up:
 *
 *   subtotal + tax ≈ total          (within RM 0.01)
 *   sum(items)     ≈ subtotal       (within RM 0.05 — leaves room for rounding
 *                                    and for receipts that bundle small fees
 *                                    into the subtotal without a line)
 *
 * If a check fires, we LOWER confidence and attach a warning. If everything
 * balances, we BOOST confidence — math agreement is strong evidence the
 * extraction got the numeric fields right.
 *
 * Crucially: when a field is missing (e.g. simple receipt with no subtotal),
 * we SKIP the relevant check rather than claiming a false failure. Don't
 * warn the user about something the receipt didn't even claim to show.
 */
class OcrValidator
{
    private const TOTALS_TOLERANCE = 0.01;
    private const ITEMS_TOLERANCE = 0.05;

    private const CONFIDENCE_BOOST = 0.95;
    private const CONFIDENCE_DROP = 0.5;

    public function validate(OcrResult $result): OcrResult
    {
        // Failed extractions: nothing to validate.
        if ($result->status === OcrResult::STATUS_FAILED) {
            return $result;
        }

        $warnings = [];

        // Check 1: subtotal + tax ≈ total
        if ($result->subtotal !== null && $result->totalAmount !== null) {
            $tax = $result->taxAmount ?? 0.0;
            $expected = round($result->subtotal + $tax, 2);
            $actual = round($result->totalAmount, 2);
            if (abs($expected - $actual) > self::TOTALS_TOLERANCE) {
                $warnings[] = sprintf(
                    'Totals do not balance: subtotal RM %s + tax RM %s = RM %s, but total reads RM %s.',
                    number_format($result->subtotal, 2),
                    number_format($tax, 2),
                    number_format($expected, 2),
                    number_format($actual, 2)
                );
            }
        }

        // Check 2: sum(items) ≈ subtotal (only if both are present)
        if ($result->subtotal !== null && ! empty($result->items)) {
            $itemsSum = 0.0;
            foreach ($result->items as $item) {
                $itemsSum += (float) ($item['amount'] ?? 0.0);
            }
            $itemsSum = round($itemsSum, 2);
            $expected = round($result->subtotal, 2);
            if (abs($itemsSum - $expected) > self::ITEMS_TOLERANCE) {
                $warnings[] = sprintf(
                    'Line items do not match subtotal: items sum to RM %s, but subtotal reads RM %s. Some line items may be missing.',
                    number_format($itemsSum, 2),
                    number_format($expected, 2)
                );
            }
        }

        return new OcrResult(
            status: $result->status,
            provider: $result->provider,
            vendorName: $result->vendorName,
            billDate: $result->billDate,
            subtotal: $result->subtotal,
            taxAmount: $result->taxAmount,
            totalAmount: $result->totalAmount,
            currency: $result->currency,
            reference: $result->reference,
            items: $result->items,
            rawText: $result->rawText,
            confidence: empty($warnings) ? self::CONFIDENCE_BOOST : self::CONFIDENCE_DROP,
            error: $result->error,
            warnings: $warnings,
        );
    }
}
