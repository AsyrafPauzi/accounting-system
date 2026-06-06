<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseService;
use Illuminate\Console\Command;

/**
 * CUSTOMER-side diagnostic. Prints the current license status, used
 * for support ("send us the output of `php artisan license:status`")
 * and as a smoke test inside the install wizard.
 */
class LicenseStatus extends Command
{
    protected $signature = 'license:status {--json : Emit JSON instead of a table}';

    protected $description = 'Print the current license validity + claims.';

    public function handle(LicenseService $svc): int
    {
        $svc->flush();
        $result = $svc->evaluate();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info('License status: '.$result['status']);
        if (! empty($result['claims'])) {
            $this->newLine();
            $this->line('Claims:');
            foreach ($result['claims'] as $k => $v) {
                $disp = is_array($v) ? implode(', ', $v) : (string) $v;
                $this->line(str_pad("  {$k}", 22).' '.$disp);
            }
        }
        if ($result['status'] === 'valid') {
            return self::SUCCESS;
        }
        return self::FAILURE;
    }
}
