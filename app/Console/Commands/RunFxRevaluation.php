<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FxRevaluationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RunFxRevaluation extends Command
{
    protected $signature = 'fx:revaluate
                            {--month= : Month-end date (YYYY-MM); defaults to previous month}
                            {--rates=* : Currency:rate pairs, e.g. USD:4.80}
                            {--tenants=* : Limit to specific tenant ids}';

    protected $description = 'Post month-end unrealized FX revaluation for open foreign-currency AR/AP';

    public function handle(FxRevaluationService $revaluation): int
    {
        $rates = $this->parseRates($this->option('rates'));
        if ($rates === []) {
            $this->error('Provide at least one --rates=USD:4.80 pair.');

            return self::FAILURE;
        }

        $monthEnd = $this->option('month')
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

            if (! Schema::hasTable('invoices') || ! Schema::hasTable('journal_entries')) {
                $this->line('  Required tables missing — skip');
                continue;
            }

            try {
                $posted = $revaluation->revaluateAll($monthEnd, $rates);
                $count = count($posted);
                $total += $count;
                $this->line("  Posted {$count} FX revaluation(s) through {$monthEnd}.");
            } catch (\Throwable $e) {
                $this->error('  '.$e->getMessage());
            }
        }

        $this->info("Done. {$total} FX revaluation(s) posted.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $pairs
     * @return array<string, float>
     */
    private function parseRates(array $pairs): array
    {
        $rates = [];
        foreach ($pairs as $pair) {
            if (! str_contains($pair, ':')) {
                continue;
            }
            [$currency, $rate] = explode(':', $pair, 2);
            $currency = strtoupper(trim($currency));
            $rate = (float) trim($rate);
            if ($currency !== '' && $rate > 0) {
                $rates[$currency] = $rate;
            }
        }

        return $rates;
    }
}
