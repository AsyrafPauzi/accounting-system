<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ArDepositService;
use App\Services\CreditNoteService;
use App\Services\DebitNoteService;
use App\Services\DeliveryOrderService;
use App\Services\InvoiceService;
use App\Services\SalesOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Extra click-through data on testdemo@bukucloud.com for Sales parity screens:
 * overdue invoice, leftover credit, debit note, SO/DO, unapplied deposit.
 *
 *   php artisan db:seed --class=DemoSalesParitySeeder
 */
class DemoSalesParitySeeder extends Seeder
{
    private const DEMO_EMAIL = 'testdemo@bukucloud.com';
    private const BANK_CODE = '1200';

    public function run(): void
    {
        $user = User::where('email', self::DEMO_EMAIL)->first();
        if (! $user) {
            $this->command?->warn(self::DEMO_EMAIL.' not found — run DemoTestAccountSeeder first.');

            return;
        }

        $tenant = Tenant::find($user->tenant_id);
        if (! $tenant) {
            $this->command?->warn('Tenant '.$user->tenant_id.' not found.');

            return;
        }

        $tenant->fill([
            'legal_name'       => 'BukuCloud Demo Sdn Bhd',
            'display_name'     => 'BukuCloud Demo',
            'tin'              => 'C25876543210',
            'brn'              => '202001012345',
            'msic_code'        => '70200',
            'sst_number'       => 'W10-1234-56789012',
            'country'          => 'Malaysia',
            'street'           => '12 Jalan Ampang',
            'city'             => 'Kuala Lumpur',
            'state'            => 'WP Kuala Lumpur',
            'postcode'         => '50450',
            'phone'            => '+60 3-2100 1000',
            'email'            => self::DEMO_EMAIL,
            'base_currency'    => 'MYR',
            'late_fee_percent' => 1.5,
        ])->save();

        tenancy()->initialize($tenant);
        try {
            if (! Schema::hasTable('sales_orders') || ! Schema::hasTable('ar_deposits')) {
                $this->command?->warn('Sales parity migrations missing. Run php artisan tenants:migrate first.');

                return;
            }

            $customer = Customer::query()->orderBy('id')->first();
            if (! $customer) {
                $this->command?->warn('No customers in demo tenant.');

                return;
            }

            $this->seedOverdueInvoice($user->id, $customer);
            $this->seedOpenInvoice($user->id, $customer);
            $this->seedOpenCredit($customer);
            $this->seedDebitNote($customer);
            $this->seedSalesOrderAndDelivery($user->id, $customer);
            $this->seedUnappliedDeposit($user->id, $customer);
            $this->enableRecurringAutoPost();
        } finally {
            tenancy()->end();
        }

        $this->command?->info('Sales parity dummy data added for '.self::DEMO_EMAIL);
    }

    private function seedOverdueInvoice(int $userId, Customer $customer): void
    {
        if (Invoice::where('invoice_number', 'INV-OVERDUE-001')->exists()) {
            return;
        }

        $service = app(InvoiceService::class);
        $invoice = $service->create([
            'invoice_number'  => 'INV-OVERDUE-001',
            'msic_code'       => '70200',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->subDays(45)->toDateString(),
            'due_date'        => now()->subDays(15)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
            'customer_notes'  => 'Overdue demo invoice — use Late fee on the Show page.',
            'created_by'      => $userId,
        ], [[
            'description'         => 'Overdue consulting — Jan cycle',
            'quantity'            => 1,
            'unit_price'          => 800,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $service->post($invoice);
    }

    private function seedOpenInvoice(int $userId, Customer $customer): void
    {
        if (Invoice::where('invoice_number', 'INV-OPEN-001')->exists()) {
            return;
        }

        $service = app(InvoiceService::class);
        $invoice = $service->create([
            'invoice_number'  => 'INV-OPEN-001',
            'msic_code'       => '70200',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->subDays(8)->toDateString(),
            'due_date'        => now()->addDays(22)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
            'customer_notes'  => 'Open demo invoice — allocate a receipt against this and INV-OVERDUE-001.',
            'created_by'      => $userId,
        ], [[
            'description'         => 'Open AR for knock-off demo',
            'quantity'            => 1,
            'unit_price'          => 450,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $service->post($invoice);
    }

    private function seedOpenCredit(Customer $customer): void
    {
        if (! Schema::hasTable('credit_note_items')) {
            return;
        }
        if (\App\Models\CreditNote::where('cn_number', 'CN-OPEN-0001')->exists()) {
            return;
        }

        app(CreditNoteService::class)->issue([
            'customer_id'        => $customer->id,
            'cn_number'          => 'CN-OPEN-0001',
            'issue_date'         => now()->subDays(2)->toDateString(),
            'reason_code'        => '03',
            'reason_description' => 'Unapplied goodwill credit — refund or knock off from the credit-note Show page.',
            'currency'           => 'MYR',
        ], [[
            'description'         => 'Goodwill credit (unapplied)',
            'quantity'            => 1,
            'unit_price'          => 200,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
    }

    private function seedDebitNote(Customer $customer): void
    {
        if (! Schema::hasTable('debit_notes')) {
            return;
        }
        if (\App\Models\DebitNote::where('dn_number', 'DN-DEMO-0001')->exists()) {
            return;
        }

        $invoice = Invoice::where('invoice_number', 'INV-OPEN-001')->first();

        app(DebitNoteService::class)->issue([
            'customer_id'        => $customer->id,
            'invoice_id'         => $invoice?->id,
            'dn_number'          => 'DN-DEMO-0001',
            'issue_date'         => now()->subDays(1)->toDateString(),
            'reason_code'        => '01',
            'reason_description' => 'Additional charge after invoice.',
            'currency'           => 'MYR',
        ], [[
            'description'         => 'Freight undercharged',
            'quantity'            => 1,
            'unit_price'          => 50,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
    }

    private function seedSalesOrderAndDelivery(int $userId, Customer $customer): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }
        if (SalesOrder::where('so_number', 'SO-DEMO-0001')->exists()) {
            return;
        }

        $product = Product::query()->orderBy('id')->first();
        $so = app(SalesOrderService::class)->create([
            'so_number'       => 'SO-DEMO-0001',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->subDays(3)->toDateString(),
            'expected_date'   => now()->addDays(4)->toDateString(),
            'currency'        => 'MYR',
            'customer_notes'  => 'Demo sales order — create another DO or convert to invoice.',
            'created_by'      => $userId,
        ], [[
            'product_id'          => $product?->id,
            'description'         => $product?->name ?? 'Demo goods',
            'quantity'            => 10,
            'unit_price'          => 65,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $qtys = [];
        foreach ($so->items as $item) {
            $qtys[$item->id] = 4;
        }
        app(DeliveryOrderService::class)->fromSalesOrder($so, $qtys, $userId);
    }

    private function seedUnappliedDeposit(int $userId, Customer $customer): void
    {
        if (! Schema::hasTable('ar_deposits')) {
            return;
        }
        if (\App\Models\ArDeposit::where('reference', 'DEMO-RECEIPT-001')->exists()) {
            return;
        }

        app(ArDepositService::class)->receive([
            'customer_id'       => $customer->id,
            'amount'            => 300,
            'payment_date'      => now()->toDateString(),
            'bank_account_code' => self::BANK_CODE,
            'reference'         => 'DEMO-RECEIPT-001',
            'notes'             => 'Unapplied deposit — apply from Receipts & deposits or invoice Show.',
            'created_by'        => $userId,
        ]);
    }

    private function enableRecurringAutoPost(): void
    {
        if (! Schema::hasColumn('recurring_invoices', 'auto_post')) {
            return;
        }

        RecurringInvoice::query()->orderBy('id')->limit(1)->update([
            'auto_post'  => true,
            'auto_email' => true,
        ]);
    }
}
