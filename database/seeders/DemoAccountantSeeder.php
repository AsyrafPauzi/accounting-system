<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillService;
use App\Services\InvoiceService;
use App\Support\DefaultChartOfAccounts;
use Database\Seeders\Concerns\ProvisionsTenantDatabase;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * testaccounter@bukucloud.com — populated demo for the Practice console.
 *
 * What this produces (idempotent: skips if the firm-owner already exists):
 *
 *   • A firm "BukuCloud Demo Accountancy" subscribed to Practice
 *     Starter (cap = 5 clients) with the firm-owner above.
 *
 *   • Two SME tenants linked to the firm:
 *       1. "Demo Trading Sdn Bhd" — owner: demo-trader@bukucloud.com
 *       2. "Demo Services Sdn Bhd" — owner: demo-services@bukucloud.com
 *     Each tenant ships with the standard Chart of Accounts, 2
 *     customers, 1 supplier, 2 invoices (1 paid + 1 unpaid) and 1
 *     unpaid bill so the practice dashboard has real numbers.
 *
 *   • Both client tenants are on the free Startup SME plan — see the
 *     billing-model documentation in `app/Models/Firm.php` for why the
 *     firm and the tenant pay separately.
 *
 * Run on its own:
 *   php artisan db:seed --class=DemoAccountantSeeder
 *
 * Login:
 *   firm-owner    testaccounter@bukucloud.com    (Password123!)
 *   client #1     demo-trader@bukucloud.com      (Password123!)
 *   client #2     demo-services@bukucloud.com    (Password123!)
 */
class DemoAccountantSeeder extends Seeder
{
    use WithoutModelEvents;
    use ProvisionsTenantDatabase;

    private const FIRM_OWNER_EMAIL = 'testaccounter@bukucloud.com';
    private const FIRM_NAME        = 'BukuCloud Demo Accountancy';
    private const FIRM_PLAN_SLUG   = 'practice-starter';
    private const PASSWORD         = 'Password123!';

    /** Bank account code used to receive customer payments + pay suppliers. */
    private const BANK_CODE = '1200';

    /**
     * The two client SMEs we'll provision under the firm.
     */
    private const CLIENTS = [
        [
            'display' => 'Demo Trading Sdn Bhd',
            'email'   => 'demo-trader@bukucloud.com',
            'sme_plan' => 'startup',
            'invoices' => [
                ['amount' => 2_500.00, 'paid' => true],
                ['amount' => 1_800.00, 'paid' => false],
            ],
            'bill'    => 650.00,
        ],
        [
            'display' => 'Demo Services Sdn Bhd',
            'email'   => 'demo-services@bukucloud.com',
            'sme_plan' => 'startup',
            'invoices' => [
                ['amount' => 4_000.00, 'paid' => true],
                ['amount' => 950.00,  'paid' => false],
            ],
            'bill'    => 320.00,
        ],
    ];

    public function run(): void
    {
        $existing = User::where('email', self::FIRM_OWNER_EMAIL)->first();
        if ($existing) {
            $this->command?->info(self::FIRM_OWNER_EMAIL . ' already exists; demo accountant seed skipped (idempotent).');
            $this->ensureSalesDemoLinked($existing);
            return;
        }

        $firmPlan = Plan::where('slug', self::FIRM_PLAN_SLUG)->first();
        if (! $firmPlan) {
            $this->command?->error('Plan ' . self::FIRM_PLAN_SLUG . ' not found. Run PlanSeeder first.');
            return;
        }
        $startupSmePlan = Plan::where('slug', 'startup')->first();
        if (! $startupSmePlan) {
            $this->command?->error('Plan startup not found. Run PlanSeeder first.');
            return;
        }

        // 1. Create the firm + firm-level subscription.
        $firm = Firm::create([
            'name'   => self::FIRM_NAME,
            'slug'   => Str::slug(self::FIRM_NAME) . '-' . random_int(100, 999),
            'status' => 'active',
        ]);

        $firmSub = Subscription::create([
            'firm_id'                => $firm->id,
            'tenant_id'              => null,
            'plan_id'                => $firmPlan->id,
            'status'                 => 'active',
            'interval'               => 'lifetime', // demo, never expires
            'gateway'                => 'system',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => null,
        ]);

        $firm->update(['firm_subscription_id' => $firmSub->id]);

        // 2. Firm-owner user.
        $owner = User::create([
            'name'                     => 'Test Accountant',
            'email'                    => self::FIRM_OWNER_EMAIL,
            'password'                 => Hash::make(self::PASSWORD),
            'firm_id'                  => $firm->id,
            'firm_role'                => 'owner',
            'privacy_accepted_at'      => now(),
            'privacy_accepted_version' => config('privacy.current_version'),
        ]);

        if (Role::where('name', 'firm-owner')->where('guard_name', 'web')->exists()) {
            $owner->assignRole('firm-owner');
        }

        $firm->update(['owner_user_id' => $owner->id]);

        // 3. For each client: tenant + admin user + SME subscription + COA + a couple of invoices/bills + FirmClient link.
        foreach (self::CLIENTS as $client) {
            $tenantId = $this->generateUniqueTenantId($client['display']);
            $tenant = $this->createTenantWithDatabase($tenantId);

            $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
            $smeUser = User::create([
                'name'                     => $client['display'],
                'email'                    => $client['email'],
                'password'                 => Hash::make(self::PASSWORD),
                'tenant_id'                => $tenantId,
                'role_id'                  => $adminRole?->id,
                'privacy_accepted_at'      => now(),
                'privacy_accepted_version' => config('privacy.current_version'),
            ]);
            if ($adminRole) {
                $smeUser->assignRole('admin');
            }

            // Each SME has its OWN subscription on the Startup (free) tier.
            // The firm doesn't pay for the SME's books — the SME does.
            // See the Q&A on this in the billing-model docs.
            Subscription::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'plan_id'                => $startupSmePlan->id,
                    'status'                 => 'active',
                    'interval'               => 'lifetime',
                    'gateway'                => 'system',
                    'current_period_start'   => now()->toDateString(),
                    'current_period_ends_at' => null,
                ]
            );

            // Populate the tenant's books (so the practice dashboard
            // shows real revenue / AR numbers, not zeros).
            tenancy()->initialize($tenant);
            try {
                $this->seedChartOfAccounts();
                $customer  = $this->seedCustomer($client['display']);
                $supplier  = $this->seedSupplier();
                $this->seedInvoices($smeUser->id, $customer, $client['invoices']);
                $this->seedBill($smeUser->id, $supplier, $client['bill']);
            } finally {
                tenancy()->end();
            }

            // Link the tenant to the firm.
            FirmClient::create([
                'firm_id'           => $firm->id,
                'tenant_id'         => $tenant->id,
                'permission_level'  => 'admin',
                'status'            => 'active',
                'linked_at'         => now(),
                'linked_by_user_id' => $owner->id,
            ]);

            $this->command?->info("  • {$client['display']} ({$tenantId}) provisioned and linked.");
        }

        $this->ensureSalesDemoLinked($owner);

        $this->command?->info(sprintf(
            'Provisioned firm "%s" with 2 clients. Login: %s / %s',
            self::FIRM_NAME,
            self::FIRM_OWNER_EMAIL,
            self::PASSWORD
        ));
    }

    /**
     * The sales demo tenant (testdemo@…) is seeded separately. Link it
     * so Practice AR includes INV-DEMO-* / late-fee / SO invoices.
     */
    private function ensureSalesDemoLinked(User $owner): void
    {
        $firm = $owner->firm_id
            ? Firm::find($owner->firm_id)
            : Firm::where('owner_user_id', $owner->id)->first();
        $demo = User::where('email', 'testdemo@bukucloud.com')->first();
        if (! $firm || ! $demo?->tenant_id) {
            return;
        }
        if (FirmClient::where('tenant_id', $demo->tenant_id)->exists()) {
            return;
        }

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $demo->tenant_id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $owner->id,
        ]);

        $this->command?->info('Linked sales demo tenant '.$demo->tenant_id.' to firm '.$firm->name.'.');
    }

    private function seedChartOfAccounts(): void
    {
        foreach (DefaultChartOfAccounts::rows() as $row) {
            Account::firstOrCreate(['code' => $row['code']], array_merge($row, ['is_active' => true]));
        }
    }

    private function seedCustomer(string $tenantDisplay): Customer
    {
        return Customer::create([
            'name'           => $tenantDisplay . " — Main Customer",
            'code'           => 'CUST-' . random_int(1000, 9999),
            'email'          => 'finance@' . Str::slug($tenantDisplay, '') . '.my',
            'phone'          => '+60 3-2200 1100',
            'tin'            => 'C2000' . random_int(1000000, 9999999),
            'currency'       => 'MYR',
            'payment_terms'  => 30,
            'is_active'      => true,
            'credit_limit'   => 50_000,
            'credit_hold'    => false,
            'send_statement' => true,
        ]);
    }

    private function seedSupplier(): Supplier
    {
        return Supplier::create([
            'name'          => 'Office Essentials Sdn Bhd',
            'code'          => 'SUP-' . random_int(1000, 9999),
            'email'         => 'sales@office-essentials.my',
            'phone'         => '+60 3-7800 2100',
            'tin'           => 'C3000' . random_int(1000000, 9999999),
            'currency'      => 'MYR',
            'payment_terms' => 30,
            'is_active'     => true,
        ]);
    }

    private function seedInvoices(int $userId, Customer $customer, array $invoiceSpecs): void
    {
        $service = app(InvoiceService::class);

        foreach ($invoiceSpecs as $idx => $spec) {
            $issueDate = now()->subDays(($idx + 1) * 5)->toDateString();
            $dueDate   = now()->subDays(($idx + 1) * 5)->addDays(30)->toDateString();
            $items = [[
                'description'         => 'Demo consulting services',
                'quantity'            => 1,
                'unit_price'          => $spec['amount'],
                'tax_rate'            => 0,
                'discount_amount'     => 0,
                'item_classification' => '022', // LHDN service classification
            ]];

            $invoice = $service->create([
                'invoice_number' => sprintf('INV-DEMO-%04d', random_int(1000, 9999)),
                'msic_code'      => '70200',
                'customer_id'    => $customer->id,
                'issue_date'     => $issueDate,
                'due_date'       => $dueDate,
                'currency'       => 'MYR',
                'shipping_amount'=> 0,
                'created_by'     => $userId,
            ], $items);

            $service->post($invoice);

            if ($spec['paid']) {
                $service->recordPayment(
                    $invoice->fresh(),
                    (float) $invoice->fresh()->total_amount,
                    now()->toDateString(),
                    self::BANK_CODE,
                );
            }
        }
    }

    private function seedBill(int $userId, Supplier $supplier, float $amount): void
    {
        $service = app(BillService::class);

        $items = [[
            'account_code' => '5000',
            'description'  => 'Office supplies',
            'quantity'     => 1,
            'unit_amount'  => $amount,
            'amount'       => $amount,
        ]];

        $bill = $service->create([
            'bill_number' => sprintf('BILL-DEMO-%04d', random_int(1000, 9999)),
            'supplier_id' => $supplier->id,
            'bill_date'   => now()->subDays(7)->toDateString(),
            'due_date'    => now()->addDays(23)->toDateString(),
            'tax_amount'  => 0,
            'created_by'  => $userId,
        ], $items);
        $service->post($bill);
        // Leave unpaid so the practice dashboard shows AP > 0.
    }

    private function generateUniqueTenantId(string $display): string
    {
        do {
            $id = Str::slug($display) . '_' . random_int(100, 999);
        } while (Tenant::where('id', $id)->exists());
        return $id;
    }
}
