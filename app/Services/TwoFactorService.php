<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;

/**
 * Wraps the underlying TOTP engine + recovery-code generation so the
 * controller stays thin and we have one place to change the algorithm
 * (e.g. swap to WebAuthn) without touching every call site.
 *
 * Design choices worth flagging:
 *
 * 1. Two secrets, not one: `two_factor_pending_secret` is what the user
 *    is actively scanning into their authenticator app. Only when they
 *    successfully verify a 6-digit code does it get promoted to
 *    `two_factor_secret`. Without that split, a half-finished enrolment
 *    would lock anybody out — they'd have a "secret" stored but no way
 *    to prove they ever scanned it.
 *
 * 2. Recovery codes are stored hashed. Same model as passwords: if our
 *    DB leaks, the codes alone don't unlock anything. Tradeoff: we
 *    can't show the user their existing codes after the initial setup
 *    — only regenerate. That's the standard product expectation in
 *    every 2FA system that hashes its codes.
 *
 * 3. Default issuer is the controller name from `config/privacy.php` so
 *    the entry that shows up in the user's authenticator reads
 *    "BukuCloud (foo@example.com)" rather than the framework default.
 */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;
    private const RECOVERY_CODE_LENGTH = 10;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Returns a data: URL of the QR code the authenticator app can
     * scan. We use the QRCode flavour of the package so we don't have
     * to ship a JS QR library to the frontend.
     */
    public function qrCodeDataUrl(User $user, string $secret): string
    {
        $issuer = config('privacy.controller_name', 'BukuCloud');
        $label = $user->email;
        $qr = new Google2FAQRCode();
        return $qr->getQRCodeInline($issuer, $label, $secret);
    }

    /**
     * `validKeyAccountCode()` walks a small +/- 1 window to be tolerant
     * of clock drift between the server and the user's phone.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = trim(preg_replace('/\s+/', '', $code));
        if ($code === '' || ! ctype_digit($code)) {
            return false;
        }
        return (bool) $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Generate a fresh batch of recovery codes — returns plaintext for
     * display, AND a parallel hashed array suitable for persistence.
     *
     * @return array{plain: array<int, string>, hashed: array<int, string>}
     */
    public function generateRecoveryCodes(): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            // 10 lowercase alphanumeric, easier to read than 16 random
            // bytes hex but still 50+ bits of entropy.
            $code = strtolower(Str::random(self::RECOVERY_CODE_LENGTH));
            $plain[]  = $code;
            $hashed[] = Hash::make($code);
        }
        return ['plain' => $plain, 'hashed' => $hashed];
    }

    /**
     * Match a plaintext recovery code against the stored hashes,
     * returning the (one-shot) hash array minus the consumed code so
     * the caller can persist the new shorter list.
     *
     * @param  array<int, string>  $hashedCodes
     * @return array<int, string>|null  null = no match (don't update anything)
     */
    public function consumeRecoveryCode(array $hashedCodes, string $candidate): ?array
    {
        $candidate = strtolower(trim($candidate));
        if ($candidate === '') {
            return null;
        }
        foreach ($hashedCodes as $i => $hash) {
            if (Hash::check($candidate, $hash)) {
                $remaining = $hashedCodes;
                unset($remaining[$i]);
                return array_values($remaining);
            }
        }
        return null;
    }
}
