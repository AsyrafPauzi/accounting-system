<?php

namespace App\Services\Ai;

use App\Models\OcrSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * OpenAI-compatible client for ILMU (`https://api.ilmu.ai/v1`).
 *
 * Copilot and receipt OCR share the encrypted key on the central
 * `ocr_settings` row. Never log the key.
 */
class IlmuClient
{
    public const DEFAULT_MODEL = 'ilmu-v3.1';

    public const CHAT_URL = 'https://api.ilmu.ai/v1/chat/completions';

    public function __construct(
        private string $apiKey,
        private string $model = self::DEFAULT_MODEL,
    ) {}

    public static function fromSettings(): self
    {
        $settings = OcrSettings::current();
        $key = $settings->getDecryptedIlmuApiKey();
        if (! $key) {
            throw new RuntimeException(
                'ILMU API key is not configured. Open /admin/ocr, choose ILMU, and paste a key from https://docs.ilmu.ai.'
            );
        }

        return new self($key, $settings->ilmu_model ?: self::DEFAULT_MODEL);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>|null  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?array $tools = null, bool $jsonMode = false): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $jsonMode ? 0.1 : 0.2,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->post($payload);

        return $response->json() ?? [];
    }

    /**
     * Vision turn: image as a data URI `image_url` (ilmu-v3.1).
     *
     * @return array<string, mixed>
     */
    public function vision(string $prompt, string $imageBytes, string $mime, bool $jsonMode = true): array
    {
        $dataUri = 'data:'.$mime.';base64,'.base64_encode($imageBytes);

        return $this->chat([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                ],
            ],
        ], tools: null, jsonMode: $jsonMode);
    }

    public static function messageText(array $payload): ?string
    {
        $content = data_get($payload, 'choices.0.message.content');
        if (is_string($content) && $content !== '') {
            return $content;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function toolCalls(array $payload): array
    {
        $calls = data_get($payload, 'choices.0.message.tool_calls');

        return is_array($calls) ? $calls : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(array $payload): Response
    {
        try {
            $response = Http::timeout(45)
                ->connectTimeout(5)
                ->retry(2, 250, throw: false)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->post(self::CHAT_URL, $payload);
        } catch (Throwable $e) {
            Log::error('[ILMU] HTTP exception', ['error' => $e->getMessage()]);
            throw new RuntimeException('ILMU API request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $apiMessage = data_get($response->json(), 'error.message');
            Log::warning('[ILMU] API returned non-2xx', [
                'status' => $response->status(),
            ]);
            throw new RuntimeException(
                'ILMU API returned HTTP '.$response->status().($apiMessage ? ': '.$apiMessage : '')
            );
        }

        return $response;
    }
}
