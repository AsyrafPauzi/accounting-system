<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Models\RecurringBill;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApDepositService;
use App\Services\BillService;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\RecurringBillService;
use App\Services\SupplierCreditNoteService;
use App\Services\SupplierDebitNoteService;
use App\Services\SupplierStatementService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Ten consecutive Expenses (Purchases) CRUD passes against a demo tenant.
 */
class PurchasesQaCrudCommand extends Command
{
    protected $signature = 'purchases:qa-crud {--email=testdemo@bukucloud.com} {--passes=10}';

    protected $description = 'Run N consecutive Expenses CRUD passes against a demo tenant';

    public function handle(
        PurchaseOrderService $orders,
        GoodsReceiptService $receipts,
        BillService $bills,
        RecurringBillService $recurring,
        SupplierCreditNoteService $creditNotes,
        SupplierDebitNoteService $debitNotes,
        ApDepositService $deposits,
        SupplierStatementService $statements,
    ): int {
        $email = (string) $this->option('email');
        $passes = max(1, (int) $this->option('passes'));

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
        $this->info("Tenant {$tenant->id} · {$email} · {$passes} passes");

        $supplier = Supplier::query()->orderBy('id')->first();
        $expense = Account::query()->where('type', 'expense')->active()->orderBy('code')->value('code') ?: '5000';
        $bank = Account::query()->where(function ($q) {
            $q->where('code', '1200')->orWhere('type', 'asset');
        })->orderBy('code')->value('code') ?: '1200';

        if (! $supplier) {
            $this->error('Need at least one supplier.');
            tenancy()->end();

            return self::FAILURE;
        }

        $passed = 0;
        $failed = 0;
        $failures = [];

        $run = function (string $label, callable $fn) use (&$passed, &$failed, &$failures) {
            try {
                $fn();
                $this->info("PASS  {$label}");
                $passed++;
            } catch (\Throwable $e) {
                $this->error("FAIL  {$label}: ".$e->getMessage());
                $failed++;
                $failures[] = $label.': '.$e->getMessage();
            }
        };

        for ($n = 1; $n <= $passes; $n++) {
            $this->newLine();
            $this->comment("--- Pass {$n}/{$passes} ---");
            $tag = 'QA'.$n.'-'.now()->format('His');

            $run("{$n}.1 Supplier C/U/R", function () use ($supplier, $tag) {
                $created = Supplier::create([
                    'name' => "QA Soak {$tag}",
                    'code' => substr('Q'.preg_replace('/\D/', '', $tag).random_int(10, 99), 0, 20),
                    'payment_terms' => 30,
                    'currency' => 'MYR',
                    'is_active' => true,
                ]);
                $created->update(['internal_notes' => 'updated '.$tag]);
                $fresh = Supplier::query()->findOrFail($created->id);
                if ($fresh->internal_notes !== 'updated '.$tag) {
                    throw new \RuntimeException('Supplier update did not persist.');
                }
                if (Supplier::query()->where('id', $supplier->id)->doesntExist()) {
                    throw new \RuntimeException('Existing supplier missing on read.');
                }
            });

            $run("{$n}.2 PO create + update + cancel", function () use ($orders, $supplier, $expense, $user, $tag) {
                $po = $orders->create([
                    'supplier_id' => $supplier->id,
                    'issue_date' => now()->toDateString(),
                    'notes' => $tag,
                    'created_by' => $user->id,
                ], [
                    ['description' => "{$tag} cancel-a", 'quantity' => 1, 'unit_price' => 11, 'tax_rate' => 0, 'account_code' => $expense],
                    ['description' => "{$tag} cancel-b", 'quantity' => 2, 'unit_price' => 4, 'tax_rate' => 0, 'account_code' => $expense],
                ]);
                if ($po->items->count() !== 2) {
                    throw new \RuntimeException('PO did not save two lines.');
                }
                $updated = $orders->update($po, [
                    'supplier_id' => $supplier->id,
                    'issue_date' => now()->toDateString(),
                    'notes' => $tag.' edited',
                ], $po->items->map(fn ($i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => 12,
                    'tax_rate' => 0,
                    'account_code' => $expense,
                ])->all());
                if ((float) $updated->items->first()->unit_price !== 12.0) {
                    throw new \RuntimeException('PO update did not change unit price.');
                }
                $orders->cancel($updated->fresh());
                if ($updated->fresh()->status !== 'cancelled') {
                    throw new \RuntimeException('PO cancel failed.');
                }
            });

            $run("{$n}.3 PO → GR → return", function () use ($orders, $receipts, $supplier, $expense, $user, $tag) {
                $po = $orders->create([
                    'supplier_id' => $supplier->id,
                    'issue_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['description' => "{$tag} receive", 'quantity' => 3, 'unit_price' => 9, 'tax_rate' => 0, 'account_code' => $expense],
                ]);
                $grn = $receipts->fromPurchaseOrder($po->fresh('items'), [], $user->id);
                $receipts->update($grn, ['notes' => $tag.' received']);
                if ($grn->fresh()->notes !== $tag.' received') {
                    throw new \RuntimeException('GR update failed.');
                }
                $receipts->returnFull($grn->fresh(['items', 'purchaseOrder.items', 'bills']));
                if ($grn->fresh()->status !== 'cancelled') {
                    throw new \RuntimeException('GR return failed.');
                }
                if ($po->fresh()->status === 'cancelled') {
                    throw new \RuntimeException('PO should be confirmed after GR return, not cancelled.');
                }
            });

            $run("{$n}.4 PO → GR → bill", function () use ($orders, $receipts, $bills, $supplier, $expense, $user, $tag) {
                $po = $orders->create([
                    'supplier_id' => $supplier->id,
                    'issue_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['description' => "{$tag} bill-from-gr", 'quantity' => 1, 'unit_price' => 40, 'tax_rate' => 0, 'account_code' => $expense],
                ]);
                $grn = $receipts->fromPurchaseOrder($po->fresh('items'), [], $user->id);
                $bill = $receipts->convertToBill($grn->fresh(['items', 'purchaseOrder.items']), $user->id);
                $bills->post($bill->fresh('items'));
                if ($bill->fresh()->status !== 'unpaid') {
                    throw new \RuntimeException('Converted bill did not post.');
                }
            });

            $run("{$n}.5 Credit bill C/U/post/pay/void", function () use ($bills, $supplier, $expense, $bank, $user, $tag) {
                $bill = $bills->create([
                    'supplier_id' => $supplier->id,
                    'purchase_kind' => 'credit',
                    'bill_date' => now()->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'reference' => $tag,
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} credit-1", 'quantity' => 1, 'unit_amount' => 15, 'amount' => 15],
                    ['account_code' => $expense, 'description' => "{$tag} credit-2", 'quantity' => 1, 'unit_amount' => 5, 'amount' => 5],
                ]);
                $bills->update($bill->fresh(), [
                    'bill_number' => $bill->bill_number,
                    'supplier_id' => $supplier->id,
                    'bill_date' => now()->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'reference' => $tag.'-u',
                    'private_notes' => 'edited',
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} credit-1", 'quantity' => 1, 'unit_amount' => 18, 'amount' => 18],
                    ['account_code' => $expense, 'description' => "{$tag} credit-2", 'quantity' => 1, 'unit_amount' => 5, 'amount' => 5],
                ]);
                $bill = $bill->fresh('items');
                if ((float) $bill->total_amount !== 23.0) {
                    throw new \RuntimeException('Bill update totals expected 23, got '.$bill->total_amount);
                }
                $bills->post($bill->fresh('items'));
                $bills->recordPayment($bill->fresh(), 23, now()->toDateString(), $bank, $tag, $user->id);
                if ($bill->fresh()->status !== 'paid') {
                    throw new \RuntimeException('Credit bill did not reach paid.');
                }
                $voidable = $bills->create([
                    'supplier_id' => $supplier->id,
                    'purchase_kind' => 'credit',
                    'bill_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} void-me", 'quantity' => 1, 'unit_amount' => 7, 'amount' => 7],
                ]);
                $bills->post($voidable->fresh('items'));
                $bills->void($voidable->fresh('items'));
                if ($voidable->fresh()->status !== 'void') {
                    throw new \RuntimeException('Bill void failed.');
                }
            });

            $run("{$n}.6 Cash purchase", function () use ($bills, $supplier, $expense, $bank, $user, $tag) {
                $bill = $bills->create([
                    'supplier_id' => $supplier->id,
                    'purchase_kind' => 'cash',
                    'bank_account_code' => $bank,
                    'bill_date' => now()->toDateString(),
                    'reference' => $tag.'-cash',
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} cash", 'quantity' => 1, 'unit_amount' => 19, 'amount' => 19],
                ]);
                if ($bill->fresh()->status !== 'paid') {
                    throw new \RuntimeException('Cash purchase should post and pay. Status: '.$bill->fresh()->status);
                }
            });

            $run("{$n}.7 Expense claim post + reimburse", function () use ($bills, $supplier, $expense, $bank, $user, $tag) {
                $bill = $bills->create([
                    'supplier_id' => $supplier->id,
                    'purchase_kind' => 'claim',
                    'bill_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} claim", 'quantity' => 1, 'unit_amount' => 13, 'amount' => 13],
                ]);
                $bills->post($bill->fresh('items'));
                $bills->recordPayment($bill->fresh(), 13, now()->toDateString(), $bank, $tag.'-reimburse', $user->id);
                if ($bill->fresh()->status !== 'paid') {
                    throw new \RuntimeException('Claim was not reimbursed.');
                }
            });

            $run("{$n}.8 Recurring bill create / run / pause", function () use ($recurring, $supplier, $expense, $user, $tag) {
                $template = $recurring->create([
                    'name' => "{$tag} rent",
                    'supplier_id' => $supplier->id,
                    'cadence' => 'monthly',
                    'interval' => 1,
                    'start_date' => now()->toDateString(),
                    'payment_terms_days' => 7,
                    'auto_post' => false,
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} rec-1", 'quantity' => 1, 'unit_price' => 8, 'amount' => 8],
                    ['account_code' => $expense, 'description' => "{$tag} rec-2", 'quantity' => 1, 'unit_price' => 4, 'amount' => 4],
                ]);
                if ($template->items->count() !== 2) {
                    throw new \RuntimeException('Recurring bill missing lines.');
                }
                $generated = $recurring->generateOne($template->fresh('items'));
                if ($generated->items->count() !== 2) {
                    throw new \RuntimeException('Run now did not copy two lines.');
                }
                $template->update(['is_active' => false]);
                try {
                    $recurring->generateOne($template->fresh('items'));
                    throw new \RuntimeException('Paused template should not run.');
                } catch (\LogicException $e) {
                    if (! str_contains($e->getMessage(), 'paused')) {
                        throw $e;
                    }
                }
                $template->update(['is_active' => true]);
            });

            $run("{$n}.9 SCN issue / apply / void + SDN issue / void", function () use ($bills, $creditNotes, $debitNotes, $supplier, $expense, $user, $tag) {
                $bill = $bills->create([
                    'supplier_id' => $supplier->id,
                    'purchase_kind' => 'credit',
                    'bill_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} scn-target", 'quantity' => 1, 'unit_amount' => 30, 'amount' => 30],
                ]);
                $bills->post($bill->fresh('items'));
                $scn = $creditNotes->issue([
                    'supplier_id' => $supplier->id,
                    'bill_id' => null,
                    'issue_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['description' => "{$tag} scn-a", 'quantity' => 1, 'unit_price' => 6, 'tax_rate' => 0, 'account_code' => $expense],
                    ['description' => "{$tag} scn-b", 'quantity' => 1, 'unit_price' => 4, 'tax_rate' => 0, 'account_code' => $expense],
                ]);
                if ($scn->items->count() !== 2) {
                    throw new \RuntimeException('SCN missing lines.');
                }
                $creditNotes->applyToBill($scn->fresh(), $bill->fresh(), 10);
                $creditNotes->void($scn->fresh());
                if ($scn->fresh()->status !== 'void') {
                    throw new \RuntimeException('SCN void failed.');
                }

                $sdn = $debitNotes->issue([
                    'supplier_id' => $supplier->id,
                    'issue_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['description' => "{$tag} sdn", 'quantity' => 1, 'unit_price' => 9, 'tax_rate' => 0, 'account_code' => $expense],
                ]);
                $debitNotes->void($sdn->fresh());
                if ($sdn->fresh()->status !== 'void') {
                    throw new \RuntimeException('SDN void failed.');
                }
            });

            $run("{$n}.10 AP deposit leftover + apply + statement", function () use ($bills, $deposits, $statements, $supplier, $expense, $bank, $user, $tag) {
                $bill = $bills->create([
                    'supplier_id' => $supplier->id,
                    'purchase_kind' => 'credit',
                    'bill_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ], [
                    ['account_code' => $expense, 'description' => "{$tag} deposit-target", 'quantity' => 1, 'unit_amount' => 25, 'amount' => 25],
                ]);
                $bills->post($bill->fresh('items'));
                $deposit = $deposits->receiveAndAllocate([
                    'supplier_id' => $supplier->id,
                    'amount' => 40,
                    'payment_date' => now()->toDateString(),
                    'bank_account_code' => $bank,
                    'reference' => $tag,
                    'created_by' => $user->id,
                ], [
                    ['bill_id' => $bill->id, 'amount' => 25],
                    ['bill_id' => $bill->id, 'amount' => ''],
                ]);
                if ((float) $deposit->applied_amount !== 25.0) {
                    throw new \RuntimeException('Deposit apply expected 25, got '.$deposit->applied_amount);
                }
                if (round($deposit->openAmount(), 2) !== 15.0) {
                    throw new \RuntimeException('Leftover prepaid expected 15, got '.$deposit->openAmount());
                }
                $statement = $statements->build($supplier->fresh(), Carbon::now()->startOfMonth(), Carbon::now());
                if (! isset($statement['opening_balance'], $statement['closing_balance'])) {
                    throw new \RuntimeException('Statement missing balances.');
                }
                if (PurchaseOrder::query()->count() < 1 || Bill::query()->count() < 1 || RecurringBill::query()->count() < 1) {
                    throw new \RuntimeException('Index read found empty core tables.');
                }
            });
        }

        tenancy()->end();

        $this->newLine();
        $this->info("Result: {$passed} passed, {$failed} failed (of ".($passed + $failed)." checks across {$passes} passes)");
        if ($failures !== []) {
            foreach ($failures as $line) {
                $this->error('  · '.$line);
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
