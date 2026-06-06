<?php

namespace App\Logging\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Walks every LogRecord's context + extra arrays and redacts values whose
 * keys look sensitive (passwords, API tokens, session cookies, two-factor
 * secrets, honeypot fields, etc.).
 *
 * Why this exists:
 *   Laravel's exception handler logs the full request payload on validation
 *   and unhandled errors. Without scrubbing, a 422 on the registration form
 *   writes the user's plaintext password to storage/logs/laravel.log, and
 *   a failed Gemini API roundtrip leaves the API key in the trace. Both
 *   are PDPA-relevant exposures even when the log file itself is private.
 *
 * Strategy:
 *   - Substring match (case-insensitive) on the key name. We never inspect
 *     the value, so we don't have to maintain regex patterns for tokens.
 *   - Recurse into nested arrays so structures like ['request' => ['password' => '...']]
 *     get scrubbed to the leaves.
 *   - Stop scrubbing past 6 levels deep — defence against pathological
 *     payloads, and 6 is more than any real Laravel context tree.
 */
class ScrubSensitive implements ProcessorInterface
{
    public const REDACTED = '[REDACTED]';

    /**
     * Substrings checked against lower-cased keys. Anything that matches
     * gets its value replaced with REDACTED. Order doesn't matter, but
     * keep the list short and curated — every entry adds work to every
     * log line in the application.
     */
    private const SENSITIVE_SUBSTRINGS = [
        'password',
        'passwd',
        'secret',
        'api_key',
        'apikey',
        'token',           // covers _token, csrf_token, remember_token, two_factor_token, ...
        'authorization',   // header value
        'auth',            // bearer, oauth, etc.
        'cookie',
        'set-cookie',
        'session_id',
        '_hp_',            // honeypot fields
        'two_factor',
        'recovery_code',
        'gemini',          // Gemini API key shows up in OCR settings traces
        'stripe',
        'toyyibpay_secret',
        'tin',             // Malaysian Tax Identification Number — PDPA sensitive
    ];

    private const MAX_DEPTH = 6;

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->scrub($record->context, 0),
            extra:   $this->scrub($record->extra, 0),
        );
    }

    private function scrub(array $data, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            foreach (self::SENSITIVE_SUBSTRINGS as $needle) {
                if (str_contains($lowerKey, $needle)) {
                    $data[$key] = self::REDACTED;
                    continue 2;
                }
            }

            if (is_array($value)) {
                $data[$key] = $this->scrub($value, $depth + 1);
            }
        }

        return $data;
    }
}
