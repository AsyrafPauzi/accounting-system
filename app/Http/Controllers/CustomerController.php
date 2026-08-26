<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAuditLog;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\CustomerPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * Account manager must belong to the same tenant as the authenticated user.
     */
    private function accountManagerIdRule()
    {
        return Rule::exists(User::class, 'id')->where('tenant_id', auth()->user()->tenant_id);
    }

    private function tenantUsersForSelect()
    {
        return User::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function index()
    {
        $outstandingByCustomer = $this->invoiceService->outstandingByCustomer();

        $customers = Customer::all()->map(function ($customer) use ($outstandingByCustomer) {
            $customer->balance = $outstandingByCustomer[$customer->id] ?? 0.0;
            $customer->has_overdue = Invoice::where('customer_id', $customer->id)
                ->whereNotIn('status', ['draft', 'void', 'paid'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->exists();
            $customer->delete_blocked_reason = $customer->deletionBlockedReason();
            $customer->can_delete = $customer->delete_blocked_reason === null;

            return $customer;
        });

        return Inertia::render('Customers/Index', ['customers' => $customers]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create', [
            'users' => $this->tenantUsersForSelect(),
        ]);
    }

    public function store(\App\Http\Requests\StoreCustomerRequest $request)
    {
        // Startup (Free) plan caps active customers at 5. The cap is silent
        // on every other plan and on self-hosted (license-driven).
        if (\App\Support\PlanCap::customerCapHit()) {
            return redirect()->route('customers.index')->with(
                'error',
                'Your plan only allows '.\App\Support\PlanCap::STARTUP_CUSTOMER_CAP.' customers. Upgrade to Solo or higher for unlimited customers.'
            );
        }

        $validated = $request->validated();
        $customerData = collect($validated)->except('contacts')->all();

        $customer = Customer::create(array_merge([
            'is_active' => true,
            'credit_hold' => false,
            'invoice_delivery_method' => 'email',
            'send_statement' => false,
        ], $customerData));

        if (!empty($validated['contacts'])) {
            foreach ($validated['contacts'] as $c) {
                $customer->contacts()->create([
                    'name' => $c['name'] ?? null,
                    'email' => $c['email'] ?? null,
                    'phone' => $c['phone'] ?? null,
                    'type' => $c['type'] ?? 'billing',
                    'is_primary' => $c['is_primary'] ?? false,
                ]);
            }
        }
        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    /**
     * Quick-create a customer from the invoice create page (JSON response).
     * Minimal fields; defaults applied for the rest.
     */
    public function quickStore(\App\Http\Requests\QuickStoreCustomerRequest $request)
    {
        if (\App\Support\PlanCap::customerCapHit()) {
            return back()->with(
                'error',
                'Your plan only allows '.\App\Support\PlanCap::STARTUP_CUSTOMER_CAP.' customers. Upgrade to Solo or higher for unlimited customers.'
            );
        }

        $validated = $request->validated();

        $code = $validated['code'] ?? ('CUST-' . str_pad((string) (Customer::max('id') + 1), 4, '0', STR_PAD_LEFT));
        $customer = Customer::create(array_merge([
            'code' => $code,
            'billing_street' => $validated['billing_street'] ?? null,
            'billing_city' => $validated['billing_city'] ?? 'Kuala Lumpur',
            'billing_state' => $validated['billing_state'] ?? null,
            'billing_zip' => $validated['billing_zip'] ?? null,
            'billing_country' => 'Malaysia',
            'shipping_street' => null,
            'shipping_city' => null,
            'shipping_state' => null,
            'shipping_zip' => null,
            'shipping_country' => 'Malaysia',
            'credit_limit' => 5000,
            'payment_terms' => 30,
            'currency' => 'MYR',
            'is_active' => true,
            'credit_hold' => false,
            'invoice_delivery_method' => 'email',
            'send_statement' => false,
        ], [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'tin' => $validated['tin'],
            'brn' => $validated['brn'],
        ]));

        return back()->with('success', 'Customer "' . $customer->name . '" created and selected.')->with('new_customer_id', $customer->id);
    }

    public function show($id)
    {
        $customer = Customer::with('contacts')->findOrFail($id);

        $invoices = Invoice::where('customer_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $balance = $this->invoiceService->sumOutstanding(
            $invoices->whereNotIn('status', ['draft', 'void'])
        );
        $creditLimit = (float) ($customer->credit_limit ?? 0);
        $remainingLimit = $creditLimit > 0 ? max(0, $creditLimit - $balance) : null;

        $openInvoices = $invoices->whereNotIn('status', ['draft', 'void', 'paid']);
        $aging = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        foreach ($openInvoices as $inv) {
            $amount = max(0, $this->invoiceService->remainingBalance($inv));
            if ($amount <= 0) {
                continue;
            }
            $due = $inv->due_date ? \Carbon\Carbon::parse($inv->due_date) : null;
            if (!$due) {
                $aging['0_30'] += $amount;
                continue;
            }
            $daysOverdue = (int) now()->startOfDay()->diffInDays($due, false);
            if ($daysOverdue >= -30) $aging['0_30'] += $amount;
            elseif ($daysOverdue >= -60) $aging['31_60'] += $amount;
            elseif ($daysOverdue >= -90) $aging['61_90'] += $amount;
            else $aging['90_plus'] += $amount;
        }

        $stats = [
            'total_invoiced' => $invoices->whereNotIn('status', ['draft', 'void'])->sum('total_amount'),
            'total_paid' => $invoices->sum('amount_paid'),
            'balance' => $balance,
            'remaining_limit' => $remainingLimit,
            'aging' => $aging,
        ];

        $auditLogs = $customer->auditLogs()->with('user')->latest('created_at')->limit(50)->get();

        $deleteBlockedReason = $customer->deletionBlockedReason();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'invoices' => $invoices,
            'stats' => $stats,
            'auditLogs' => $auditLogs,
            'can_delete_customer' => $deleteBlockedReason === null,
            'delete_blocked_reason' => $deleteBlockedReason,
            'myinvois_gaps' => \App\Services\MyInvoisService::customerGaps($customer),
        ]);
    }

    public function issuePortalLink(int $id)
    {
        $this->authorize('customers.view');

        $customer = Customer::findOrFail($id);
        $url = app(CustomerPortalService::class)->urlForCustomer($customer, auth()->id());

        return redirect()->back()->with([
            'success'    => 'Customer portal link ready — share it with '.$customer->name.'.',
            'portal_url' => $url,
        ]);
    }

    public function edit($id)
    {
        $customer = Customer::with(['accountManager', 'contacts'])->findOrFail($id);
        $deleteBlockedReason = $customer->deletionBlockedReason();

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
            'users' => $this->tenantUsersForSelect(),
            'can_delete_customer' => $deleteBlockedReason === null,
            'delete_blocked_reason' => $deleteBlockedReason,
        ]);
    }

    public function update(\App\Http\Requests\UpdateCustomerRequest $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validated();

        $toUpdate = collect($validated)->except('contacts')->all();

        $creditRiskFields = ['credit_limit', 'payment_terms', 'credit_hold', 'risk_rating', 'segment'];
        if (!auth()->user()->canEditCreditAndRisk()) {
            foreach ($creditRiskFields as $key) {
                unset($toUpdate[$key]);
            }
        }

        $auditFields = ['credit_limit', 'payment_terms', 'credit_hold', 'risk_rating', 'segment', 'tin', 'brn', 'is_active'];
        foreach ($auditFields as $field) {
            if (!array_key_exists($field, $toUpdate)) continue;
            $old = $customer->getRawOriginal($field);
            $new = $toUpdate[$field];
            if ((string) $old !== (string) $new) {
                CustomerAuditLog::create([
                    'customer_id' => $customer->id,
                    'user_id' => auth()->id(),
                    'field' => $field,
                    'old_value' => $old === null ? '' : (string) $old,
                    'new_value' => $new === null ? '' : (string) $new,
                ]);
            }
        }

        $customer->update($toUpdate);

        if (array_key_exists('contacts', $validated)) {
            $contactIds = [];
            foreach ($validated['contacts'] as $c) {
                $attrs = [
                    'name' => $c['name'] ?? null,
                    'email' => $c['email'] ?? null,
                    'phone' => $c['phone'] ?? null,
                    'type' => $c['type'] ?? 'billing',
                    'is_primary' => $c['is_primary'] ?? false,
                ];
                if (!empty($c['id'])) {
                    $contact = $customer->contacts()->find($c['id']);
                    if ($contact) {
                        $contact->update($attrs);
                        $contactIds[] = $contact->id;
                    }
                } else {
                    $contactIds[] = $customer->contacts()->create($attrs)->id;
                }
            }
            $customer->contacts()->whereNotIn('id', $contactIds)->delete();
        }

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    /**
     * Soft-delete a customer when they have no blocking relations (invoices, credit notes, subsidiaries).
     */
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);

        if ($reason = $customer->deletionBlockedReason()) {
            return redirect()->back()->with('error', $reason);
        }

        DB::transaction(function () use ($customer) {
            $customer->contacts()->delete();
            $customer->delete();
        });

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
}