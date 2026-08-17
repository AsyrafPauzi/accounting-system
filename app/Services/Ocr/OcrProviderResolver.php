<?php

namespace App\Services\Ocr;

use App\Models\OcrSettings;
use App\Services\Ocr\Providers\NullProvider;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Resolves the active OCR provider based on the central OcrSettings row.
 *
 * Failures (missing class, missing binary, missing API key) degrade to
 * NullProvider rather than throwing — receipts should still upload even when
 * OCR is misconfigured. The failure reason is logged for super-admins.
 */
class OcrProviderResolver
{
    public function __construct(
        private Container $container,
    ) {}

    public function resolve(): OcrProviderInterface
    {
        $settings = OcrSettings::current();

        return match ($settings->provider) {
            OcrSettings::PROVIDER_TESSERACT => $this->resolveProvider(
                'App\\Services\\Ocr\\Providers\\TesseractProvider',
                'Tesseract OCR is not yet installed on this server. Run composer require thiagoalessio/tesseract_ocr and ensure the tesseract binary is on PATH.',
            ),
            OcrSettings::PROVIDER_GEMINI => $this->resolveProvider(
                'App\\Services\\Ocr\\Providers\\GeminiProvider',
                'Gemini OCR provider is not yet wired up.',
            ),
            OcrSettings::PROVIDER_ILMU => $this->resolveProvider(
                'App\\Services\\Ocr\\Providers\\IlmuProvider',
                'ILMU OCR provider is not yet wired up.',
            ),
            default => new NullProvider(),
        };
    }

    /**
     * Tries to instantiate the provider via the container so dependencies are wired.
     * Falls back to NullProvider on any error so receipt uploads keep working.
     */
    private function resolveProvider(string $class, string $missingMessage): OcrProviderInterface
    {
        if (! class_exists($class)) {
            return $this->logAndFallback($missingMessage);
        }

        try {
            $instance = $this->container->make($class);
            if (! $instance instanceof OcrProviderInterface) {
                return $this->logAndFallback("$class does not implement OcrProviderInterface.");
            }
            return $instance;
        } catch (Throwable $e) {
            return $this->logAndFallback("Failed to construct $class: ".$e->getMessage());
        }
    }

    private function logAndFallback(string $message): OcrProviderInterface
    {
        \Log::warning('[OCR] Falling back to NullProvider', ['reason' => $message]);
        return new NullProvider();
    }
}
