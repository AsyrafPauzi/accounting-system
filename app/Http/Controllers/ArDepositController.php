<?php

namespace App\Http\Controllers;

use App\Jobs\SendArDepositEmail;
use App\Models\Account;
use App\Models\ArDeposit;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ArDepositService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class ArDepositController extends Controller
{
    public function __construct(
        protected ArDepositService $deposits,
        protected InvoiceService $invoices,
    ) {}

    public function index()
    {
        $deposits = ArDeposit::query()->with('customer:id,name')->orderByDesc('id')->get();

        return Inertia::render('ArDeposits/Index', ['deposits' => $deposits]);
    }

    public function show($id)
    {
        $deposit = ArDeposit::with(['customer', 'applications.invoice:id,invoice_number,status'])->findOrFail($id);
        $openInvoices = $this->invoices->openInvoicesForCustomer((int) $deposit->customer_id);

        return Inertia::render('ArDeposits/Show', [
            'deposit'      => array_merge($deposit->toArray(), ['open_amount' => $deposit->openAmount()]),
            'openInvoices' => $openInvoices,
            'company'      => tenant()?->getCompanyDetails() ?? [],
        ]);
    }

    public function create(Request $request)
    {
        $customerId = $request->integer('customer_id') ?: null;

        return Inertia::render('ArDeposits/Create', [
            'customers'    => Customer::query()->orderBy('name')->get(['id', 'name']),
            'bankAccounts' => Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']),
            'customer_id'  => $customerId,
            'openInvoices' => $customerId ? $this->invoices->openInvoicesForCustomer($customerId) : [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'              => 'required|exists:customers,id',
            'amount'                   => 'required|numeric|min:0.01',
            'payment_date'             => 'required|date',
            'bank_account_code'        => 'required|string|exists:accounts,code',
            'reference'                => 'nullable|string|max:120',
            'notes'                    => 'nullable|string|max:2000',
            'allocations'              => 'nullable|array',
            'allocations.*.invoice_id' => 'required_with:allocations|exists:invoices,id',
            'allocations.*.amount'     => 'required_with:allocations|numeric|min:0',
        ]);

        try {
            $deposit = $this->deposits->receiveAndAllocate(
                array_merge($request->only([
                    'customer_id', 'amount', 'payment_date', 'bank_account_code', 'notes', 'reference',
                ]), ['created_by' => auth()->id()]),
                $request->input('allocations', [])
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('ar-deposits.show', $deposit->id)->with('success', 'Customer receipt recorded.');
    }

    public function edit($id)
    {
        $deposit = ArDeposit::with(['customer', 'applications'])->findOrFail($id);
        $editable = true;
        $lockReason = null;
        try {
            $this->deposits->assertEditable($deposit);
        } catch (\LogicException $e) {
            $editable = false;
            $lockReason = $e->getMessage();
        }
        $amountLocked = (float) $deposit->applied_amount > 0
            || (float) ($deposit->refunded_amount ?? 0) > 0
            || (float) ($deposit->forfeited_amount ?? 0) > 0
            || $deposit->applications->isNotEmpty();

        return Inertia::render('ArDeposits/Edit', [
            'deposit'       => $deposit,
            'editable'      => $editable,
            'lock_reason'   => $lockReason,
            'amount_locked' => $amountLocked,
            'bankAccounts'  => Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $deposit = ArDeposit::findOrFail($id);
        $request->validate([
            'payment_date'      => 'nullable|date',
            'reference'         => 'nullable|string|max:120',
            'notes'             => 'nullable|string|max:2000',
            'amount'            => 'nullable|numeric|min:0.01',
            'bank_account_code' => 'nullable|string|exists:accounts,code',
        ]);

        $data = $request->only(['payment_date', 'reference', 'notes']);
        $amountLocked = (float) $deposit->applied_amount > 0
            || (float) ($deposit->refunded_amount ?? 0) > 0
            || (float) ($deposit->forfeited_amount ?? 0) > 0
            || $deposit->applications()->exists();
        if (! $amountLocked) {
            $data = array_merge($data, $request->only(['amount', 'bank_account_code']));
        }

        try {
            $this->deposits->update($deposit, $data);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('ar-deposits.show', $deposit->id)->with('success', 'Deposit updated.');
    }

    public function downloadPdf($id)
    {
        $deposit = ArDeposit::with(['customer'])->findOrFail($id);
        $number = $deposit->reference ?: ('DEP-'.$deposit->id);
        $items = new Collection([
            (object) [
                'description' => 'Customer deposit / receipt',
                'quantity'    => 1,
                'unit_price'  => $deposit->amount,
                'amount'      => $deposit->amount,
            ],
        ]);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Customer Receipt',
            'number'     => $number,
            'issue_date' => optional($deposit->payment_date)->toDateString(),
            'customer'   => $deposit->customer,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $items,
            'tax'        => 0,
            'total'      => $deposit->amount,
            'currency'   => 'MYR',
            'notes'      => $deposit->notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Receipt-{$number}.pdf");
    }

    public function emailPdf($id)
    {
        $deposit = ArDeposit::with(['customer'])->findOrFail($id);
        $customer = $deposit->customer;
        if (! $customer) {
            return redirect()->back()->with('error', 'Customer not found.');
        }
        $recipients = [];
        if ($customer->email && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $customer->email;
        }
        if ($recipients === []) {
            return redirect()->back()->with('error', 'Customer has no email.');
        }
        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        SendArDepositEmail::dispatch($deposit->id, $recipients, $company);

        return redirect()->back()->with('success', 'Deposit email queued.');
    }

    public function apply(Request $request, $id)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount'     => 'required|numeric|min:0.01',
        ]);
        try {
            $this->deposits->applyToInvoice(
                ArDeposit::findOrFail($id),
                Invoice::findOrFail($request->invoice_id),
                (float) $request->amount
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Deposit applied to invoice.');
    }

    public function refund(Request $request, $id)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'reference'    => 'nullable|string|max:120',
        ]);
        try {
            $this->deposits->refundLeftover(
                ArDeposit::findOrFail($id),
                $request->input('payment_date'),
                $request->input('reference')
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Leftover deposit refunded.');
    }

    public function forfeit($id)
    {
        try {
            $this->deposits->forfeitLeftover(ArDeposit::findOrFail($id), now()->toDateString());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Leftover deposit kept as income.');
    }
}
