<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EstimateService;
use App\Support\WalksTenants;
use Illuminate\Console\Command;

class ExpireEstimatesCommand extends Command
{
    use WalksTenants;

    protected $signature = 'estimates:expire
                            {--tenants=* : Limit to specific tenant ids}';

    protected $description = 'Mark sent/draft estimates past their expiry date as expired';

    public function handle(EstimateService $estimates): int
    {
        $total = 0;

        return $this->forEachTenant($this, function () use ($estimates, &$total) {
            $n = $estimates->markExpired();
            $total += $n;
            $this->line("  {$n} estimate(s) expired");
        }, 'estimates');
    }
}
