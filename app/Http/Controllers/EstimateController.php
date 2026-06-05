<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Http\Requests\UpdateEstimateRequest;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstimateController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function index(Request $request): Response
    {
        $this->authorize('estimates.view');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $estimates = Estimate::query()
            ->select(['id', 'estimate_number', 'customer_id', 'issue_date', 'expiry_date', 'status', 'currency', 'total_amount', 'converted_invoice_id'])
            ->with(['customer:id,name'])
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('estimate_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when(in_array($status, Estimate::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = Estimate::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return Inertia::render('Estimates/Index', [
            'estimates' => $estimates,
            'filters'   => [
                'search' => $search,
                'status' => $status,
            ],
            'counts'    => $counts,
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('estimates.create');

        return Inertia::render('Estimates/Create', [
            'customers'           => $this->customerOptions(),
            'customer_id'         => $request->query('customer_id'),
            'products'            => $this->productOptions(),
            'next_estimate_number'=> $this->estimates->nextNumber(),
            'base_currency'       => $this->tenantBaseCurrency(),
        ]);
    }

    public function store(StoreEstimateRequest $request): RedirectResponse
    {
        $estimate = $this->estimates->create(
            array_merge($request->validated(), ['created_by' => $request->user()->id]),
            $request->validated()['items']
        );

        return redirect()
            ->route('estimates.show', $estimate->id)
            ->with('success', "Estimate {$estimate->estimate_number} saved as draft.");
    }

    public function show(int $id): Response
    {
        $this->authorize('estimates.view');

        $estimate = Estimate::with(['items', 'customer', 'convertedInvoice:id,invoice_number,status'])
            ->findOrFail($id);

        return Inertia::render('Estimates/Show', [
            'estimate'     => $estimate,
            'base_currency'=> $this->tenantBaseCurrency(),
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('estimates.edit');

        $estimate = Estimate::with('items')->findOrFail($id);

        if ($estimate->isConverted()) {
            return redirect()->route('estimates.show', $estimate->id)
                ->with('error', 'This estimate has been converted to an invoice and is locked.');
        }

        return Inertia::render('Estimates/Edit', [
            'estimate'     => $estimate,
            'customers'    => $this->customerOptions(),
            'products'     => $this->productOptions(),
            'base_currency'=> $this->tenantBaseCurrency(),
        ]);
    }

    public function update(UpdateEstimateRequest $request, int $id): RedirectResponse
    {
        $estimate = Estimate::findOrFail($id);

        try {
            $this->estimates->update($estimate, $request->validated(), $request->validated()['items']);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('estimates.show', $estimate->id)
            ->with('success', "Estimate {$estimate->estimate_number} updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('estimates.delete');

        $estimate = Estimate::findOrFail($id);

        if ($estimate->isConverted()) {
            return redirect()->back()->with('error', 'Converted estimates cannot be deleted — delete the resulting invoice instead.');
        }

        $estimate->delete();

        return redirect()->route('estimates.index')->with('success', 'Estimate removed.');
    }

    /**
     * Manually move an estimate's status (sent / accepted / rejected / re-open).
     * The Convert action is a separate endpoint because it produces an invoice.
     */
    public function transition(Request $request, int $id): RedirectResponse
    {
        $this->authorize('estimates.edit');

        $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(Estimate::STATUSES)],
        ]);

        $estimate = Estimate::findOrFail($id);

        try {
            $this->estimates->transition($estimate, $request->input('status'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Estimate marked as {$estimate->status}.");
    }

    /**
     * Convert an estimate into a draft invoice.
     * Sets estimate.status = 'converted' and stores converted_invoice_id.
     */
    public function convert(Request $request, int $id): RedirectResponse
    {
        $this->authorize('estimates.convert');

        $estimate = Estimate::with('items')->findOrFail($id);

        try {
            $invoice = $this->estimates->convertToInvoice($estimate, [
                'created_by' => $request->user()->id,
                'msic_code'  => $request->input('msic_code', '00000'),
                'due_date'   => $request->input('due_date'),
            ]);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.edit', $invoice->id)
            ->with('success', "Estimate {$estimate->estimate_number} converted to invoice {$invoice->invoice_number}. Review and post when ready.");
    }

    private function customerOptions(): \Illuminate\Support\Collection
    {
        return Customer::query()
            ->select(['id', 'name', 'tin'])
            ->orderBy('name')
            ->get();
    }

    private function productOptions(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->active()
            ->select(['id', 'code', 'name', 'description', 'unit_price', 'account_code', 'tax_rate'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }
}
