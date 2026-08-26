<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FixedAssetService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RunDepreciation extends Command
{
    protected $signature = 'depreciation:run
                            {--month= : Month to depreciate (YYYY-MM); defaults to previous month}
                            {--tenants=* : Limit to specific tenant ids}';

    protected $description = 'Post straight-line monthly depreciation for all active fixed assets';

    public function handle(FixedAssetService $assets): int
    {
        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')->endOfMonth()->toDateString()
            : now()->subMonth()->endOfMonth()->toDateString();

        $ids = $this->option('tenants');
        $tenants = $ids ? Tenant::query()->whereIn('id', $ids)->get() : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::FAILURE;
        }

        $total = 0;
        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");
            tenancy()->initialize($tenant);

            if (! Schema::hasTable('fixed_assets')) {
                $this->line('  fixed_assets table missing — skip');
                continue;
            }

            try {
                $updated = $assets->depreciateAllForMonth($month);
                $count = count($updated);
                $total += $count;
                $this->line("  Posted depreciation for {$count} asset(s) through {$month}.");
            } catch (\Throwable $e) {
                $this->error('  '.$e->getMessage());
            }
        }

        $this->info("Done. {$total} asset depreciation(s) posted.");

        return self::SUCCESS;
    }
}
