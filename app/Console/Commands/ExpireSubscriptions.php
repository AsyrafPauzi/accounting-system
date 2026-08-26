<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Support\SubscriptionPeriod;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscription:expire';

    protected $description = 'Mark subscriptions past_due after period end, then expired after grace';

    public function handle(): int
    {
        $today = now()->toDateString();
        $pastDue = 0;
        $expired = 0;

        Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereNotNull('current_period_ends_at')
            ->orderBy('id')
            ->chunkById(100, function ($subs) use ($today, &$pastDue, &$expired) {
                foreach ($subs as $sub) {
                    $ends = $sub->current_period_ends_at?->toDateString();
                    $action = SubscriptionPeriod::expireAction($sub->status, $ends, $today);
                    if ($action === 'past_due') {
                        $sub->update(['status' => 'past_due']);
                        $pastDue++;
                    } elseif ($action === 'expired') {
                        $sub->update(['status' => 'expired']);
                        $expired++;
                    }
                }
            });

        $this->info("Marked {$pastDue} past_due; expired {$expired} subscription(s).");

        return self::SUCCESS;
    }
}
