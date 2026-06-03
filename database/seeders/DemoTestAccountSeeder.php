<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Customer;
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
 * testdemo@bukucloud.com — populated demo tenant for sales / training / QA.
 *
 * What this seeder produces in the tenant's private database:
 *   • Standard chart of accounts (assets, liabilities, equity, income, expense)
 *     with explicit Bank (1200) so receipts and payments have a destination.
 *   • 5 customers, all linked to invoices below.
 *   • 4 suppliers, all linked to bills below.
 *   • 8 invoices (RM 500–3,000): 6 paid (status=paid, journal entries posted,
 *     payment recorded against bank), 2 unpaid (status=unpaid, journal
 *     entries posted but no payment yet).
 *   • 10 bills (RM 200–1,000): 6 paid, 4 unpaid — same posting pattern.
 *
 * Run on its own:
 *   php artisan db:seed --class=DemoTestAccountSeeder
 *
 * Idempotent: if the user already exists, the seeder is a no-op so the demo
 * dataset isn't duplicated on every fresh deploy.
 */
class DemoTestAccountSeeder extends Seeder
{
    /**
     * WithoutModelEvents suppresses Stancl Tenancy's TenantCreated event
     * (which would otherwise auto-create the tenant database). We then
     * create it explicitly via ProvisionsTenantDatabase so the seeder
     * works the same whether invoked via `db:seed` (runs through the
     * parent DatabaseSeeder which already applies this trait) or via
     * `db:seed --class=DemoTestAccountSeeder` directly.
     */
    use WithoutModelEvents;
    use ProvisionsTenantDatabase;

    private const EMAIL = 'testdemo@bukucloud.com';
    private const PASSWORD = 'Password123!';
    private const TENANT_DISPLAY_NAME = 'BukuCloud Demo';
    private const PLAN_SLUG = 'corporate';

    /** Bank account code used to receive customer payments + pay suppliers. */
    private const BANK_CODE = '1200';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command->info(self::EMAIL . ' already exists, skipping demo seed (idempotent).');
            return;
        }

        $plan = Plan::where('slug', self::PLAN_SLUG)->first();
        if (! $plan) {
            $this->command->error('Plan ' . self::PLAN_SLUG . ' not found. Run PlanSeeder first.');
            return;
        }

        $companyId = $this->generateUniqueTenantId(self::TENANT_DISPLAY_NAME);
        $tenant = $this->createTenantWithDatabase($companyId);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        $user = User::create([
            'name'      => self::TENANT_DISPLAY_NAME,
            'email'     => self::EMAIL,
            'password'  => Hash::make(self::PASSWORD),
            'tenant_id' => $companyId,
            'role_id'   => $adminRole?->id,
        ]);

        if ($adminRole) {
            $user->assignRole('admin');
        }

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id'                => $plan->id,
                'status'                 => 'active',
                'interval'               => 'lifetime',
                'current_period_start'   => now(),
                'current_period_ends_at' => null,
                'gateway'                => 'system',
            ]
        );

        // Switch into the tenant DB to seed customers, suppliers, invoices, bills.
        // If anything throws here, drop the half-seeded tenant + its DB so a
        // retry of the seeder gets a clean slate (no orphan tenant row,
        // no orphan MySQL database).
        tenancy()->initialize($tenant);
        try {
            $this->seedChartOfAccounts();
            $customers = $this->seedCustomers();
            $suppliers = $this->seedSuppliers();
            $this->seedInvoices($user->id, $customers);
            $this->seedBills($user->id, $suppliers);
        } catch (\Throwable $e) {
            tenancy()->end();
            $user->delete();
            try {
                $tenant->database()->manager()->deleteDatabase($tenant);
            } catch (\Throwable $_) {
                // Ignore — DB may not exist or already gone.
            }
            \Illuminate\Support\Facades\DB::table('tenants')->where('id', $tenant->id)->delete();
            throw $e;
        } finally {
            tenancy()->end();
        }

        $this->command->info(sprintf(
            'Provisioned %s (tenant %s). Login password: %s',
            self::EMAIL,
            $companyId,
            self::PASSWORD
        ));
    }

    /**
     * Seed the standard chart of accounts.
     *
     * In normal flow this is already populated by the tenant migration
     * `..._seed_default_chart_of_accounts.php`. We still call firstOrCreate
     * here so the seeder stays authoritative even if someone runs it
     * against an out-of-band tenant where the migration hasn't fired.
     */
    private function seedChartOfAccounts(): void
    {
        foreach (DefaultChartOfAccounts::rows() as $row) {
            Account::firstOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['is_active' => true])
            );
        }
    }

    /**
     * 5 customers, each linked to multiple invoices below.
     */
    private function seedCustomers(): array
    {
        $rows = [
            ['name' => 'Tropika Sdn Bhd',          'code' => 'CUST-1001', 'email' => 'finance@tropika.my',        'phone' => '+60 3-2200 1100', 'tin' => 'C20001234567', 'segment' => 'wholesale', 'region' => 'KL'],
            ['name' => 'Cendol Cafe Group',        'code' => 'CUST-1002', 'email' => 'ap@cendolcafe.my',          'phone' => '+60 3-2200 1200', 'tin' => 'C20002345678', 'segment' => 'F&B',       'region' => 'Selangor'],
            ['name' => 'Pulau Pinang Mart',        'code' => 'CUST-1003', 'email' => 'orders@ppm.my',             'phone' => '+60 4-2200 1300', 'tin' => 'C20003456789', 'segment' => 'retail',    'region' => 'Penang'],
            ['name' => 'Johor Tech Solutions',     'code' => 'CUST-1004', 'email' => 'billing@johortech.my',      'phone' => '+60 7-2200 1400', 'tin' => 'C20004567890', 'segment' => 'IT',        'region' => 'Johor'],
            ['name' => 'Kinabalu Trading Co.',     'code' => 'CUST-1005', 'email' => 'admin@kinabalu-trading.my', 'phone' => '+60 88-220 1500', 'tin' => 'C20005678901', 'segment' => 'wholesale', 'region' => 'Sabah'],
        ];

        $created = [];
        foreach ($rows as $row) {
            $created[] = Customer::create(array_merge($row, [
                'currency'       => 'MYR',
                'payment_terms'  => 30,
                'is_active'      => true,
                'credit_limit'   => 50000,
                'credit_hold'    => false,
                'send_statement' => true,
            ]));
        }

        return $created;
    }

    /**
     * 4 suppliers, each linked to multiple bills below.
     */
    private function seedSuppliers(): array
    {
        $rows = [
            ['name' => 'Mega Office Supplies',    'code' => 'SUP-2001', 'email' => 'sales@megaoffice.my',  'phone' => '+60 3-7800 2100', 'tin' => 'C30001234567', 'segment' => 'office',    'region' => 'KL'],
            ['name' => 'Klang Logistics Sdn Bhd', 'code' => 'SUP-2002', 'email' => 'ar@klanglogistics.my', 'phone' => '+60 3-7800 2200', 'tin' => 'C30002345678', 'segment' => 'logistics', 'region' => 'Selangor'],
            ['name' => 'Hijau Energy Sdn Bhd',    'code' => 'SUP-2003', 'email' => 'billing@hijau.my',     'phone' => '+60 3-7800 2300', 'tin' => 'C30003456789', 'segment' => 'utility',   'region' => 'KL'],
            ['name' => 'Bumi Construction Pte',   'code' => 'SUP-2004', 'email' => 'ap@bumicon.my',        'phone' => '+60 3-7800 2400', 'tin' => 'C30004567890', 'segment' => 'services',  'region' => 'KL'],
        ];

        $created = [];
        foreach ($rows as $row) {
            $created[] = Supplier::create(array_merge($row, [
                'currency'       => 'MYR',
                'payment_terms'  => 30,
                'is_active'      => true,
            ]));
        }

        return $created;
    }

    /**
     * 8 invoices, RM 500–3,000. 6 paid, 2 unpaid. Linked across the 5 customers.
     *
     * Each invoice is created via InvoiceService::create (draft) → post()
     * (status=unpaid, AR debit + Revenue credit). Paid invoices then have
     * recordPayment() called against the bank account so the GL is consistent
     * (Bank DR / AR CR).
     */
    private function seedInvoices(int $userId, array $customers): void
    {
        $service = app(InvoiceService::class);

        // [customer_index, amount_before_tax, paid?, days_ago_issued]
        $plan = [
            [0,  900.00, true,  35],   // Tropika
            [1, 1450.00, true,  28],   // Cendol Cafe
            [2,  680.00, true,  21],   // PPM
            [3, 2750.00, true,  18],   // Johor Tech
            [4, 1200.00, true,  14],   // Kinabalu
            [0, 2100.00, true,  10],   // Tropika (second invoice)
            [1,  525.00, false,  7],   // Cendol Cafe (unpaid)
            [3, 2950.00, false,  3],   // Johor Tech (unpaid)
        ];

        foreach ($plan as $idx => [$ci, $beforeTax, $paid, $daysAgo]) {
            $customer = $customers[$ci];
            $issueDate = now()->subDays($daysAgo)->toDateString();
            $dueDate = now()->subDays($daysAgo)->addDays(30)->toDateString();

            $invoiceNumber = sprintf('INV-DEMO-%04d', $idx + 1);

            $items = [[
                'description'         => 'Consulting & advisory services — ' . now()->subDays($daysAgo)->format('M Y'),
                'quantity'            => 1,
                'unit_price'          => $beforeTax,
                'tax_rate'            => 8,        // SST 8%
                'discount_amount'     => 0,
                'item_classification' => '022',    // LHDN service classification
            ]];

            $invoice = $service->create([
                'invoice_number'   => $invoiceNumber,
                'msic_code'        => '70200',
                'customer_id'      => $customer->id,
                'issue_date'       => $issueDate,
                'due_date'         => $dueDate,
                'currency'         => 'MYR',
                'shipping_amount'  => 0,
                'customer_notes'   => 'Demo data — generated by DemoTestAccountSeeder.',
                'created_by'       => $userId,
            ], $items);

            $service->post($invoice);

            if ($paid) {
                // Record full payment against bank — same flow as a user
                // clicking "Record payment" in the UI.
                $service->recordPayment(
                    $invoice->fresh(),
                    (float) $invoice->fresh()->total_amount,
                    now()->subDays(max(0, $daysAgo - 5))->toDateString(),
                    self::BANK_CODE,
                );
            }
        }
    }

    /**
     * 10 bills, RM 200–1,000. 6 paid, 4 unpaid. Linked across the 4 suppliers.
     *
     * Same lifecycle as invoices: BillService::create (draft) → post()
     * (Expense DR + Tax DR + AP CR), then for paid bills a recordPayment
     * against bank (AP DR + Bank CR).
     */
    private function seedBills(int $userId, array $suppliers): void
    {
        $service = app(BillService::class);

        // [supplier_index, total_amount, tax_amount, paid?, days_ago, account_code, description]
        $plan = [
            [0,  340.00,  25.19, true,  40, '5000', 'Office stationery & printer toner'],
            [1,  720.00,  53.33, true,  35, '5000', 'Courier service — March'],
            [2,  450.00,  33.33, true,  30, '5000', 'Electricity — utilities'],
            [3,  980.00,  72.59, true,  25, '5000', 'Air-cond servicing'],
            [0,  210.00,  15.56, true,  20, '5000', 'Office supplies — restock'],
            [1,  640.00,  47.41, true,  15, '5000', 'Pickup & delivery — last mile'],
            [2,  390.00,  28.89, false, 10, '5000', 'Internet & telco — current cycle'],
            [3,  870.00,  64.44, false,  7, '5000', 'Site maintenance contract'],
            [0,  295.00,  21.85, false,  4, '5000', 'Toner cartridges & paper'],
            [1,  555.00,  41.11, false,  2, '5000', 'Logistics — recent shipment'],
        ];

        foreach ($plan as $idx => [$si, $total, $tax, $paid, $daysAgo, $accountCode, $description]) {
            $supplier = $suppliers[$si];
            $billDate = now()->subDays($daysAgo)->toDateString();
            $dueDate = now()->subDays($daysAgo)->addDays(30)->toDateString();
            $billNumber = sprintf('BILL-DEMO-%04d', $idx + 1);

            // BillService computes total = subtotal(items) + taxAmount, so
            // the items' amount must equal (total - tax) for the math to land
            // on $total exactly. This keeps the visible numbers tidy.
            $netAmount = round($total - $tax, 2);

            $items = [[
                'account_code' => $accountCode,
                'description'  => $description,
                'quantity'     => 1,
                'unit_amount'  => $netAmount,
                'amount'       => $netAmount,
            ]];

            $bill = $service->create([
                'bill_number'   => $billNumber,
                'supplier_id'   => $supplier->id,
                'bill_date'     => $billDate,
                'due_date'      => $dueDate,
                'tax_amount'    => $tax,
                'reference'     => sprintf('PO-%05d', 10000 + $idx),
                'private_notes' => 'Demo data — generated by DemoTestAccountSeeder.',
                'created_by'    => $userId,
            ], $items);

            $service->post($bill);

            if ($paid) {
                $service->recordPayment(
                    $bill->fresh(),
                    (float) $bill->fresh()->total_amount,
                    now()->subDays(max(0, $daysAgo - 3))->toDateString(),
                    self::BANK_CODE,
                );
            }
        }
    }

    private function generateUniqueTenantId(string $tenantDisplayName): string
    {
        do {
            $id = Str::slug($tenantDisplayName) . '_' . random_int(100, 999);
        } while (Tenant::where('id', $id)->exists());

        return $id;
    }
}
