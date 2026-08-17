<?php

namespace App\Services\Ocr\Providers;

use App\Services\Ai\IlmuClient;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\OcrResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Receipt OCR via ILMU ilmu-v3.1 vision (OpenAI-compatible image_url).
 *
 * JSON shape matches GeminiProvider so OcrValidator and ReceiptUpload stay unchanged.
 */
class IlmuProvider implements OcrProviderInterface
{
    private const MAX_INLINE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private ?IlmuClient $client = null,
    ) {}

    public function name(): string
    {
        return 'ilmu';
    }

    public function extract(string $imagePath): OcrResult
    {
        try {
            $client = $this->client ?? IlmuClient::fromSettings();
        } catch (RuntimeException $e) {
            return OcrResult::failed(provider: $this->name(), error: $e->getMessage());
        }

        $imageBytes = $this->readImageBytes($imagePath);
        if ($imageBytes === null) {
            return OcrResult::failed(
                provider: $this->name(),
                error: "Receipt file not found at $imagePath. Cannot send to ILMU.",
            );
        }

        if (strlen($imageBytes) > self::MAX_INLINE_BYTES) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Receipt image exceeds 20 MB inline limit. Resize before uploading.',
            );
        }

        $mimeType = $this->detectMimeType($imageBytes);

        try {
            $payload = $client->vision($this->prompt(), $imageBytes, $mimeType, jsonMode: true);
        } catch (Throwable $e) {
            Log::error('[OCR/ILMU] request failed', ['error' => $e->getMessage()]);

            return OcrResult::failed(
                provider: $this->name(),
                error: 'ILMU API request failed: '.$e->getMessage(),
            );
        }

        $rawJsonText = IlmuClient::messageText($payload);
        if (! $rawJsonText) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'ILMU returned no text content for this image.',
            );
        }

        $parsed = $this->decodeJsonObject($rawJsonText);
        if (! is_array($parsed)) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'ILMU returned malformed JSON. Raw response: '.mb_substr($rawJsonText, 0, 200),
                rawText: $rawJsonText,
            );
        }

        return OcrResult::success(
            provider: $this->name(),
            fields: [
                'vendor_name' => $parsed['vendor_name'] ?? null,
                'bill_date' => $parsed['bill_date'] ?? null,
                'subtotal' => isset($parsed['subtotal']) ? (float) $parsed['subtotal'] : null,
                'tax_amount' => isset($parsed['tax_amount']) ? (float) $parsed['tax_amount'] : null,
                'total_amount' => isset($parsed['total_amount']) ? (float) $parsed['total_amount'] : null,
                'currency' => $parsed['currency'] ?? null,
                'reference' => $parsed['reference'] ?? null,
                'items' => $this->normalizeItems($parsed['items'] ?? []),
                'raw_text' => $rawJsonText,
                'confidence' => 0.9,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeJsonObject(string $raw): ?array
    {
        $trimmed = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $trimmed, $m)) {
            $trimmed = trim($m[1]);
        }
        $parsed = json_decode($trimmed, true);

        return is_array($parsed) ? $parsed : null;
    }

    private function prompt(): string
    {
        return <<<PROMPT
        You are a receipt parser for a Malaysian small-business accounting system.
        Read this receipt image and return ONLY a JSON object matching this schema.
        If a field is unreadable, use null. Do not invent values. Never invent TIN or BRN.

        {
          "vendor_name": string | null,
          "bill_date": "YYYY-MM-DD" | null,
          "currency": "MYR" | "SGD" | "USD" | "EUR" | "GBP" | null,
          "subtotal": number | null,
          "tax_amount": number | null,
          "total_amount": number | null,
          "reference": string | null,
          "items": [
            {
              "description": string,
              "quantity": number | null,
              "unit_amount": number | null,
              "amount": number
            }
          ]
        }

        Notes:
        - Receipts are typically in English or Bahasa Malaysia.
        - "TOTAL" or "JUMLAH BAYARAN" maps to total_amount.
        - "JUMLAH" alone (before tax) typically maps to subtotal.
        - "SST" / "GST" / "TAX" / "CUKAI" maps to tax_amount.
        - Default currency is MYR if not specified but RM is shown.
        - Dates may be DD/MM/YYYY, DD-MM-YYYY, or "21 Mei 2025" (Bahasa month name). Always normalize to YYYY-MM-DD.
        - Amounts must be numbers (not strings), without currency symbols or thousands separators.
        - Bahasa month names: Januari=1, Februari=2, Mac=3, April=4, Mei=5, Jun=6, Julai=7, Ogos=8, September=9, Oktober=10, November=11, Disember=12.
        - For each line item, capture quantity ("Kuantiti") and unit price ("Harga Seunit") if present in a tabular receipt; set them to null when the receipt only shows a flat amount per line.
        - Do NOT include subtotal/tax/total rows as line items. Only the actual products/services purchased.
        - For the reference field, prefer the human receipt number (e.g. "RCP250521-001") over bank transaction IDs.
        PROMPT;
    }

    private function readImageBytes(string $imagePath): ?string
    {
        $imagePath = trim($imagePath);
        if ($imagePath === '') {
            return null;
        }

        try {
            if (\App\Support\UploadDisk::disk()->exists($imagePath)) {
                return \App\Support\UploadDisk::disk()->get($imagePath);
            }
            if (Storage::disk('local')->exists($imagePath)) {
                return Storage::disk('local')->get($imagePath);
            }
        } catch (Throwable) {
            return null;
        }
        $publicSample = public_path($imagePath);
        if (file_exists($publicSample)) {
            return file_get_contents($publicSample) ?: null;
        }
        if (str_starts_with($imagePath, '/') && file_exists($imagePath)) {
            return file_get_contents($imagePath) ?: null;
        }

        return null;
    }

    private function detectMimeType(string $bytes): string
    {
        if (str_starts_with($bytes, '%PDF')) {
            return 'application/pdf';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_buffer($finfo, $bytes) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime ?: 'image/jpeg';
    }

    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $description = trim((string) ($item['description'] ?? ''));
            $amount = $item['amount'] ?? null;
            if ($description === '' || ! is_numeric($amount)) {
                continue;
            }

            $row = [
                'description' => $description,
                'amount' => (float) $amount,
            ];

            $quantity = $item['quantity'] ?? null;
            $unitAmount = $item['unit_amount'] ?? $item['unit_price'] ?? null;
            if (is_numeric($quantity) && is_numeric($unitAmount) && (float) $quantity > 0 && (float) $unitAmount > 0) {
                $row['quantity'] = (float) $quantity;
                $row['unit_amount'] = (float) $unitAmount;
            }

            $clean[] = $row;
        }

        return $clean;
    }
}
