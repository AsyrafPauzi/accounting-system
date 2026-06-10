<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Per-tenant API credential issued through the OAuth "Connect" flow.
 *
 * One row authorises ONE partner (oauth_client_id) to call BukuCloud's
 * /api/v1 surface on behalf of ONE tenant. A tenant can have multiple
 * rows if they connect multiple partners (today only Fin Persona).
 *
 * Two key materials live here:
 *
 *   - Plaintext API key (`pk_live_...`): generated, shown ONCE in the
 *     OAuth token-exchange response, then never recoverable from the
 *     DB. We only persist a SHA-256 hash + last-4 for lookup and UI.
 *
 *   - Plaintext signing key (`sk_live_...`): generated, returned with
 *     the API key on token exchange. We persist it encrypted at rest
 *     because we DO need to recover it server-side every time we
 *     verify an HMAC signature on a write request. Same pattern as
 *     OcrSettings::gemini_api_key.
 *
 * Auth flow (per request):
 *
 *   1. Partner sends `Authorization: Bearer pk_live_xxx`
 *   2. ApiKeyAuth middleware: hash(pk_live_xxx) → look up this row
 *   3. row->isActive() and row->tenant->hasPlanPermission('api.access')
 *   4. Initialise tenancy on row->tenant_id
 *   5. For mutating endpoints, ApiSignatureVerifier middleware decrypts
 *      transaction_signing_key, computes hmac(body) and compares to
 *      X-BukuCloud-Signature header; rejects on mismatch.
 *
 * Audit trail: issued_by_user_id and revoked_by_user_id let
 * ops/customers see exactly who turned this on or off.
 */
class TenantApiCredential extends Model
{
    use HasFactory, CentralConnection, Auditable;

    /**
     * Public-key prefix. Sent in the Authorization header as
     * `Authorization: Bearer pk_live_<28 random url-safe chars>`. The
     * `pk_live_` prefix is purely for readability — bots scanning
     * GitHub for accidental commits can pick it out and we can revoke.
     */
    public const API_KEY_PREFIX = 'pk_live_';

    /**
     * HMAC signing-key prefix. NEVER sent over the wire in plaintext —
     * partners use it locally to compute X-BukuCloud-Signature on
     * mutating requests. The `sk_live_` prefix is again for grep-
     * defence on accidental leaks.
     */
    public const SIGNING_KEY_PREFIX = 'sk_live_';

    /** Length of the random portion (after the prefix). 28 bytes
     *  base64url ≈ 38 chars, which is comfortably above 128 bits of
     *  entropy and below the practical URL/header size limits. */
    private const RANDOM_BYTES = 28;

    protected $fillable = [
        'tenant_id',
        'oauth_client_id',
        'api_key_hash',
        'api_key_last4',
        'transaction_signing_key', // stored encrypted; setter below
        'signing_key_last4',
        'issued_by_user_id',
        'last_used_at',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    /**
     * Mint a brand-new credential row for the given tenant + partner.
     * Returns the row plus the **plaintext** key materials, which the
     * caller MUST surface to the partner (via the OAuth token-exchange
     * response) and then discard — there is no second chance to read
     * either plaintext from the database.
     *
     * @return array{credential: self, api_key: string, signing_key: string}
     */
    public static function issueFor(
        string $tenantId,
        string $oauthClientId,
        ?int $issuedByUserId = null,
    ): array {
        $apiKey = self::generatePlaintext(self::API_KEY_PREFIX);
        $signingKey = self::generatePlaintext(self::SIGNING_KEY_PREFIX);

        $credential = self::create([
            'tenant_id'              => $tenantId,
            'oauth_client_id'        => $oauthClientId,
            'api_key_hash'           => hash('sha256', $apiKey),
            'api_key_last4'          => substr($apiKey, -4),
            'transaction_signing_key' => Crypt::encryptString($signingKey),
            'signing_key_last4'      => substr($signingKey, -4),
            'issued_by_user_id'      => $issuedByUserId,
        ]);

        return [
            'credential'  => $credential,
            'api_key'     => $apiKey,
            'signing_key' => $signingKey,
        ];
    }

    /**
     * Look up an active credential row by its plaintext API key. Returns
     * null when no match, the key is malformed, or the credential is
     * revoked. Constant-time compare on the hash is unnecessary — we
     * only do an indexed equality lookup, no per-byte branching.
     */
    public static function findActiveByPlaintextKey(string $plaintext): ?self
    {
        if (! str_starts_with($plaintext, self::API_KEY_PREFIX)) {
            return null;
        }
        $hash = hash('sha256', $plaintext);
        return self::query()
            ->where('api_key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Decrypt the stored HMAC secret. Throws on a corrupt ciphertext
     * — that's a legitimate error condition (DB tampering or a key
     * rotation we missed) and the caller should 500 rather than fall
     * back to a permissive code path.
     */
    public function decryptSigningKey(): string
    {
        return Crypt::decryptString($this->transaction_signing_key);
    }

    public function maskedApiKey(): string
    {
        return self::API_KEY_PREFIX . str_repeat('•', 12) . $this->api_key_last4;
    }

    public function maskedSigningKey(): string
    {
        return self::SIGNING_KEY_PREFIX . str_repeat('•', 12) . $this->signing_key_last4;
    }

    public function revoke(?int $byUserId): void
    {
        $this->forceFill([
            'revoked_at'         => now(),
            'revoked_by_user_id' => $byUserId,
        ])->save();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** Build a `pk_live_xxx` / `sk_live_xxx` value with the prefix and
     *  RANDOM_BYTES of url-safe base64. */
    private static function generatePlaintext(string $prefix): string
    {
        // Str::random uses random_bytes under the hood and yields
        // URL-safe alnum, which is what we want — no '/', no '+'.
        return $prefix . Str::random(self::RANDOM_BYTES);
    }
}
