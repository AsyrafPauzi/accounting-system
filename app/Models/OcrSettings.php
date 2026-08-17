<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class OcrSettings extends Model
{
    // Pin queries to the central DB even when a tenant is initialized — this
    // table lives only in the central database (one row, platform-wide settings).
    use CentralConnection;


    public const PROVIDER_DISABLED = 'disabled';
    public const PROVIDER_TESSERACT = 'tesseract';
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_ILMU = 'ilmu';

    public const PROVIDERS = [
        self::PROVIDER_DISABLED,
        self::PROVIDER_TESSERACT,
        self::PROVIDER_GEMINI,
        self::PROVIDER_ILMU,
    ];

    protected $table = 'ocr_settings';

    protected $fillable = [
        'provider',
        'gemini_api_key',
        'gemini_model',
        'ilmu_api_key',
        'ilmu_model',
        'tesseract_languages',
        'max_image_mb',
    ];

    protected $casts = [
        'max_image_mb' => 'integer',
    ];

    /**
     * Per-request memo. We deliberately don't use the Cache facade because
     * Stancl Tenancy wraps it with a tag-based store wrapper, and the
     * database cache driver doesn't support tagging. Reading one row from
     * the central DB once per request is cheap.
     */
    protected static ?self $memo = null;

    public static function current(): self
    {
        if (static::$memo) {
            return static::$memo;
        }

        $row = static::query()->find(1);
        if (! $row) {
            // Avoid mass-assigning `id` (which is not in $fillable). Build the row
            // by hand and save with default column values from the migration.
            $row = new self();
            $row->id = 1;
            $row->save();
            $row->refresh();
        }

        return static::$memo = $row;
    }

    public static function flushCache(): void
    {
        static::$memo = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public function getDecryptedApiKey(): ?string
    {
        return $this->decryptStored($this->gemini_api_key);
    }

    public function getDecryptedIlmuApiKey(): ?string
    {
        return $this->decryptStored($this->ilmu_api_key);
    }

    private function decryptStored(?string $stored): ?string
    {
        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Stores the API key encrypted at rest.
     * Pass null/empty to clear it.
     */
    public function setApiKey(?string $plain): void
    {
        $this->gemini_api_key = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    public function setIlmuApiKey(?string $plain): void
    {
        $this->ilmu_api_key = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    /**
     * Convenience accessor: last 4 characters of the stored key for masked UI display.
     * Returns null when no key is set.
     */
    public function maskedApiKey(): ?string
    {
        return $this->maskKey($this->getDecryptedApiKey());
    }

    public function maskedIlmuApiKey(): ?string
    {
        return $this->maskKey($this->getDecryptedIlmuApiKey());
    }

    private function maskKey(?string $plain): ?string
    {
        if (! $plain) {
            return null;
        }

        return str_repeat('•', 12).substr($plain, -4);
    }

    public function isEnabled(): bool
    {
        return $this->provider !== self::PROVIDER_DISABLED;
    }
}
