<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAuditLog;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Account manager must belong to the same tenant as the authenticated user.
     */
    private function accountManagerIdRule()
    {
        return Rule::exists('users', 'id')->where('tenant_id', auth()->user()->tenant_id);
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
        $customers = Customer::all()->map(function ($customer) {
            $customer->balance = Invoice::where('customer_id', $customer->id)
                ->whereNotIn('status', ['draft', 'void'])
                ->sum(DB::raw('total_amount - amount_paid'));
            $customer->has_overdue = Invoice::where('customer_id', $customer->id)
                ->whereNotIn('status', ['draft', 'void', 'paid'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->exists();
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|unique:customers,code',
            'email' => 'required|email',
            'tin' => 'required|string', // LHDN Requirement
            'brn' => 'required|string', // SSM Requirement
            'billing_street' => 'required|string',
            'billing_city' => 'required|string',
            'billing_state' => 'required|string',
            'billing_zip' => 'required|string',
            'credit_limit' => 'required|numeric',
            'payment_terms' => 'required|integer|min:0|max:365',
            // Optional fields
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'shipping_street' => 'nullable|string',
            'shipping_city' => 'nullable|string',
            'shipping_state' => 'nullable|string',
            'shipping_zip' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'credit_hold' => 'nullable|boolean',
            'risk_rating' => 'nullable|string|in:low,medium,high',
            'segment' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:50',
            'account_manager_id' => ['nullable', $this->accountManagerIdRule()],
            'invoice_delivery_method' => 'nullable|string|in:email,none',
            'send_statement' => 'nullable|boolean',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.type' => 'nullable|string|in:billing,finance,operations',
            'contacts.*.is_primary' => 'nullable|boolean',
        ]);

        $customer = Customer::create(array_merge([
            'is_active' => true,
            'credit_hold' => false,
            'invoice_delivery_method' => 'email',
            'send_statement' => false,
        ], $validated));

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
        return redirect()->route('customers.index');
    }

    /**
     * Quick-create a customer from the invoice create page (JSON response).
     * Minimal fields; defaults applied for the rest.
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'tin' => 'required|string|max:50',
            'brn' => 'required|string|max:50',
            'code' => 'nullable|string|unique:customers,code',
            'billing_street' => 'nullable|string|max:500',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_zip' => 'nullable|string|max:20',
        ]);

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

        return response()->json(['customer' => $customer->fresh()], 201);
    }

    public function show($id)
    {
        $customer = Customer::with('contacts')->findOrFail($id);

        $invoices = Invoice::where('customer_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $balance = $invoices->whereNotIn('status', ['draft', 'void'])->sum(fn($i) => $i->total_amount - $i->amount_paid);
        $creditLimit = (float) ($customer->credit_limit ?? 0);
        $remainingLimit = $creditLimit > 0 ? max(0, $creditLimit - $balance) : null;

        $openInvoices = $invoices->whereNotIn('status', ['draft', 'void', 'paid']);
        $aging = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        foreach ($openInvoices as $inv) {
            $amount = (float) $inv->total_amount - (float) $inv->amount_paid;
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

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'invoices' => $invoices,
            'stats' => $stats,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function edit($id)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => Customer::with(['accountManager', 'contacts'])->findOrFail($id),
            'users' => $this->tenantUsersForSelect(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', Rule::unique('customers')->ignore($id)],
            'email' => 'required|email',
            'tin' => 'required|string',
            'brn' => 'required|string',
            'billing_street' => 'required|string',
            'billing_city' => 'required|string',
            'billing_state' => 'required|string',
            'billing_zip' => 'required|string',
            'billing_country' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
            'credit_limit' => 'required|numeric',
            'payment_terms' => 'required|integer|min:0|max:365',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'shipping_street' => 'nullable|string',
            'shipping_city' => 'nullable|string',
            'shipping_state' => 'nullable|string',
            'shipping_zip' => 'nullable|string',
            'shipping_country' => 'nullable|string|max:255',
            'internal_notes' => 'nullable|string',
            'credit_hold' => 'nullable|boolean',
            'risk_rating' => 'nullable|string|in:low,medium,high',
            'segment' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:50',
            'account_manager_id' => ['nullable', $this->accountManagerIdRule()],
            'invoice_delivery_method' => 'nullable|string|in:email,none',
            'send_statement' => 'nullable|boolean',
            'contacts' => 'nullable|array',
            'contacts.*.id' => 'nullable|exists:customer_contacts,id',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.type' => 'nullable|string|in:billing,finance,operations',
            'contacts.*.is_primary' => 'nullable|boolean',
        ]);

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
}