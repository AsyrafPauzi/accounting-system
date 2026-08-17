<?php

namespace App\Services\Copilot;

use App\Jobs\SendDebitNoteEmail;
use App\Jobs\SendDeliveryOrderEmail;
use App\Jobs\SendSalesOrderEmail;
use App\Mail\CustomerStatementEmail;
use App\Mail\TeamMemberWelcome;
use App\Models\ArDeposit;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\DeliveryOrder;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ArDepositService;
use App\Services\BillService;
use App\Services\CreditNoteService;
use App\Services\CustomerStatementService;
use App\Services\DebitNoteService;
use App\Services\DeliveryOrderService;
use App\Services\DocumentBulkService;
use App\Services\EstimateService;
use App\Services\InvoiceReminderService;
use App\Services\InvoiceService;
use App\Services\MyInvoisService;
use App\Services\RecurringInvoiceService;
use App\Services\SalesDocumentTrail;
use App\Services\SalesOrderService;
use App\Support\Deployment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CopilotTools
{
    public function __construct(
        private InvoiceService $invoices,
        private EstimateService $estimates,
        private BillService $bills,
        private CreditNoteService $creditNotes,
        private DebitNoteService $debitNotes,
        private ArDepositService $deposits,
        private RecurringInvoiceService $recurring,
        private SalesOrderService $salesOrders,
        private DeliveryOrderService $deliveries,
        private SalesDocumentTrail $trail,
        private CustomerStatementService $statements,
        private DocumentBulkService $bulk,
        private InvoiceReminderService $reminders,
        private MyInvoisService $myinvois,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function execute(string $name, array $args, User $user): array
    {
        $this->assertAllowed($user, $name);

        return match ($name) {
            'ar_aging' => $this->arAging(),
            'overdue_invoices' => $this->overdueInvoices((int) ($args['limit'] ?? 25)),
            'list_invoices' => $this->listInvoices($args),
            'show_invoice' => $this->showInvoice($args),
            'list_estimates' => $this->listEstimates($args),
            'show_estimate' => $this->showEstimate($args),
            'list_credit_notes' => $this->listCreditNotes($args),
            'list_debit_notes' => $this->listDebitNotes($args),
            'list_sales_orders' => $this->listSalesOrders($args),
            'list_delivery_orders' => $this->listDeliveryOrders($args),
            'list_recurring_invoices' => $this->listRecurring($args),
            'list_customers' => $this->listCustomers($args),
            'show_customer' => $this->showCustomer((int) $args['customer_id']),
            'list_products' => $this->listProducts($args),
            'customer_statement_snapshot' => $this->statementSnapshot($args),
            'download_customer_statement_pdf' => $this->statementPdfLink($args),
            'sales_report_snapshot' => $this->salesSnapshot($args),
            'document_trail' => $this->documentTrail($args),
            'explain_journal' => $this->explainJournal($args),
            'suggest_lhdn_classification' => $this->suggestClassification((string) ($args['description'] ?? '')),
            'draft_invoice', 'draft_invoice_from_receipt' => $this->saveDraftInvoice($args, $user),
            'draft_estimate' => $this->saveDraftEstimate($args, $user),
            'draft_bill_from_receipt' => $this->saveDraftBill($args, $user),
            'draft_customer' => $this->saveCustomer($args),
            'draft_product' => $this->saveProduct($args),
            'draft_sales_order' => $this->saveSalesOrder($args, $user),
            'draft_recurring_invoice' => $this->saveRecurring($args),
            'post_invoice' => $this->postInvoice((int) $args['invoice_id']),
            'void_invoice' => $this->voidInvoice((int) $args['invoice_id']),
            'record_invoice_payment' => $this->recordPayment($args, $user),
            'reverse_invoice_payment' => $this->reversePayment((int) $args['payment_id'], $user),
            'issue_credit_note' => $this->issueCreditNote($args),
            'apply_credit_note' => $this->applyCreditNote($args),
            'refund_credit_note' => $this->refundCreditNote($args, $user),
            'issue_debit_note' => $this->issueDebitNote($args),
            'receive_ar_deposit' => $this->receiveDeposit($args, $user),
            'apply_ar_deposit' => $this->applyDeposit($args),
            'refund_ar_deposit' => $this->refundDeposit($args),
            'forfeit_ar_deposit' => $this->forfeitDeposit($args),
            'convert_estimate_to_invoice' => $this->convertEstimate((int) $args['estimate_id']),
            'generate_recurring_invoice' => $this->generateRecurring((int) $args['recurring_invoice_id']),
            'email_invoice' => $this->emailInvoice((int) $args['invoice_id']),
            'email_estimate' => $this->emailEstimate((int) $args['estimate_id']),
            'send_invoice_reminder' => $this->sendReminder($args),
            'email_customer_statement' => $this->emailStatement($args),
            'myinvois_submit' => $this->myinvoisSubmit($args),
            'myinvois_refresh' => $this->myinvoisRefresh($args),
            'myinvois_cancel' => $this->myinvoisCancel($args),
            'draft_owner_expense_claim' => $this->draftOwnerExpenseClaim($args, $user),
            'list_team_members' => $this->listTeamMembers($user),
            'invite_team_member' => $this->inviteTeamMember($args, $user),
            'show_sales_order' => $this->showSalesOrder($args),
            'show_delivery_order' => $this->showDeliveryOrder($args),
            'list_ar_deposits' => $this->listArDeposits($args),
            'deliver_sales_order' => $this->deliverSalesOrder($args, $user),
            'cancel_sales_order' => $this->cancelSalesOrder($args),
            'convert_sales_order_to_invoice' => $this->convertSalesOrderToInvoice($args, $user),
            'convert_delivery_order_to_invoice' => $this->convertDeliveryOrderToInvoice($args, $user),
            'return_delivery_order' => $this->returnDeliveryOrder($args),
            'email_sales_order' => $this->emailSalesOrder($args),
            'email_delivery_order' => $this->emailDeliveryOrder($args),
            'email_debit_note' => $this->emailDebitNote($args),
            'delete_customer' => $this->deleteCustomer((int) $args['customer_id']),
            default => throw new RuntimeException("Unknown copilot tool: {$name}"),
        };
    }

    public function assertAllowed(User $user, string $name): void
    {
        if (! CopilotCatalog::exists($name)) {
            throw new RuntimeException("Unknown copilot tool: {$name}");
        }

        $perm = CopilotCatalog::permission($name);
        if ($perm && ! $user->can($perm)) {
            throw new AuthorizationException("You do not have permission {$perm}.");
        }

        if ($perm && ! Deployment::isSelfHosted()) {
            $tenant = function_exists('tenant') ? tenant() : null;
            if ($tenant && method_exists($tenant, 'hasPlanPermission') && ! $tenant->hasPlanPermission($perm)) {
                throw new AuthorizationException("This plan does not include {$perm}.");
            }
        }
    }

    /**
     * @param  iterable<mixed>  $invoices
     * @return list<array<string, mixed>>
     */
    public static function summariseOverdue(iterable $invoices, int $limit = 25): array
    {
        $today = Carbon::now()->startOfDay();
        $rows = [];
        foreach ($invoices as $invoice) {
            $status = (string) data_get($invoice, 'status');
            if (! in_array($status, ['unpaid', 'partially paid'], true)) {
                continue;
            }
            $due = data_get($invoice, 'due_date');
            if (! $due) {
                continue;
            }
            $dueDay = Carbon::parse($due)->startOfDay();
            if ($dueDay->gte($today)) {
                continue;
            }
            $total = (float) data_get($invoice, 'total_amount');
            $paid = (float) data_get($invoice, 'amount_paid');
            $balance = $total - $paid;
            if ($balance <= 0) {
                continue;
            }
            $rows[] = [
                'id' => data_get($invoice, 'id'),
                'invoice_number' => data_get($invoice, 'invoice_number'),
                'customer' => data_get($invoice, 'customer.name') ?? data_get($invoice, 'customer_name'),
                'due_date' => $dueDay->toDateString(),
                'days_overdue' => (int) $dueDay->diffInDays($today),
                'balance' => round($balance, 2),
                'status' => $status,
            ];
        }
        usort($rows, fn ($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function arAging(): array
    {
        $invoices = Invoice::with('customer:id,name')
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->get();

        $today = now()->startOfDay();
        $buckets = [
            'current' => ['label' => 'Current (not yet due)', 'amount' => 0.0, 'count' => 0],
            '1-30' => ['label' => '1–30 days overdue', 'amount' => 0.0, 'count' => 0],
            '31-60' => ['label' => '31–60 days overdue', 'amount' => 0.0, 'count' => 0],
            '61-90' => ['label' => '61–90 days overdue', 'amount' => 0.0, 'count' => 0],
            '90+' => ['label' => '90+ days overdue', 'amount' => 0.0, 'count' => 0],
        ];

        foreach ($invoices as $invoice) {
            $balance = (float) $invoice->total_amount - (float) $invoice->amount_paid;
            if ($balance <= 0) {
                continue;
            }
            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->startOfDay() : $today;
            $daysOverdue = (int) $today->diffInDays($dueDate, false);
            if ($daysOverdue < 0) {
                $daysOverdue = (int) abs($daysOverdue);
            } else {
                $daysOverdue = 0;
            }
            $bucket = match (true) {
                $daysOverdue === 0 => 'current',
                $daysOverdue <= 30 => '1-30',
                $daysOverdue <= 60 => '31-60',
                $daysOverdue <= 90 => '61-90',
                default => '90+',
            };
            $buckets[$bucket]['amount'] += $balance;
            $buckets[$bucket]['count']++;
        }

        foreach ($buckets as $k => $b) {
            $buckets[$k]['amount'] = round($b['amount'], 2);
        }

        return ['buckets' => $buckets];
    }

    /**
     * @return array<string, mixed>
     */
    private function overdueInvoices(int $limit): array
    {
        $invoices = Invoice::with('customer:id,name')
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->get();

        return ['invoices' => self::summariseOverdue($invoices, $limit)];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listInvoices(array $args): array
    {
        $q = Invoice::query()->with('customer:id,name')->orderByDesc('id');
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }
        if (! empty($args['query'])) {
            $term = '%'.$args['query'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('invoice_number', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        return [
            'invoices' => $q->limit($this->limit($args))->get()->map(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'customer' => $i->customer?->name,
                'status' => $i->status,
                'issue_date' => optional($i->issue_date)->toDateString(),
                'due_date' => optional($i->due_date)->toDateString(),
                'total_amount' => (float) $i->total_amount,
                'amount_paid' => (float) $i->amount_paid,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function showInvoice(array $args): array
    {
        $invoice = $this->findInvoice($args);
        $invoice->load(['customer', 'items']);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'customer' => $invoice->customer?->only(['id', 'name', 'email', 'tin', 'brn']),
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'total_amount' => (float) $invoice->total_amount,
            'amount_paid' => (float) $invoice->amount_paid,
            'balance' => $this->invoices->remainingBalance($invoice),
            'lhdn_status' => $invoice->lhdn_status,
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'amount' => (float) $item->amount,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listEstimates(array $args): array
    {
        $q = Estimate::query()->with('customer:id,name')->orderByDesc('id');
        if (! empty($args['query'])) {
            $term = '%'.$args['query'].'%';
            $q->where('estimate_number', 'like', $term);
        }

        return [
            'estimates' => $q->limit($this->limit($args))->get()->map(fn (Estimate $e) => [
                'id' => $e->id,
                'estimate_number' => $e->estimate_number,
                'customer' => $e->customer?->name,
                'status' => $e->status,
                'total_amount' => (float) $e->total_amount,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function showEstimate(array $args): array
    {
        $estimate = ! empty($args['estimate_id'])
            ? Estimate::with(['customer', 'items'])->findOrFail($args['estimate_id'])
            : Estimate::with(['customer', 'items'])->where('estimate_number', $args['estimate_number'] ?? '')->firstOrFail();

        return $estimate->only(['id', 'estimate_number', 'status', 'total_amount', 'issue_date', 'expiry_date'])
            + ['customer' => $estimate->customer?->name, 'items' => $estimate->items];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listCreditNotes(array $args): array
    {
        $q = CreditNote::query()->with('customer:id,name')->orderByDesc('id');

        return [
            'credit_notes' => $q->limit($this->limit($args))->get()->map(fn (CreditNote $cn) => [
                'id' => $cn->id,
                'cn_number' => $cn->cn_number,
                'customer' => $cn->customer?->name,
                'status' => $cn->status,
                'total_amount' => (float) $cn->total_amount,
                'applied_amount' => (float) $cn->applied_amount,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listDebitNotes(array $args): array
    {
        return [
            'debit_notes' => DebitNote::query()->with('customer:id,name')->orderByDesc('id')->limit($this->limit($args))->get()
                ->map(fn (DebitNote $dn) => [
                    'id' => $dn->id,
                    'dn_number' => $dn->dn_number,
                    'customer' => $dn->customer?->name,
                    'status' => $dn->status,
                    'total_amount' => (float) $dn->total_amount,
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listSalesOrders(array $args): array
    {
        return [
            'sales_orders' => SalesOrder::query()->with('customer:id,name')->orderByDesc('id')->limit($this->limit($args))->get()
                ->map(fn (SalesOrder $so) => [
                    'id' => $so->id,
                    'so_number' => $so->so_number,
                    'customer' => $so->customer?->name,
                    'status' => $so->status,
                    'total_amount' => (float) $so->total_amount,
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listDeliveryOrders(array $args): array
    {
        return [
            'delivery_orders' => DeliveryOrder::query()->with('customer:id,name')->orderByDesc('id')->limit($this->limit($args))->get()
                ->map(fn (DeliveryOrder $do) => [
                    'id' => $do->id,
                    'do_number' => $do->do_number,
                    'customer' => $do->customer?->name,
                    'status' => $do->status,
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listRecurring(array $args): array
    {
        return [
            'recurring_invoices' => RecurringInvoice::query()->with('customer:id,name')->orderByDesc('id')->limit($this->limit($args))->get()
                ->map(fn (RecurringInvoice $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'customer' => $r->customer?->name,
                    'cadence' => $r->cadence,
                    'next_run_date' => optional($r->next_run_date)->toDateString(),
                    'is_active' => (bool) $r->is_active,
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listCustomers(array $args): array
    {
        $q = Customer::query()->orderBy('name');
        if (! empty($args['query'])) {
            $term = '%'.$args['query'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        return [
            'customers' => $q->limit($this->limit($args))->get(['id', 'name', 'email', 'phone', 'tin', 'brn', 'is_active'])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function showCustomer(int $id): array
    {
        $c = Customer::findOrFail($id);

        return $c->only(['id', 'name', 'email', 'phone', 'tin', 'brn', 'credit_limit', 'credit_hold', 'is_active'])
            + ['deletion_blocked_reason' => $c->deletionBlockedReason()];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listProducts(array $args): array
    {
        $q = Product::query()->orderBy('name');
        if (! empty($args['query'])) {
            $q->where('name', 'like', '%'.$args['query'].'%');
        }

        return [
            'products' => $q->limit($this->limit($args))->get(['id', 'name', 'code', 'unit_price', 'tax_rate', 'account_code', 'is_active'])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function statementSnapshot(array $args): array
    {
        $customer = Customer::findOrFail($args['customer_id']);
        $defaults = $this->statements->defaultWindow();
        $from = Carbon::parse($args['from'] ?? $defaults['from']);
        $to = Carbon::parse($args['to'] ?? $defaults['to']);
        $built = $this->statements->build($customer, $from, $to);

        return [
            'customer' => $customer->name,
            'from' => $built['from'],
            'to' => $built['to'],
            'opening_balance' => $built['opening_balance'],
            'total_charges' => $built['total_charges'],
            'total_payments' => $built['total_payments'],
            'total_credits' => $built['total_credits'],
            'closing_balance' => $built['closing_balance'],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function statementPdfLink(array $args): array
    {
        $customer = Customer::findOrFail($args['customer_id']);
        $defaults = $this->statements->defaultWindow();
        $from = Carbon::parse($args['from'] ?? $defaults['from'])->toDateString();
        $to = Carbon::parse($args['to'] ?? $defaults['to'])->toDateString();

        // Validate the statement can be built for this window.
        $this->statements->build($customer, Carbon::parse($from), Carbon::parse($to));

        $pdfUrl = route('customer-statements.pdf', $customer->id).'?'.http_build_query([
            'from' => $from,
            'to' => $to,
        ]);
        $viewUrl = route('customer-statements.show', $customer->id).'?'.http_build_query([
            'from' => $from,
            'to' => $to,
        ]);

        return [
            'ok' => true,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'from' => $from,
            'to' => $to,
            'pdf_url' => $pdfUrl,
            'view_url' => $viewUrl,
            'instruction' => 'Reply with a markdown link to pdf_url so the user can download the statement PDF. Do not email unless they asked to email.',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function salesSnapshot(array $args): array
    {
        $start = $args['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $args['end_date'] ?? now()->endOfMonth()->toDateString();
        $rows = Invoice::with('customer:id,name')
            ->whereBetween('issue_date', [$start, $end])
            ->where('status', '!=', 'void')
            ->select('customer_id', DB::raw('SUM(total_amount) as total_sales'), DB::raw('COUNT(*) as invoice_count'))
            ->groupBy('customer_id')
            ->get()
            ->map(fn ($row) => [
                'customer' => $row->customer->name ?? 'Unknown',
                'total_sales' => round((float) $row->total_sales, 2),
                'invoice_count' => (int) $row->invoice_count,
            ]);

        return [
            'start_date' => $start,
            'end_date' => $end,
            'total_sales' => round($rows->sum('total_sales'), 2),
            'by_customer' => $rows->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function documentTrail(array $args): array
    {
        if (! empty($args['invoice_id'])) {
            return ['trail' => $this->trail->forInvoice(Invoice::findOrFail($args['invoice_id']))];
        }
        if (! empty($args['estimate_id'])) {
            return ['trail' => $this->trail->forEstimate(Estimate::findOrFail($args['estimate_id']))];
        }

        throw new RuntimeException('Pass invoice_id or estimate_id.');
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function explainJournal(array $args): array
    {
        $type = $args['document_type'];
        $id = (int) $args['document_id'];
        $map = [
            'invoice' => ['Invoice', 'Invoice Payment'],
            'credit_note' => ['Credit Note', 'Credit Note Refund'],
            'debit_note' => ['Debit Note'],
        ];
        $types = $map[$type] ?? [];
        if ($types === []) {
            throw new RuntimeException('Unsupported document_type.');
        }

        $journals = DB::table('journal_entries')
            ->whereIn('reference_type', $types)
            ->where('reference_id', $id)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($journals as $journal) {
            $lines = [];
            if (Schema::hasTable('journal_items')) {
                $lines = DB::table('journal_items as ji')
                    ->leftJoin('accounts as a', 'a.id', '=', 'ji.account_id')
                    ->where('ji.journal_entry_id', $journal->id)
                    ->get(['a.code', 'a.name', 'ji.debit', 'ji.credit'])
                    ->all();
            }
            $out[] = [
                'id' => $journal->id,
                'date' => $journal->date,
                'description' => $journal->description,
                'reference_type' => $journal->reference_type,
                'lines' => $lines,
            ];
        }

        return ['journals' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    public static function suggestClassification(string $description): array
    {
        $text = mb_strtolower($description);
        $code = '022';
        $reason = 'Default classification 022 (others) when the line is not a clear goods or professional-service match.';
        $sst = 0.0;
        $sstReason = 'No SST keyword found; suggesting 0%. Confirm against the SST schedule before posting.';

        if (preg_match('/consult|akaun|account|audit|legal|lawyer|peguam|software|langgan|subscription/', $text)) {
            $code = '022';
            $reason = 'Professional / digital service wording — keep 022 unless LHDN guidance says otherwise.';
        }
        if (preg_match('/makan|food|restoran|minuman|f&b/', $text)) {
            $code = '008';
            $reason = 'Food & beverage wording often maps near classification 008; verify against LHDN list.';
        }
        if (preg_match('/sst|cukai perkhidmatan|service tax/', $text)) {
            $sst = 8.0;
            $sstReason = 'Service-tax wording present; 8% is the common SST rate — confirm the item is taxable.';
        } elseif (preg_match('/gst|sales tax|cukai jualan/', $text)) {
            $sst = 10.0;
            $sstReason = 'Sales-tax wording present; suggesting 10% pending schedule check.';
        }

        return [
            'item_classification' => $code,
            'classification_reason' => $reason,
            'suggested_tax_rate' => $sst,
            'tax_reason' => $sstReason,
            'wrote' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveDraftInvoice(array $args, User $user): array
    {
        $items = $this->normalizeSalesItems($args['items'] ?? []);
        $msic = $args['msic_code'] ?? (function_exists('tenant') ? (tenant()->msic_code ?? '00000') : '00000');
        $invoice = $this->invoices->create([
            'invoice_number' => $this->invoices->nextNumber(),
            'msic_code' => $msic ?: '00000',
            'customer_id' => $args['customer_id'],
            'issue_date' => $args['issue_date'] ?? now()->toDateString(),
            'due_date' => $args['due_date'] ?? now()->addDays(30)->toDateString(),
            'currency' => $args['currency'] ?? 'MYR',
            'customer_notes' => $args['customer_notes'] ?? null,
            'show_signature' => false,
            'created_by' => $user->id,
        ], $items);

        return [
            'ok' => true,
            'status' => 'draft',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => (float) $invoice->total_amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveDraftEstimate(array $args, User $user): array
    {
        $items = $this->normalizeSalesItems($args['items'] ?? []);
        $estimate = $this->estimates->create([
            'estimate_number' => $this->estimates->nextNumber(),
            'customer_id' => $args['customer_id'],
            'issue_date' => $args['issue_date'] ?? now()->toDateString(),
            'expiry_date' => $args['expiry_date'] ?? null,
            'currency' => $args['currency'] ?? 'MYR',
            'customer_notes' => $args['customer_notes'] ?? null,
            'created_by' => $user->id,
            'status' => 'draft',
        ], $items);

        return [
            'ok' => true,
            'status' => 'draft',
            'estimate_id' => $estimate->id,
            'estimate_number' => $estimate->estimate_number,
            'total_amount' => (float) $estimate->total_amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveDraftBill(array $args, User $user): array
    {
        $supplier = $this->resolveOrCreateSupplier($args);

        $items = [];
        foreach ($args['items'] ?? [] as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }
            $unit = (float) ($item['unit_amount'] ?? $item['unit_price'] ?? 0);
            $amount = (float) ($item['amount'] ?? ($qty * $unit));
            if ($unit <= 0 && $amount > 0) {
                $unit = $amount / $qty;
            }
            $items[] = [
                'description' => $item['description'] ?? 'Item',
                'quantity' => $qty,
                'unit_amount' => $unit,
                'amount' => $amount,
                'account_code' => $item['account_code'] ?? '5000',
            ];
        }

        $bill = $this->bills->create([
            'bill_number' => $this->bills->nextNumber(),
            'supplier_id' => $supplier->id,
            'bill_date' => $args['bill_date'] ?? now()->toDateString(),
            'due_date' => $args['due_date'] ?? null,
            'reference' => $args['reference'] ?? null,
            'tax_amount' => $args['tax_amount'] ?? 0,
            'receipt_path' => $this->persistBillReceiptPath($args['receipt_path'] ?? null),
            'ocr_status' => ! empty($args['receipt_path']) ? 'success' : 'none',
            'created_by' => $user->id,
            'purchase_kind' => 'credit',
        ], $items);

        return [
            'ok' => true,
            'status' => 'draft',
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_created' => (bool) ($supplier->wasRecentlyCreated ?? false),
            'total_amount' => (float) $bill->total_amount,
        ];
    }

    /**
     * Match an existing supplier by id / case-insensitive name, or create one from OCR vendor fields.
     *
     * @param  array<string, mixed>  $args
     */
    private function resolveOrCreateSupplier(array $args): Supplier
    {
        if (! empty($args['supplier_id'])) {
            $byId = Supplier::query()->find((int) $args['supplier_id']);
            if ($byId) {
                return $byId;
            }
        }

        $name = trim((string) ($args['vendor_name'] ?? $args['supplier_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Supplier is required. Pass supplier_id or vendor_name from the receipt.');
        }

        $existing = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Supplier::create([
            'name' => $name,
            'code' => $args['supplier_code'] ?? ('SUP-'.str_pad((string) ((int) Supplier::max('id') + 1), 4, '0', STR_PAD_LEFT)),
            'email' => $args['vendor_email'] ?? $args['email'] ?? null,
            'phone' => $args['vendor_phone'] ?? $args['phone'] ?? null,
            'tin' => $args['vendor_tin'] ?? $args['tin'] ?? null,
            'brn' => $args['vendor_brn'] ?? $args['brn'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Ensure the receipt lives under public `receipts/` so bill preview can serve it.
     * Legacy copilot uploads were stored on the local disk under `copilot-receipts/`.
     */
    private function persistBillReceiptPath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (str_starts_with($path, 'receipts/') && Storage::disk('public')->exists($path)) {
            return $path;
        }

        if (str_starts_with($path, 'copilot-receipts/') && Storage::disk('local')->exists($path)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
            $dest = 'receipts/'.uniqid('copilot_', true).'.'.$extension;
            Storage::disk('public')->put($dest, Storage::disk('local')->get($path));

            return $dest;
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveCustomer(array $args): array
    {
        $code = $args['code'] ?? ('CUST-'.str_pad((string) ((int) Customer::max('id') + 1), 4, '0', STR_PAD_LEFT));
        $customer = Customer::create([
            'name' => $args['name'],
            'code' => $code,
            'email' => $args['email'] ?? null,
            'phone' => $args['phone'] ?? null,
            'tin' => $args['tin'] ?? null,
            'brn' => $args['brn'] ?? null,
            'is_active' => true,
        ]);

        return ['ok' => true, 'customer_id' => $customer->id, 'name' => $customer->name, 'code' => $customer->code];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveProduct(array $args): array
    {
        $product = Product::create([
            'name' => $args['name'],
            'code' => $args['code'] ?? ('PRD-'.str_pad((string) ((int) Product::max('id') + 1), 4, '0', STR_PAD_LEFT)),
            'description' => $args['description'] ?? null,
            'unit_price' => $args['unit_price'],
            'tax_rate' => $args['tax_rate'] ?? 0,
            'account_code' => $args['account_code'] ?? '4000',
            'is_active' => true,
        ]);

        return ['ok' => true, 'product_id' => $product->id, 'name' => $product->name, 'code' => $product->code];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveSalesOrder(array $args, User $user): array
    {
        $so = $this->salesOrders->create([
            'customer_id' => $args['customer_id'],
            'issue_date' => $args['issue_date'] ?? now()->toDateString(),
            'expected_date' => $args['expected_date'] ?? null,
            'currency' => $args['currency'] ?? 'MYR',
            'customer_notes' => $args['customer_notes'] ?? null,
            'created_by' => $user->id,
            'status' => 'draft',
        ], $this->normalizeSalesItems($args['items'] ?? []));

        return ['ok' => true, 'sales_order_id' => $so->id, 'so_number' => $so->so_number, 'total_amount' => (float) $so->total_amount];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function saveRecurring(array $args): array
    {
        $template = $this->recurring->create([
            'name' => $args['name'] ?? 'Recurring',
            'customer_id' => $args['customer_id'],
            'cadence' => $args['cadence'],
            'interval' => $args['interval'] ?? 1,
            'start_date' => $args['start_date'],
            'currency' => $args['currency'] ?? 'MYR',
            'msic_code' => $args['msic_code'] ?? '00000',
        ], $this->normalizeSalesItems($args['items'] ?? []));

        return ['ok' => true, 'recurring_invoice_id' => $template->id, 'next_run_date' => optional($template->next_run_date)->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    private function postInvoice(int $id): array
    {
        $invoice = Invoice::findOrFail($id);
        $this->invoices->post($invoice);

        return ['ok' => true, 'invoice_id' => $invoice->id, 'status' => $invoice->fresh()->status];
    }

    /**
     * @return array<string, mixed>
     */
    private function voidInvoice(int $id): array
    {
        $invoice = Invoice::findOrFail($id);
        $this->invoices->void($invoice);

        return ['ok' => true, 'invoice_id' => $invoice->id, 'status' => 'void'];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function recordPayment(array $args, User $user): array
    {
        $invoice = Invoice::findOrFail($args['invoice_id']);
        $payment = $this->invoices->recordPayment(
            $invoice,
            (float) $args['amount'],
            $args['payment_date'],
            $args['bank_account_code'],
            $args['reference'] ?? null,
            $user->id,
        );

        return ['ok' => true, 'payment_id' => $payment->id, 'invoice_status' => $invoice->fresh()->status];
    }

    /**
     * @return array<string, mixed>
     */
    private function reversePayment(int $paymentId, User $user): array
    {
        $payment = InvoicePayment::findOrFail($paymentId);
        $this->invoices->reversePayment($payment, $user->id);

        return ['ok' => true, 'payment_id' => $paymentId];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function issueCreditNote(array $args): array
    {
        $cn = $this->creditNotes->issue($args, $this->normalizeSalesItems($args['items'] ?? []));

        return ['ok' => true, 'credit_note_id' => $cn->id, 'cn_number' => $cn->cn_number, 'status' => $cn->status, 'total_amount' => (float) $cn->total_amount];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function applyCreditNote(array $args): array
    {
        $cn = CreditNote::findOrFail($args['credit_note_id']);
        $invoice = Invoice::findOrFail($args['invoice_id']);
        $this->creditNotes->applyToInvoice($cn, $invoice, (float) $args['amount']);

        return ['ok' => true, 'credit_note_id' => $cn->id, 'invoice_id' => $invoice->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function refundCreditNote(array $args, User $user): array
    {
        $cn = CreditNote::findOrFail($args['credit_note_id']);
        $refund = $this->creditNotes->refund(
            $cn,
            (float) $args['amount'],
            $args['bank_account_code'],
            $args['payment_date'],
            $args['reference'] ?? null,
            $user->id,
        );

        return ['ok' => true, 'refund_id' => $refund->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function issueDebitNote(array $args): array
    {
        $dn = $this->debitNotes->issue($args, $this->normalizeSalesItems($args['items'] ?? []));

        return ['ok' => true, 'debit_note_id' => $dn->id, 'dn_number' => $dn->dn_number, 'status' => $dn->status];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function receiveDeposit(array $args, User $user): array
    {
        $deposit = $this->deposits->receive([
            'customer_id' => $args['customer_id'],
            'amount' => $args['amount'],
            'payment_date' => $args['payment_date'],
            'bank_account_code' => $args['bank_account_code'],
            'reference' => $args['reference'] ?? null,
            'created_by' => $user->id,
        ]);

        return ['ok' => true, 'deposit_id' => $deposit->id, 'amount' => (float) $deposit->amount];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function applyDeposit(array $args): array
    {
        $deposit = ArDeposit::findOrFail($args['deposit_id']);
        $invoice = Invoice::findOrFail($args['invoice_id']);
        $this->deposits->applyToInvoice($deposit, $invoice, (float) $args['amount']);

        return ['ok' => true, 'deposit_id' => $deposit->id, 'invoice_id' => $invoice->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function refundDeposit(array $args): array
    {
        $deposit = ArDeposit::findOrFail($args['deposit_id']);
        $this->deposits->refundLeftover($deposit, $args['payment_date'], $args['reference'] ?? null);

        return ['ok' => true, 'deposit_id' => $deposit->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function forfeitDeposit(array $args): array
    {
        $deposit = ArDeposit::findOrFail($args['deposit_id']);
        $this->deposits->forfeitLeftover($deposit, $args['date']);

        return ['ok' => true, 'deposit_id' => $deposit->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function convertEstimate(int $id): array
    {
        $invoice = $this->estimates->convertToInvoice(Estimate::findOrFail($id));

        return ['ok' => true, 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'status' => $invoice->status];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateRecurring(int $id): array
    {
        $invoice = $this->recurring->generateOne(RecurringInvoice::findOrFail($id));

        return ['ok' => true, 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'status' => $invoice->status];
    }

    /**
     * @return array<string, mixed>
     */
    private function emailInvoice(int $id): array
    {
        $result = $this->bulk->queueInvoiceEmails([$id], $this->bulk->companyDetails());

        return ['ok' => $result['queued'] > 0, 'queued' => $result['queued'], 'skipped' => $result['skipped']];
    }

    /**
     * @return array<string, mixed>
     */
    private function emailEstimate(int $id): array
    {
        $result = $this->bulk->queueEstimateEmails([$id], $this->bulk->companyDetails());

        return ['ok' => $result['queued'] > 0, 'queued' => $result['queued'], 'skipped' => $result['skipped']];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function sendReminder(array $args): array
    {
        $invoice = Invoice::with('customer')->findOrFail($args['invoice_id']);
        $offset = (int) ($args['offset'] ?? ($this->reminders->dueOffsetToday($invoice) ?? 0));
        $this->reminders->send($invoice, $offset);

        return ['ok' => true, 'invoice_id' => $invoice->id, 'offset' => $offset];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function emailStatement(array $args): array
    {
        $customer = Customer::with('contacts')->findOrFail($args['customer_id']);
        $defaults = $this->statements->defaultWindow();
        $from = $args['from'] ?? $defaults['from'];
        $to = $args['to'] ?? $defaults['to'];
        $statement = $this->statements->build($customer, Carbon::parse($from), Carbon::parse($to));
        $company = $this->bulk->companyDetails();
        $recipients = $this->bulk->recipientsFor($customer);
        if ($recipients === []) {
            throw new RuntimeException('Customer has no email or billing contact on file.');
        }

        $pdfBytes = Pdf::loadView('pdf.customer_statement', compact('customer', 'statement', 'company'))
            ->setPaper('a4', 'portrait')
            ->output();
        $filename = 'Statement-'.$customer->id.'.pdf';
        Mail::to($recipients)->send(new CustomerStatementEmail($customer, $statement, $company, $pdfBytes, $filename));

        return ['ok' => true, 'recipients' => $recipients];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function myinvoisSubmit(array $args): array
    {
        $doc = $this->resolveMyInvoisDoc($args);
        $this->myinvois->submit($doc);
        $doc->refresh();

        return [
            'ok' => true,
            'lhdn_status' => $doc->lhdn_status ?? null,
            'lhdn_uuid' => $doc->lhdn_uuid ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function myinvoisRefresh(array $args): array
    {
        $doc = $this->resolveMyInvoisDoc($args);
        $this->myinvois->refreshStatus($doc);
        $doc->refresh();

        return ['ok' => true, 'lhdn_status' => $doc->lhdn_status ?? null];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function myinvoisCancel(array $args): array
    {
        $doc = $this->resolveMyInvoisDoc($args);
        $this->myinvois->cancel($doc, (string) $args['reason']);
        $doc->refresh();

        return ['ok' => true, 'lhdn_status' => $doc->lhdn_status ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteCustomer(int $id): array
    {
        $customer = Customer::findOrFail($id);
        $reason = $customer->deletionBlockedReason();
        if ($reason) {
            throw new RuntimeException($reason);
        }
        $customer->delete();

        return ['ok' => true, 'customer_id' => $id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function draftOwnerExpenseClaim(array $args, User $user): array
    {
        $claimant = trim((string) ($args['claimant_name'] ?? ''));
        if ($claimant === '') {
            $claimant = trim((string) ($user->name ?? '')) ?: 'Director / Owner';
        }

        $supplier = $this->resolveOrCreateSupplier(['vendor_name' => $claimant]);
        $amount = (float) ($args['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Claim amount must be greater than zero.');
        }

        $baseNotes = 'Owner claim — paid personally. Reimburse via bill payment.';
        $extra = trim((string) ($args['notes'] ?? ''));
        $notes = $extra !== '' ? $baseNotes.' '.$extra : $baseNotes;

        $description = trim((string) ($args['description'] ?? ''));
        if ($description === '') {
            throw new RuntimeException('Description is required.');
        }

        $bill = $this->bills->create([
            'bill_number' => $this->bills->nextNumber(),
            'supplier_id' => $supplier->id,
            'bill_date' => $args['bill_date'] ?? now()->toDateString(),
            'due_date' => $args['due_date'] ?? null,
            'private_notes' => $notes,
            'receipt_path' => $this->persistBillReceiptPath($args['receipt_path'] ?? null),
            'ocr_status' => ! empty($args['receipt_path']) ? 'success' : 'none',
            'created_by' => $user->id,
            'purchase_kind' => 'claim',
        ], [[
            'description' => $description,
            'quantity' => 1,
            'unit_amount' => $amount,
            'amount' => $amount,
            'account_code' => $args['account_code'] ?? '5000',
        ]]);

        return [
            'ok' => true,
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'supplier_name' => $supplier->name,
            'total_amount' => (float) $bill->total_amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listTeamMembers(User $user): array
    {
        if (! $user->tenant_id) {
            throw new RuntimeException('No tenant on the current user.');
        }

        $members = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'roles' => $member->getRoleNames()->values()->all(),
            ])
            ->all();

        return ['team_members' => $members];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function inviteTeamMember(array $args, User $user): array
    {
        $tenantId = $user->tenant_id;
        if (! $tenantId) {
            throw new RuntimeException('No tenant on the current user.');
        }

        $role = (string) ($args['role'] ?? '');
        if (! in_array($role, ['admin', 'accountant', 'sales', 'viewer'], true)) {
            throw new RuntimeException('Role must be one of: admin, accountant, sales, viewer.');
        }

        $tenant = Tenant::find($tenantId);
        $subscription = $tenant?->activeSubscription();
        if (! $subscription) {
            throw new RuntimeException('No active subscription found. Please subscribe to a plan to add team members.');
        }

        $userCount = User::where('tenant_id', $tenantId)->count();
        $totalSeats = $subscription->totalSeats();
        if ($userCount >= $totalSeats) {
            throw new RuntimeException(
                'No seats available. Add seats in Settings → Team / Plan before inviting another member.'
            );
        }

        $targetRole = Role::where('name', $role)->where('guard_name', 'web')->first();
        $newUser = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => Hash::make(Str::password(16)),
            'tenant_id' => $tenantId,
            'role_id' => $targetRole?->id,
        ]);

        if ($targetRole) {
            $newUser->assignRole($role);
        }

        $emailQueued = false;
        if ($tenant) {
            try {
                $token = Password::broker()->createToken($newUser);
                $resetUrl = route('password.reset', [
                    'token' => $token,
                    'email' => $newUser->email,
                ]);

                Mail::to($newUser->email)->queue(new TeamMemberWelcome(
                    user: $newUser,
                    tenant: $tenant,
                    role: $role,
                    resetUrl: $resetUrl,
                    inviterName: $user->name,
                ));
                $emailQueued = true;
            } catch (\Throwable $e) {
                Log::warning('Team member welcome email dispatch failed', [
                    'tenant_id' => $tenantId,
                    'user_id' => $newUser->id,
                    'email' => $newUser->email,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        return [
            'ok' => true,
            'user_id' => $newUser->id,
            'email' => $newUser->email,
            'role' => $role,
            'email_queued' => $emailQueued,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function showSalesOrder(array $args): array
    {
        $so = $this->findSalesOrder($args);
        $so->load(['customer', 'items']);

        return [
            'id' => $so->id,
            'so_number' => $so->so_number,
            'status' => $so->status,
            'customer' => $so->customer?->only(['id', 'name', 'email']),
            'issue_date' => optional($so->issue_date)->toDateString(),
            'expected_date' => optional($so->expected_date)->toDateString(),
            'total_amount' => (float) $so->total_amount,
            'items' => $so->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'qty_delivered' => (float) ($item->qty_delivered ?? 0),
                'qty_invoiced' => (float) ($item->qty_invoiced ?? 0),
                'unit_price' => (float) $item->unit_price,
                'amount' => (float) ($item->amount ?? 0),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function showDeliveryOrder(array $args): array
    {
        $do = $this->findDeliveryOrder($args);
        $do->load(['customer', 'items', 'salesOrder']);

        return [
            'id' => $do->id,
            'do_number' => $do->do_number,
            'status' => $do->status,
            'sales_order_id' => $do->sales_order_id,
            'so_number' => $do->salesOrder?->so_number,
            'customer' => $do->customer?->only(['id', 'name', 'email']),
            'issue_date' => optional($do->issue_date)->toDateString(),
            'delivery_date' => optional($do->delivery_date)->toDateString(),
            'items' => $do->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'qty_invoiced' => (float) ($item->qty_invoiced ?? 0),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function listArDeposits(array $args): array
    {
        $q = ArDeposit::query()->with('customer:id,name')->orderByDesc('id');
        if (! empty($args['customer_id'])) {
            $q->where('customer_id', (int) $args['customer_id']);
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }
        if (! empty($args['query'])) {
            $term = '%'.$args['query'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('reference', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        return [
            'deposits' => $q->limit($this->limit($args))->get()->map(fn (ArDeposit $d) => [
                'id' => $d->id,
                'customer' => $d->customer?->name,
                'amount' => (float) $d->amount,
                'applied_amount' => (float) $d->applied_amount,
                'open_amount' => $d->openAmount(),
                'status' => $d->status,
                'payment_date' => optional($d->payment_date)->toDateString(),
                'reference' => $d->reference,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function deliverSalesOrder(array $args, User $user): array
    {
        $so = $this->findSalesOrder($args);
        $do = $this->deliveries->fromSalesOrder($so, $this->normalizeQuantities($args['quantities'] ?? []), $user->id);

        return [
            'ok' => true,
            'delivery_order_id' => $do->id,
            'do_number' => $do->do_number,
            'status' => $do->status,
            'sales_order_id' => $so->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function cancelSalesOrder(array $args): array
    {
        $so = $this->findSalesOrder($args);
        $this->salesOrders->cancel($so);

        return ['ok' => true, 'sales_order_id' => $so->id, 'so_number' => $so->so_number, 'status' => $so->fresh()->status];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function convertSalesOrderToInvoice(array $args, User $user): array
    {
        $so = $this->findSalesOrder($args);
        $invoice = $this->salesOrders->convertToInvoice($so, $this->normalizeQuantities($args['quantities'] ?? []), $user->id);

        return [
            'ok' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'sales_order_id' => $so->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function convertDeliveryOrderToInvoice(array $args, User $user): array
    {
        $do = $this->findDeliveryOrder($args);
        $invoice = $this->deliveries->convertToInvoice($do, $user->id);

        return [
            'ok' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'delivery_order_id' => $do->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function returnDeliveryOrder(array $args): array
    {
        $do = $this->findDeliveryOrder($args);
        $this->deliveries->returnFull($do);

        return ['ok' => true, 'delivery_order_id' => $do->id, 'do_number' => $do->do_number, 'status' => $do->fresh()->status];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function emailSalesOrder(array $args): array
    {
        $so = $this->findSalesOrder($args);
        $so->loadMissing('customer');
        $recipients = $this->customerEmailRecipients($so->customer);
        SendSalesOrderEmail::dispatch($so->id, $recipients, $this->bulk->companyDetails());

        return ['ok' => true, 'sales_order_id' => $so->id, 'recipients' => $recipients];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function emailDeliveryOrder(array $args): array
    {
        $do = $this->findDeliveryOrder($args);
        $do->loadMissing('customer');
        $recipients = $this->customerEmailRecipients($do->customer);
        SendDeliveryOrderEmail::dispatch($do->id, $recipients, $this->bulk->companyDetails());

        return ['ok' => true, 'delivery_order_id' => $do->id, 'recipients' => $recipients];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function emailDebitNote(array $args): array
    {
        $dn = $this->findDebitNote($args);
        $dn->loadMissing('customer');
        $recipients = $this->customerEmailRecipients($dn->customer);
        SendDebitNoteEmail::dispatch($dn->id, $recipients, $this->bulk->companyDetails());

        return ['ok' => true, 'debit_note_id' => $dn->id, 'recipients' => $recipients];
    }

    /**
     * @return list<string>
     */
    private function customerEmailRecipients(?Customer $customer): array
    {
        if (! $customer) {
            throw new RuntimeException('Customer not found.');
        }

        $recipients = $this->bulk->recipientsFor($customer);
        if ($recipients === []) {
            throw new RuntimeException('Customer has no email on file.');
        }

        return $recipients;
    }

    /**
     * @param  mixed  $quantities
     * @return array<int, float>
     */
    private function normalizeQuantities(mixed $quantities): array
    {
        if (! is_array($quantities)) {
            return [];
        }

        $out = [];
        foreach ($quantities as $itemId => $qty) {
            $out[(int) $itemId] = (float) $qty;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function resolveMyInvoisDoc(array $args): Invoice|CreditNote|DebitNote
    {
        return match ($args['document_type']) {
            'invoice' => Invoice::with(['items', 'customer'])->findOrFail($args['document_id']),
            'credit_note' => CreditNote::with(['items', 'customer'])->findOrFail($args['document_id']),
            'debit_note' => DebitNote::with(['items', 'customer'])->findOrFail($args['document_id']),
            default => throw new RuntimeException('Unsupported MyInvois document_type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function findSalesOrder(array $args): SalesOrder
    {
        if (! empty($args['sales_order_id'])) {
            return SalesOrder::findOrFail($args['sales_order_id']);
        }
        if (! empty($args['so_number'])) {
            return SalesOrder::query()->where('so_number', $args['so_number'])->firstOrFail();
        }

        throw new RuntimeException('Pass sales_order_id or so_number.');
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function findDeliveryOrder(array $args): DeliveryOrder
    {
        if (! empty($args['delivery_order_id'])) {
            return DeliveryOrder::findOrFail($args['delivery_order_id']);
        }
        if (! empty($args['do_number'])) {
            return DeliveryOrder::query()->where('do_number', $args['do_number'])->firstOrFail();
        }

        throw new RuntimeException('Pass delivery_order_id or do_number.');
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function findDebitNote(array $args): DebitNote
    {
        if (! empty($args['debit_note_id'])) {
            return DebitNote::findOrFail($args['debit_note_id']);
        }
        if (! empty($args['dn_number'])) {
            return DebitNote::query()->where('dn_number', $args['dn_number'])->firstOrFail();
        }

        throw new RuntimeException('Pass debit_note_id or dn_number.');
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function findInvoice(array $args): Invoice
    {
        if (! empty($args['invoice_id'])) {
            return Invoice::findOrFail($args['invoice_id']);
        }
        if (! empty($args['invoice_number'])) {
            return Invoice::query()->where('invoice_number', $args['invoice_number'])->firstOrFail();
        }

        throw new RuntimeException('Pass invoice_id or invoice_number.');
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function limit(array $args): int
    {
        return min(50, max(1, (int) ($args['limit'] ?? 20)));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeSalesItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? $item['unit_amount'] ?? $item['amount'] ?? 0);
            $out[] = [
                'product_id' => $item['product_id'] ?? null,
                'account_code' => $item['account_code'] ?? '4000',
                'description' => $item['description'] ?? 'Item',
                'quantity' => $qty > 0 ? $qty : 1,
                'unit_price' => $price,
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                'item_classification' => $item['item_classification'] ?? '022',
            ];
        }

        return $out;
    }
}
