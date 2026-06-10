<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Single-use authorization code for the OAuth "Connect" handshake.
 *
 * Lifecycle is intentionally short — 10 minutes — and one-shot. Once a
 * row's `used_at` is non-null it cannot be exchanged again. Even if a
 * partner's redirect URI gets logged somewhere, the leaked code stops
 * being useful within 10 minutes AND immediately if the partner has
 * already exchanged it.
 *
 * The code is not a JWT or any other stateless container because we
 * need three guarantees that statelessness can't easily provide:
 *
 *   1. Idempotent single-use (the `used_at` column);
 *   2. Server-side revocation if we detect abuse;
 *   3. Audit of who authorised which partner-tenant link, even if
 *      the credential row is later revoked.
 */
class OAuthAuthorizationCode extends Model
{
    use HasFactory, CentralConnection;

    /**
     * Pinned because Laravel's auto-derivation produces
     * `o_auth_authorization_codes` (snake_case inserts an underscore
     * after the leading capital O of "OAuth"), which doesn't match
     * the migration's `oauth_authorization_codes`.
     */
    protected $table = 'oauth_authorization_codes';

    /** 10-minute window from issuance to exchange. Generous enough to
     *  survive a slow partner backend, tight enough to bound abuse. */
    public const TTL_MINUTES = 10;

    /**
     * Random bytes for the code. 64 chars of url-safe base64 ≈ 384
     * bits of entropy, well above any sensible brute-force threat.
     */
    private const CODE_BYTES = 64;

    protected $fillable = [
        'code',
        'tenant_id',
        'user_id',
        'oauth_client_id',
        'redirect_uri',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    /**
     * Insert a fresh authorization code. The plaintext code is the
     * primary key the partner sees — we do NOT hash it (unlike the
     * api_key) because the row's `used_at` already gives us single-use
     * semantics, and constant-time lookup matters less when the code
     * is one-time and short-lived.
     */
    public static function issue(
        string $tenantId,
        ?int $userId,
        string $oauthClientId,
        string $redirectUri,
    ): self {
        return self::create([
            'code'            => Str::random(self::CODE_BYTES),
            'tenant_id'       => $tenantId,
            'user_id'         => $userId,
            'oauth_client_id' => $oauthClientId,
            'redirect_uri'    => $redirectUri,
            'expires_at'      => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at?->isFuture();
    }

    public function markUsed(): void
    {
        $this->forceFill(['used_at' => now()])->save();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
