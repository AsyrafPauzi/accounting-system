<?php

namespace App\Services\Ocr\Providers;

use App\Models\OcrSettings;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\OcrResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cloud OCR via Google Gemini's `generateContent` endpoint.
 *
 * The image is sent inline as base64 with a structured-output prompt
 * (Appendix A of docs/plans/2026-06-ocr-provider-toggle.md).
 *
 * Configuration is read from OcrSettings::current():
 *   - gemini_api_key (encrypted at rest)
 *   - gemini_model    (default: gemini-1.5-flash)
 */
class GeminiProvider implements OcrProviderInterface
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const MAX_INLINE_BYTES = 20 * 1024 * 1024; // 20 MB hard ceiling per Gemini docs

    public function name(): string
    {
        return 'gemini';
    }

    public function extract(string $imagePath): OcrResult
    {
        $settings = OcrSettings::current();
        $apiKey = $settings->getDecryptedApiKey();

        if (! $apiKey) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Gemini provider is selected but no API key is configured. Open /admin/ocr and paste a key from https://aistudio.google.com/apikey.',
            );
        }

        $imageBytes = $this->readImageBytes($imagePath);
        if ($imageBytes === null) {
            return OcrResult::failed(
                provider: $this->name(),
                error: "Receipt file not found at $imagePath. Cannot send to Gemini.",
            );
        }

        if (strlen($imageBytes) > self::MAX_INLINE_BYTES) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Receipt image exceeds 20 MB inline limit for Gemini. Resize before uploading.',
            );
        }

        $mimeType = $this->detectMimeType($imageBytes);
        $model = $settings->gemini_model ?: 'gemini-1.5-flash';

        try {
            $response = Http::timeout(30)
                ->withQueryParameters(['key' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->post(sprintf(self::ENDPOINT, $model), [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            ['text' => $this->prompt()],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => base64_encode($imageBytes),
                                ],
                            ],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'response_mime_type' => 'application/json',
                    ],
                ]);
        } catch (Throwable $e) {
            Log::error('[OCR/Gemini] HTTP exception', ['error' => $e->getMessage()]);
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Gemini API request failed: '.$e->getMessage(),
            );
        }

        if ($response->failed()) {
            $errorMessage = $this->humanizeApiError($response->status(), $response->json());
            Log::warning('[OCR/Gemini] API returned non-2xx', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return OcrResult::failed(provider: $this->name(), error: $errorMessage);
        }

        $payload = $response->json();
        $rawJsonText = data_get($payload, 'candidates.0.content.parts.0.text');

        if (! $rawJsonText) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Gemini returned no text content. The image may have been blocked by safety filters.',
            );
        }

        $parsed = json_decode($rawJsonText, true);
        if (! is_array($parsed)) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Gemini returned malformed JSON. Raw response: '.mb_substr($rawJsonText, 0, 200),
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
                'confidence' => 0.9, // Gemini doesn't expose confidence; we assume high.
            ],
        );
    }

    private function prompt(): string
    {
        return <<<PROMPT
        You are a receipt parser for a Malaysian small-business accounting system.
        Read this receipt image and return ONLY a JSON object matching this schema.
        If a field is unreadable, use null. Do not invent values.

        {
          "vendor_name": string | null,
          "bill_date": "YYYY-MM-DD" | null,
          "currency": "MYR" | "SGD" | "USD" | "EUR" | "GBP" | null,
          "subtotal": number | null,
          "tax_amount": number | null,
          "total_amount": number | null,
          "reference": string | null,
          "items": [{"description": string, "amount": number}]
        }

        Notes:
        - Receipts are typically in English or Bahasa Malaysia.
        - "TOTAL" or "JUMLAH" maps to total_amount.
        - "SST" / "GST" / "TAX" / "CUKAI" maps to tax_amount.
        - Default currency is MYR if not specified but RM is shown.
        - Dates may be DD/MM/YYYY or DD-MM-YYYY format. Always normalize to YYYY-MM-DD.
        - Amounts must be numbers (not strings), without currency symbols or thousands separators.
        PROMPT;
    }

    private function readImageBytes(string $imagePath): ?string
    {
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->get($imagePath);
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
        // Magic-byte sniff first (cheap and reliable for our two file families).
        if (str_starts_with($bytes, '%PDF')) {
            return 'application/pdf';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_buffer($finfo, $bytes) : null;
        if ($finfo) finfo_close($finfo);
        return $mime ?: 'image/jpeg';
    }

    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) return [];
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $description = trim((string) ($item['description'] ?? ''));
            $amount = $item['amount'] ?? null;
            if ($description === '' || ! is_numeric($amount)) continue;
            $clean[] = [
                'description' => $description,
                'amount' => (float) $amount,
            ];
        }
        return $clean;
    }

    private function humanizeApiError(int $status, ?array $body): string
    {
        $apiMessage = data_get($body, 'error.message');

        return match (true) {
            $status === 400 => 'Gemini rejected the request: '.($apiMessage ?: 'malformed input'),
            $status === 401 || $status === 403 => 'Gemini API key is invalid or missing the right permissions.',
            $status === 429 => 'Gemini API quota exceeded. Try again later or upgrade your Google AI plan.',
            $status >= 500 => 'Gemini service unavailable (HTTP '.$status.'). Try again in a moment.',
            default => 'Gemini API returned HTTP '.$status.': '.($apiMessage ?: 'unknown error'),
        };
    }
}
