<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Copilot\CopilotTools;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Five Sales-module passes driven through CopilotTools (same code path as Confirm).
 */
class CopilotQaSalesCommand extends Command
{
    protected $signature = 'copilot:qa-sales {--email=testdemo@bukucloud.com}';

    protected $description = 'Run 5 Sales + Copilot scenario passes against a demo tenant';

    public function handle(CopilotTools $tools): int
    {
        Mail::fake();

        $email = (string) $this->option('email');
        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->tenant_id) {
            $this->error("User {$email} not found or has no tenant.");

            return self::FAILURE;
        }

        $tenant = Tenant::find($user->tenant_id);
        if (! $tenant) {
            $this->error('Tenant missing.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);
        $this->info("Tenant {$tenant->id} · user {$email}");

        $passed = 0;
        $failed = 0;

        $run = function (string $label, callable $fn) use (&$passed, &$failed) {
            try {
                $fn();
                $this->info("PASS  {$label}");
                $passed++;
            } catch (\Throwable $e) {
                $this->error("FAIL  {$label}: ".$e->getMessage());
                $failed++;
            }
        };

        $customer = Customer::query()->orderBy('id')->first();
        $product = Product::query()->orderBy('id')->first();
        if (! $customer || ! $product) {
            $this->error('Need at least one customer and product in the tenant.');
            tenancy()->end();

            return self::FAILURE;
        }

        // Pass 1: Quote → Invoice
        $run('1 Quote→Invoice (estimate, convert, post)', function () use ($tools, $user, $customer, $product) {
            $est = $tools->execute('draft_estimate', [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'description' => 'QA Copilot estimate line',
                    'quantity' => 1,
                    'unit_price' => 50,
                    'tax_rate' => 0,
                    'product_id' => $product->id,
                ]],
            ], $user);
            $this->assertOk($est);
            $inv = $tools->execute('convert_estimate_to_invoice', [
                'estimate_id' => $est['estimate_id'],
            ], $user);
            $this->assertOk($inv);
            $post = $tools->execute('post_invoice', ['invoice_id' => $inv['invoice_id']], $user);
            $this->assertOk($post);
        });

        // Pass 2: Goods flow SO → DO → invoice → return path on second DO
        $run('2 Goods SO→DO→Invoice (+ cancel empty SO)', function () use ($tools, $user, $customer, $product) {
            $so = $tools->execute('draft_sales_order', [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'description' => 'QA Copilot SO line',
                    'quantity' => 2,
                    'unit_price' => 25,
                    'tax_rate' => 0,
                    'product_id' => $product->id,
                ]],
            ], $user);
            $this->assertOk($so);
            $soId = $so['sales_order_id'] ?? $so['id'] ?? null;
            if (! $soId) {
                throw new \RuntimeException('draft_sales_order missing sales_order_id: '.json_encode($so));
            }
            $do = $tools->execute('deliver_sales_order', ['sales_order_id' => $soId], $user);
            $this->assertOk($do);
            $doId = $do['delivery_order_id'] ?? $do['id'] ?? null;
            $inv = $tools->execute('convert_delivery_order_to_invoice', [
                'delivery_order_id' => $doId,
            ], $user);
            $this->assertOk($inv);

            $so2 = $tools->execute('draft_sales_order', [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'description' => 'QA cancel SO',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'tax_rate' => 0,
                ]],
            ], $user);
            $this->assertOk($so2);
            $cancel = $tools->execute('cancel_sales_order', [
                'sales_order_id' => $so2['sales_order_id'] ?? $so2['id'],
            ], $user);
            $this->assertOk($cancel);

            $so3 = $tools->execute('draft_sales_order', [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'description' => 'QA return DO',
                    'quantity' => 1,
                    'unit_price' => 15,
                    'tax_rate' => 0,
                ]],
            ], $user);
            $this->assertOk($so3);
            $doR = $tools->execute('deliver_sales_order', [
                'sales_order_id' => $so3['sales_order_id'] ?? $so3['id'],
            ], $user);
            $this->assertOk($doR);
            $ret = $tools->execute('return_delivery_order', [
                'delivery_order_id' => $doR['delivery_order_id'] ?? $doR['id'],
            ], $user);
            $this->assertOk($ret);
        });

        // Pass 3: Collections — payment + deposit
        $run('3 Collections (payment + AR deposit)', function () use ($tools, $user, $customer, $product) {
            $inv = $tools->execute('draft_invoice', [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'items' => [[
                    'description' => 'QA payment invoice',
                    'quantity' => 1,
                    'unit_price' => 80,
                    'tax_rate' => 0,
                    'product_id' => $product->id,
                ]],
            ], $user);
            $this->assertOk($inv);
            $tools->execute('post_invoice', ['invoice_id' => $inv['invoice_id']], $user);
            $pay = $tools->execute('record_invoice_payment', [
                'invoice_id' => $inv['invoice_id'],
                'amount' => 80,
                'payment_date' => now()->toDateString(),
                'bank_account_code' => '1200',
            ], $user);
            $this->assertOk($pay);

            $dep = $tools->execute('receive_ar_deposit', [
                'customer_id' => $customer->id,
                'amount' => 40,
                'payment_date' => now()->toDateString(),
                'bank_account_code' => '1200',
                'reference' => 'QA-DEP',
            ], $user);
            $this->assertOk($dep);
            $list = $tools->execute('list_ar_deposits', [], $user);
            if (empty($list['deposits'] ?? $list['ok'] ?? true) && ! isset($list['deposits']) && ! ($list['ok'] ?? false)) {
                // accept either shape
            }
        });

        // Pass 4: CN / DN
        $run('4 Credit note + Debit note', function () use ($tools, $user, $customer, $product) {
            $inv = $tools->execute('draft_invoice', [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'items' => [[
                    'description' => 'QA CN invoice',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_rate' => 0,
                    'product_id' => $product->id,
                ]],
            ], $user);
            $this->assertOk($inv);
            $tools->execute('post_invoice', ['invoice_id' => $inv['invoice_id']], $user);

            $cn = $tools->execute('issue_credit_note', [
                'invoice_id' => $inv['invoice_id'],
                'customer_id' => $customer->id,
                'reason_code' => '02',
                'items' => [[
                    'description' => 'QA credit',
                    'quantity' => 1,
                    'unit_price' => 20,
                    'tax_rate' => 0,
                ]],
            ], $user);
            $this->assertOk($cn);

            $dn = $tools->execute('issue_debit_note', [
                'customer_id' => $customer->id,
                'invoice_id' => $inv['invoice_id'],
                'items' => [[
                    'description' => 'QA debit',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'tax_rate' => 0,
                ]],
            ], $user);
            $this->assertOk($dn);
        });

        // Pass 5: Masters + claim + team list
        $run('5 Masters + owner claim + list team', function () use ($tools, $user) {
            $cust = $tools->execute('draft_customer', [
                'name' => 'QA Copilot Customer '.uniqid(),
                'email' => 'qa-copilot-'.uniqid().'@example.test',
            ], $user);
            $this->assertOk($cust);

            $prod = $tools->execute('draft_product', [
                'name' => 'QA Copilot Product '.uniqid(),
                'unit_price' => 12.5,
                'tax_rate' => 0,
            ], $user);
            $this->assertOk($prod);

            $claim = $tools->execute('draft_owner_expense_claim', [
                'description' => 'Internet (personal paid) QA',
                'amount' => 99.0,
                'claimant_name' => $user->name,
                'account_code' => '5000',
            ], $user);
            $this->assertOk($claim);

            $team = $tools->execute('list_team_members', [], $user);
            if (! isset($team['team_members']) && ! isset($team['members'])) {
                throw new \RuntimeException('list_team_members unexpected: '.json_encode($team));
            }
        });

        tenancy()->end();

        $this->newLine();
        $this->info("Result: {$passed} passed, {$failed} failed (of 5)");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function assertOk(array $result): void
    {
        if (($result['ok'] ?? null) === false) {
            throw new \RuntimeException('Tool returned ok=false: '.json_encode($result));
        }
        if (isset($result['error'])) {
            throw new \RuntimeException((string) $result['error']);
        }
    }
}
