<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

/**
 * Builds a zip bundle of everything the calling user is entitled to under
 * PDPA's "right of access" — their central account, their tenant's
 * metadata, and a CSV per personal-data table inside the tenant DB.
 *
 * Design choices worth knowing about:
 *
 * 1. **Synchronous build, streamed download.** The job *could* be queued,
 *    but for a tenant-sized SME the export is small enough (<5 MB) that
 *    the controller can build and stream in one request and we avoid a
 *    queue + email + signed-URL stack just for compliance. We can swap
 *    to async later by moving build() into a queued job.
 *
 * 2. **Cursor over `get()`** for tenant tables. Even though the dataset is
 *    small today, a tenant on the Corporate plan with two years of
 *    history could realistically have thousands of invoices; cursor()
 *    keeps memory flat.
 *
 * 3. **Best-effort table coverage.** If a table doesn't exist (different
 *    deployments enable different features), we silently skip it. The
 *    export still succeeds with whatever does exist — partial PDPA data
 *    is better than failing the whole export.
 *
 * 4. **Sensitive fields stripped.** We never emit hashed passwords,
 *    two-factor secrets, or remember tokens — they're not "personal data"
 *    a user would want a copy of, and they're security-sensitive.
 */
class DataExportBuilder
{
    /**
     * Tables in the tenant database we know about and want to include in
     * the export. Add to this list when shipping new tenant features that
     * touch personal data. Map shape: table => [columns to select].
     *
     * Empty column array means "all columns".
     */
    private const TENANT_TABLES = [
        'customers'        => [],
        'suppliers'        => [],
        'invoices'         => [],
        'invoice_items'    => [],
        'bills'            => [],
        'bill_items'       => [],
        'estimates'        => [],
        'estimate_items'   => [],
        'recurring_invoices' => [],
        'credit_notes'     => [],
        'journal_entries'  => [],
        'journal_items'    => [],
        'products'         => [],
        'payments'         => [],
        'audit_logs'       => [],
    ];

    /**
     * Build an export bundle and return the absolute path to the zip on
     * the local filesystem. Caller is responsible for streaming and then
     * deleting the temp file.
     */
    public function build(User $user): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'pdpa-export-').'.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open temp zip for data export.');
        }

        // README first so the user understands what they're looking at
        // before they open the JSON / CSV files.
        $zip->addFromString('README.txt', $this->readme($user));

        // Central-side records (User, Tenant, Subscription) need to be
        // collected *before* we switch to the tenant context, otherwise
        // the central connection query targets the tenant DB.
        $zip->addFromString('account.json', $this->jsonEncode($this->accountSummary($user)));
        $zip->addFromString('tenant.json',  $this->jsonEncode($this->tenantSummary($user)));

        // Tenant-side tables. Initialise tenancy if the caller didn't
        // already do it; always restore the previous state on exit so we
        // don't pollute the caller.
        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;
        $shouldEnd = false;
        if ($tenant && (! tenancy()->initialized || tenant()?->id !== $tenant->id)) {
            tenancy()->initialize($tenant);
            $shouldEnd = true;
        }

        try {
            foreach (self::TENANT_TABLES as $table => $columns) {
                $csv = $this->dumpTableToCsv($table, $columns);
                if ($csv !== null) {
                    $zip->addFromString("{$table}.csv", $csv);
                }
            }
        } finally {
            if ($shouldEnd) {
                tenancy()->end();
            }
        }

        $zip->close();

        return $tempPath;
    }

    private function readme(User $user): string
    {
        $generated = Carbon::now()->toIso8601String();
        $controller = config('privacy.controller_name', 'BukuCloud');
        $dpo = config('privacy.dpo_email', 'dpo@bukucloud.com');

        return <<<TXT
{$controller} — PDPA Data Export

Generated:  {$generated}
For user:   {$user->email}
Tenant id:  {$user->tenant_id}

This bundle contains the personal data we hold about you as required
under section 30 of the Personal Data Protection Act 2010 (Malaysia).

Files in this archive
---------------------

  account.json   - your user account on the BukuCloud platform.
  tenant.json    - your organisation's metadata + active subscription.
  *.csv          - one CSV per personal-data table inside your
                   organisation's database. Empty / missing tables
                   are skipped silently.

What's NOT included
-------------------

  - Password hashes, two-factor secrets, remember tokens (security
    sensitive, not personal data you would meaningfully use).
  - Receipt images / PDFs themselves. They live on your tenant's
    private storage; export those separately from the relevant
    bills if you need them.

Questions
---------

  Email the Data Protection Officer at {$dpo}.
TXT;
    }

    private function accountSummary(User $user): array
    {
        return [
            'id'                       => $user->id,
            'name'                     => $user->name,
            'email'                    => $user->email,
            'role'                     => $user->roles->pluck('name')->all(),
            'is_active'                => (bool) $user->is_active,
            'tenant_id'                => $user->tenant_id,
            'theme_preference'         => $user->theme_preference,
            'privacy_accepted_at'      => optional($user->privacy_accepted_at)->toIso8601String(),
            'privacy_accepted_version' => $user->privacy_accepted_version,
            'two_factor_enabled'       => ! is_null($user->two_factor_confirmed_at),
            'created_at'               => optional($user->created_at)->toIso8601String(),
            'updated_at'               => optional($user->updated_at)->toIso8601String(),
        ];
    }

    private function tenantSummary(User $user): array
    {
        if (! $user->tenant_id) {
            return [];
        }

        $tenant = Tenant::find($user->tenant_id);
        $subscription = Subscription::with('plan')
            ->where('tenant_id', $user->tenant_id)
            ->latest('id')
            ->first();

        return [
            'tenant_id'   => $tenant?->id,
            'created_at'  => optional($tenant?->created_at)->toIso8601String(),
            'subscription' => $subscription ? [
                'plan'                  => $subscription->plan?->slug,
                'plan_name'             => $subscription->plan?->name,
                'status'                => $subscription->status,
                'interval'              => $subscription->interval,
                'current_period_start'  => optional($subscription->current_period_start)->toDateString(),
                'current_period_ends_at'=> optional($subscription->current_period_ends_at)->toDateString(),
                'extra_seats'           => $subscription->extra_seats,
            ] : null,
        ];
    }

    /**
     * Stream a tenant table out as CSV. Returns null if the table doesn't
     * exist on this tenant's DB.
     */
    private function dumpTableToCsv(string $table, array $columns): ?string
    {
        try {
            if (! Schema::hasTable($table)) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning("DataExportBuilder: schema check failed for {$table}", ['err' => $e->getMessage()]);
            return null;
        }

        $select = $columns ?: ['*'];

        $rows = DB::table($table);
        if ($select !== ['*']) {
            $rows = $rows->select($select);
        }

        $headers = null;
        $buffer = fopen('php://temp', 'w+');
        if ($buffer === false) {
            return null;
        }

        try {
            // PHP 8.4 deprecates the implicit `\` escape — pass `''` so we
            // emit RFC-4180 strict CSV (and so the deprecation warning
            // doesn't dump into the export logs once per row).
            foreach ($rows->orderBy('id')->cursor() as $row) {
                $assoc = (array) $row;
                if ($headers === null) {
                    $headers = array_keys($assoc);
                    fputcsv($buffer, $headers, ',', '"', '');
                }
                fputcsv($buffer, array_map(static function ($v) {
                    if (is_array($v) || is_object($v)) {
                        return json_encode($v, JSON_UNESCAPED_UNICODE);
                    }
                    return $v;
                }, $assoc), ',', '"', '');
            }

            if ($headers === null) {
                $columnList = Schema::getColumnListing($table);
                if (empty($columnList)) {
                    return null;
                }
                fputcsv($buffer, $columnList, ',', '"', '');
            }

            rewind($buffer);
            return stream_get_contents($buffer) ?: '';
        } finally {
            fclose($buffer);
        }
    }

    private function jsonEncode($value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) ?: '{}';
    }
}
