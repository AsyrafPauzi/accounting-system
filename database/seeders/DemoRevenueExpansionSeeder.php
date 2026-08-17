<?php

namespace Database\Seeders;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EstimateService;
use App\Services\RecurringInvoiceService;
use Illuminate\Database\Seeder;

/**
 * Top-up seeder for the testdemo@bukucloud.com tenant.
 *
 * Adds the "revenue stack" demo data on top of whatever DemoTestAccountSeeder
 * already produced:
 *   • 8 products & services (catalogue)
 *   • 5 estimates spanning every status (draft, sent, accepted, expired,
 *     converted — and the converted one points at a real invoice)
 *   • 3 recurring invoice templates (monthly + quarterly cadence) with one
 *     of them already having generated its first child invoice
 *   • 2 credit notes against existing paid invoices
 *
 * Run on its own:
 *     php artisan db:seed --class=DemoRevenueExpansionSeeder
 *
 * Idempotent: each section checks whether its data already exists and skips
 * anything that's already there, so re-running the seeder is safe.
 */
class DemoRevenueExpansionSeeder extends Seeder
{
    private const DEMO_EMAIL = 'testdemo@bukucloud.com';

    public function run(): void
    {
        $user = User::where('email', self::DEMO_EMAIL)->first();
        if (! $user) {
            $this->command?->warn(self::DEMO_EMAIL . ' not found — run DemoTestAccountSeeder first.');
            return;
        }

        $tenant = Tenant::find($user->tenant_id);
        if (! $tenant) {
            $this->command?->warn('Tenant ' . $user->tenant_id . ' not found.');
            return;
        }

        tenancy()->initialize($tenant);
        try {
            $products = $this->seedProducts();
            $this->seedEstimates($user->id, $products);
            $this->seedRecurringInvoices($user->id, $products);
            $this->seedCreditNotes();
        } finally {
            tenancy()->end();
        }

        $this->command?->info('Revenue expansion data added for ' . self::DEMO_EMAIL);
    }

    // ─── Products & services ───────────────────────────────────────────

    /**
     * 8 catalogue items — mix of services and goods. Code-keyed so re-runs
     * are idempotent.
     */
    private function seedProducts(): array
    {
        $rows = [
            ['code' => 'SVC-CONSULT',  'name' => 'Consulting & advisory (per hour)',     'description' => 'Strategy + implementation guidance.', 'unit_price' => 250.00, 'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'SVC-DESIGN',   'name' => 'Brand & UI design package',            'description' => 'Logo, brand guide, hi-fi mockups.',    'unit_price' => 4500.00, 'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'SVC-DEV',      'name' => 'Software development (per day)',       'description' => 'Senior engineer time-and-materials.',  'unit_price' => 1800.00, 'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'SVC-RETAINER', 'name' => 'Monthly support retainer',             'description' => '40 hours of priority support per month.', 'unit_price' => 7500.00, 'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'GD-LAPTOP',    'name' => 'Refurbished business laptop',          'description' => '14-inch, 16GB RAM, 512GB SSD, 1-yr wty.', 'unit_price' => 3200.00, 'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'GD-MERCH',     'name' => 'Branded merchandise pack',             'description' => 'Tote, notebook, sticker pack.',         'unit_price' => 65.00,  'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'SUB-CLOUD',    'name' => 'Cloud hosting — Standard tier',        'description' => 'Monthly subscription, 1 environment.',  'unit_price' => 480.00,  'tax_rate' => 8, 'account_code' => '4000'],
            ['code' => 'SUB-CLOUD-PRO', 'name' => 'Cloud hosting — Pro tier',            'description' => 'Monthly subscription, HA + backups.',   'unit_price' => 980.00,  'tax_rate' => 8, 'account_code' => '4000'],
        ];

        $products = [];
        foreach ($rows as $i => $row) {
            $products[] = Product::firstOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_active'     => true,
                    'display_order' => $i + 1,
                ])
            );
        }

        return $products;
    }

    // ─── Estimates ────────────────────────────────────────────────────

    /**
     * 5 estimates covering every status the UI knows about, including a
     * "converted" estimate that points at a brand-new draft invoice so the
     * conversion flow is observable on the dashboard / estimate index.
     */
    private function seedEstimates(int $userId, array $products): void
    {
        if (\App\Models\Estimate::count() > 0) {
            $this->command?->info('Estimates already exist, skipping.');
            return;
        }

        $service = app(EstimateService::class);
        $customers = Customer::orderBy('id')->get();
        if ($customers->count() < 4) {
            $this->command?->warn('Not enough customers to seed estimates.');
            return;
        }

        // Cache product lookup by code so each plan row reads naturally.
        $byCode = collect($products)->keyBy('code');
        $line = function (string $code, float $qty, ?float $priceOverride = null) use ($byCode): array {
            /** @var Product $p */
            $p = $byCode[$code];
            return [
                'product_id'          => $p->id,
                'description'         => $p->name,
                'quantity'            => $qty,
                'unit_price'          => $priceOverride ?? (float) $p->unit_price,
                'tax_rate'            => (float) $p->tax_rate,
                'discount_amount'     => 0,
                'item_classification' => '022',
            ];
        };

        // [customer index, status, days_ago_issued, days_until_expiry, lines]
        $plan = [
            // 0: Draft — Tropika, 4 days ago, expires in 26 days
            [0, 'draft', 4, 26, [
                $line('SVC-CONSULT', 16),
                $line('SVC-DESIGN', 1),
            ]],
            // 1: Sent — Cendol Cafe, 8 days ago, expires in 22 days
            [1, 'sent', 8, 22, [
                $line('SVC-RETAINER', 3),
            ]],
            // 2: Accepted — PPM, 12 days ago, expires in 18 days
            [2, 'accepted', 12, 18, [
                $line('GD-LAPTOP', 5),
                $line('GD-MERCH', 25),
            ]],
            // 3: Expired — Johor Tech, 60 days ago (so expiry is 30 days ago)
            [3, 'expired', 60, -30, [
                $line('SVC-DEV', 8),
            ]],
            // 4: Converted — Kinabalu, 20 days ago, expired in 10 days,
            //    will be flipped to converted + linked to a fresh invoice.
            [4, 'converted', 20, 10, [
                $line('SVC-DEV', 5),
                $line('SUB-CLOUD-PRO', 6),
            ]],
        ];

        $estimateNo = 1;
        foreach ($plan as [$ci, $status, $daysAgoIssued, $daysToExpiry, $items]) {
            /** @var Customer $customer */
            $customer = $customers[$ci];
            $issueDate = now()->subDays($daysAgoIssued)->toDateString();
            $expiryDate = now()->subDays($daysAgoIssued)->addDays($daysToExpiry)->toDateString();

            $estimate = $service->create([
                'estimate_number' => 'EST-' . $estimateNo,
                'customer_id'     => $customer->id,
                'issue_date'      => $issueDate,
                'expiry_date'     => $expiryDate,
                'currency'        => 'MYR',
                'shipping_amount' => 0,
                'customer_notes'  => 'Demo estimate — generated by DemoRevenueExpansionSeeder.',
                'created_by'      => $userId,
            ], $items);

            // Set the target status. For "converted" we go through
            // convertToInvoice() so it leaves a real invoice trail.
            if ($status === 'converted') {
                // EstimateService only allows convert from draft|sent|accepted, so
                // we mark it accepted first via the regular transition flow.
                $service->transition($estimate, 'sent');
                $service->transition($estimate->fresh(), 'accepted');
                $service->convertToInvoice($estimate->fresh(), [
                    'issue_date' => now()->subDays(2)->toDateString(),
                    'due_date'   => now()->addDays(28)->toDateString(),
                    'created_by' => $userId,
                    'msic_code'  => '70200',
                ]);
            } elseif ($status !== 'draft') {
                // For the rest we drive through the legal transitions.
                $path = match ($status) {
                    'sent'     => ['sent'],
                    'accepted' => ['sent', 'accepted'],
                    'expired'  => ['sent', 'expired'],
                    'rejected' => ['sent', 'rejected'],
                    default    => [],
                };
                foreach ($path as $next) {
                    $service->transition($estimate->fresh(), $next);
                }
            }

            $estimateNo++;
        }
    }

    // ─── Recurring invoices ──────────────────────────────────────────

    /**
     * 3 recurring invoice templates. The first one's next_run_date is set in
     * the past so we manually fire generateOne() and produce one real child
     * invoice, demonstrating a populated history.
     */
    private function seedRecurringInvoices(int $userId, array $products): void
    {
        if (\App\Models\RecurringInvoice::count() > 0) {
            $this->command?->info('Recurring invoices already exist, skipping.');
            return;
        }

        $service = app(RecurringInvoiceService::class);
        $customers = Customer::orderBy('id')->get();
        if ($customers->count() < 3) {
            $this->command?->warn('Not enough customers to seed recurring invoices.');
            return;
        }

        $byCode = collect($products)->keyBy('code');
        $line = function (string $code, float $qty, ?float $priceOverride = null) use ($byCode): array {
            /** @var Product $p */
            $p = $byCode[$code];
            return [
                'product_id'          => $p->id,
                'description'         => $p->name,
                'quantity'            => $qty,
                'unit_price'          => $priceOverride ?? (float) $p->unit_price,
                'tax_rate'            => (float) $p->tax_rate,
                'discount_amount'     => 0,
                'item_classification' => '022',
            ];
        };

        // 0: Tropika — monthly cloud subscription (Pro tier).
        // next_run_date is 2 days ago so we can generate one already.
        $tpl1 = $service->create([
            'name'              => 'Tropika — Monthly Cloud Hosting',
            'customer_id'       => $customers[0]->id,
            'cadence'           => 'monthly',
            'interval'          => 1,
            'start_date'        => now()->subMonths(2)->toDateString(),
            'next_run_date'     => now()->subDays(2)->toDateString(),
            'currency'          => 'MYR',
            'payment_terms_days'=> 14,
            'msic_code'         => '63110',
            'is_active'         => true,
            'customer_notes'    => 'Auto-generated from monthly hosting agreement.',
            'created_by'        => $userId,
        ], [
            $line('SUB-CLOUD-PRO', 1),
        ]);

        // Drive one cycle so the template shows generated_count=1 and a
        // child invoice is on the books.
        try {
            $service->generateOne($tpl1->fresh());
        } catch (\Throwable $e) {
            $this->command?->warn('Could not auto-generate first recurring invoice: ' . $e->getMessage());
        }

        // 1: Cendol Cafe — quarterly retainer.
        $service->create([
            'name'              => 'Cendol Cafe — Quarterly Retainer',
            'customer_id'       => $customers[1]->id,
            'cadence'           => 'monthly',
            'interval'          => 3,
            'start_date'        => now()->subMonths(1)->toDateString(),
            'next_run_date'     => now()->addDays(20)->toDateString(),
            'currency'          => 'MYR',
            'payment_terms_days'=> 30,
            'msic_code'         => '70200',
            'is_active'         => true,
            'customer_notes'    => 'Quarterly support retainer — billed in advance.',
            'created_by'        => $userId,
        ], [
            $line('SVC-RETAINER', 1),
        ]);

        // 2: PPM — monthly delivery, ends in 6 months.
        $service->create([
            'name'              => 'PPM — Monthly Delivery Contract',
            'customer_id'       => $customers[2]->id,
            'cadence'           => 'monthly',
            'interval'          => 1,
            'start_date'        => now()->subMonths(1)->toDateString(),
            'next_run_date'     => now()->addDays(7)->toDateString(),
            'end_date'          => now()->addMonths(6)->toDateString(),
            'currency'          => 'MYR',
            'payment_terms_days'=> 30,
            'msic_code'         => '49230',
            'is_active'         => true,
            'customer_notes'    => '6-month rolling delivery contract — auto-bills monthly.',
            'created_by'        => $userId,
        ], [
            $line('GD-MERCH', 50),
        ]);
    }

    // ─── Credit notes ────────────────────────────────────────────────

    /**
     * Credit notes issued through CreditNoteService so SST reverse and
     * leftover open credit show on the new Credit Note Show page.
     * Paid invoices have no remaining AR, so these stay unapplied.
     */
    private function seedCreditNotes(): void
    {
        if (CreditNote::count() > 0) {
            $this->command?->info('Credit notes already exist, skipping.');
            return;
        }

        $customer = Customer::query()->orderBy('id')->first();
        if (! $customer) {
            $this->command?->warn('No customers to attach credit notes to.');
            return;
        }

        $service = app(\App\Services\CreditNoteService::class);
        $service->issue([
            'customer_id'        => $customer->id,
            'cn_number'          => 'CN-DEMO-0001',
            'issue_date'         => now()->subDays(3)->toDateString(),
            'reason_code'        => '01',
            'reason_description' => 'Partial return — 1 unit damaged on arrival.',
            'currency'           => 'MYR',
        ], [[
            'description'         => 'Partial return',
            'quantity'            => 1,
            'unit_price'          => 90,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
    }
}
