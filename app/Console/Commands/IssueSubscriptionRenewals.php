<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionRenewalService;
use App\Support\Deployment;
use Illuminate\Console\Command;

class IssueSubscriptionRenewals extends Command
{
    protected $signature = 'subscription:issue-renewals';

    protected $description = 'Create Billplz payment-link renewals for due monthly/yearly subscriptions';

    public function handle(SubscriptionRenewalService $service): int
    {
        if (Deployment::isSelfHosted()) {
            $this->info('Skipped (self-hosted).');

            return self::SUCCESS;
        }

        $created = 0;
        $ensured = 0;

        Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereIn('interval', ['monthly', 'yearly'])
            ->whereNotNull('current_period_ends_at')
            ->with(['plan', 'pendingPlan'])
            ->orderBy('id')
            ->chunkById(50, function ($subs) use ($service, &$created, &$ensured) {
                foreach ($subs as $sub) {
                    $wasCreated = false;
                    $renewal = $service->issueIfDue($sub, $wasCreated);
                    if ($renewal) {
                        $ensured++;
                        if ($wasCreated) {
                            $created++;
                        }
                    }
                }
            });

        $this->info("Created {$created} new renewal(s); ensured {$ensured} pending/paid-ready row(s).");

        return self::SUCCESS;
    }
}
