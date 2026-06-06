<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PDPA / housekeeping retention pass.
 *
 * Runs daily via the scheduler. Two responsibilities:
 *
 *   1. Trim aged operational tables (sessions, password resets, failed
 *      jobs, audit logs) so we're not hoarding personal data we don't
 *      need any more. Each table has its own retention rationale below.
 *
 *   2. Honour scheduled account-erasure requests. After the cooling-off
 *      window expires, the user row is hard-deleted from the central DB
 *      and a final audit-log entry is written. We deliberately *do not*
 *      drop the tenant's database — Income Tax Act 1967 requires
 *      aggregate financial records to be retained for 7 years. Tenant
 *      DB teardown is a manual, audited operation that lives outside
 *      this scheduled job.
 *
 * Operations are wrapped in try/catch per step so a failure on one
 * tenant or one table doesn't kill the whole pass. The command logs
 * what it actually changed so you can confirm it's working in
 * storage/logs/retention.log.
 */
class RetentionPurge extends Command
{
    protected $signature = 'retention:purge
        {--dry-run : Print what would be removed without touching anything}';

    protected $description = 'Purge expired sessions, password reset tokens, failed jobs, old audit logs, and process scheduled account deletions';

    /** Days each table is allowed to keep rows. Tune in one place. */
    private const RETENTION = [
        'sessions'              => 30,
        'password_reset_tokens' => 7,
        'failed_jobs'           => 30,
        'central_audit_logs'    => 540,  // 18 months
        'tenant_audit_logs'     => 540,  // 18 months
    ];

    private const ERASURE_COOLING_OFF_DAYS = 30;

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $prefix = $this->dryRun ? '[DRY RUN] ' : '';

        $now = Carbon::now();

        // 1. Central DB housekeeping
        $this->info($prefix.'Trimming central operational tables…');
        $this->trim('sessions', 'last_activity', $now->copy()->subDays(self::RETENTION['sessions'])->timestamp, isUnixTimestamp: true);
        $this->trim('password_reset_tokens', 'created_at', $now->copy()->subDays(self::RETENTION['password_reset_tokens']));
        $this->trim('failed_jobs', 'failed_at', $now->copy()->subDays(self::RETENTION['failed_jobs']));
        $this->trim('audit_logs', 'created_at', $now->copy()->subDays(self::RETENTION['central_audit_logs']));

        // 2. Tenant audit logs — same retention but iterating tenants. We
        // only trim the largest known PII-bearing table; full per-tenant
        // GC is a different beast.
        $this->info($prefix.'Trimming tenant audit logs…');
        $this->trimAcrossTenants(
            'audit_logs',
            'created_at',
            $now->copy()->subDays(self::RETENTION['tenant_audit_logs']),
        );

        // 3. Process account-erasure requests whose cooling-off has
        // elapsed. Order this last so any forensic data we needed during
        // the cooling-off window is still on disk while we're processing.
        $this->info($prefix.'Processing account erasure requests…');
        $this->processErasures($now->copy()->subDays(self::ERASURE_COOLING_OFF_DAYS));

        $this->info('Retention pass complete.');
        return self::SUCCESS;
    }

    /**
     * Delete rows from `$table` whose `$column` is older than
     * `$threshold`. Optionally treat the column as a unix integer
     * timestamp (Laravel sessions use `last_activity` that way).
     *
     * @param  Carbon|int  $threshold
     */
    private function trim(string $table, string $column, $threshold, bool $isUnixTimestamp = false): void
    {
        try {
            if (! Schema::hasTable($table)) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning("RetentionPurge: schema check failed for {$table}", ['err' => $e->getMessage()]);
            return;
        }

        $query = DB::table($table)->where($column, '<', $isUnixTimestamp ? $threshold : ($threshold instanceof Carbon ? $threshold->toDateTimeString() : $threshold));

        if ($this->dryRun) {
            $count = $query->count();
            $this->line("  would delete {$count} from {$table}");
            return;
        }

        $deleted = $query->delete();
        if ($deleted > 0) {
            $this->line("  deleted {$deleted} from {$table}");
            Log::info('RetentionPurge: deleted rows', ['table' => $table, 'count' => $deleted]);
        }
    }

    /**
     * Run trim() once per tenant, switching tenancy in/out. Failures on
     * one tenant don't abort the others.
     */
    private function trimAcrossTenants(string $table, string $column, Carbon $threshold): void
    {
        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($table, $column, $threshold) {
            try {
                tenancy()->initialize($tenant);
                $this->trim($table, $column, $threshold);
            } catch (\Throwable $e) {
                Log::warning('RetentionPurge: tenant pass failed', [
                    'tenant_id' => $tenant->id,
                    'table'     => $table,
                    'err'       => $e->getMessage(),
                ]);
            } finally {
                tenancy()->end();
            }
        });
    }

    /**
     * Hard-delete users whose erasure cooling-off window has elapsed.
     *
     * Notes:
     *   - We deliberately keep the user's tenant database in place. The
     *     tenant's financial records are subject to Income Tax Act 1967
     *     retention (7 years) and should be redacted in a separate,
     *     auditable batch — not as a side-effect of this routine.
     *   - We log a 'user_erased' audit event before the row goes so the
     *     trail of "this user existed and was deleted on N" survives.
     */
    private function processErasures(Carbon $threshold): void
    {
        $candidates = User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', $threshold)
            ->get();

        if ($candidates->isEmpty()) {
            $this->line('  no erasures due');
            return;
        }

        foreach ($candidates as $user) {
            try {
                Log::info('RetentionPurge: erasing user', [
                    'user_id'                  => $user->id,
                    'tenant_id'                => $user->tenant_id,
                    'deletion_requested_at'    => optional($user->deletion_requested_at)->toIso8601String(),
                    'cooling_off_days'         => self::ERASURE_COOLING_OFF_DAYS,
                    'note'                     => 'tenant database retained for tax-record obligations',
                ]);

                if ($this->dryRun) {
                    $this->line("  would erase user_id={$user->id} email={$user->email}");
                    continue;
                }

                // Sever the auth session and deny future logins by clearing
                // the password hash before the row goes — protects against
                // any in-flight session that hadn't been logged out yet.
                $user->forceFill([
                    'password'            => null,
                    'remember_token'      => null,
                    'two_factor_secret'   => null,
                ])->saveQuietly();

                $firmId = $user->firm_id;
                $wasFirmOwner = $user->firm_role === 'owner';

                $user->delete();
                $this->line("  erased user_id={$user->id}");

                // If this user was the only owner of a firm AND the firm
                // has zero remaining staff, soft-delete the firm row
                // alongside them. The AccountErasureController already
                // blocked this path when the firm had active clients, so
                // soft-delete here is just tidying up. Firm uses
                // SoftDeletes so super-admin can still see the audit
                // trail in the central DB.
                if ($wasFirmOwner && $firmId) {
                    $this->maybeSoftDeleteFirm((int) $firmId);
                }
            } catch (\Throwable $e) {
                Log::error('RetentionPurge: erasure failed', [
                    'user_id' => $user->id,
                    'err'     => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Soft-delete a firm if it has no remaining owner / staff and no
     * active client links. Defensive checks here even though the
     * controller already blocks firm deletion with active clients —
     * the daily job runs against a moving DB and we don't want to
     * trash a firm that gained an owner between the schedule and now.
     */
    private function maybeSoftDeleteFirm(int $firmId): void
    {
        try {
            $firm = \App\Models\Firm::find($firmId);
            if (! $firm || $firm->trashed()) {
                return;
            }

            $remainingStaff = \App\Models\User::query()
                ->where('firm_id', $firmId)
                ->count();

            $activeClients = \App\Models\FirmClient::query()
                ->where('firm_id', $firmId)
                ->where('status', 'active')
                ->count();

            if ($remainingStaff > 0 || $activeClients > 0) {
                return; // not the last person out, leave the firm alone
            }

            $firm->forceFill(['status' => 'archived'])->saveQuietly();
            $firm->delete(); // SoftDeletes — sets deleted_at
            $this->line("  soft-deleted firm_id={$firmId} (no remaining owner / clients)");
            Log::info('RetentionPurge: firm soft-deleted', ['firm_id' => $firmId]);
        } catch (\Throwable $e) {
            Log::warning('RetentionPurge: firm soft-delete failed', [
                'firm_id' => $firmId,
                'err'     => $e->getMessage(),
            ]);
        }
    }
}
