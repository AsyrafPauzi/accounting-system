<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringInvoiceRequest;
use App\Http\Requests\UpdateRecurringInvoiceRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringInvoiceController extends Controller
{
    public function __construct(private RecurringInvoiceService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('recurring-invoices.view');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $templates = RecurringInvoice::query()
            ->with(['customer:id,name', 'lastGeneratedInvoice:id,invoice_number,status'])
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'paused', fn ($q) => $q->where('is_active', false))
            ->when($status === 'due', fn ($q) => $q->due())
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all'    => RecurringInvoice::count(),
            'active' => RecurringInvoice::where('is_active', true)->count(),
            'paused' => RecurringInvoice::where('is_active', false)->count(),
            'due'    => RecurringInvoice::query()->due()->count(),
        ];

        return Inertia::render('RecurringInvoices/Index', [
            'templates'     => $templates,
            'filters'       => ['search' => $search, 'status' => $status],
            'counts'        => $counts,
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('recurring-invoices.create');

        return Inertia::render('RecurringInvoices/Create', [
            'customers'     => $this->customerOptions(),
            'customer_id'   => $request->query('customer_id'),
            'products'      => $this->productOptions(),
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function store(StoreRecurringInvoiceRequest $request): RedirectResponse
    {
        $template = $this->service->create(
            array_merge($request->validated(), ['created_by' => $request->user()->id]),
            $request->validated()['items']
        );

        return redirect()
            ->route('recurring-invoices.index')
            ->with('success', "Recurring template saved. First invoice will be generated on {$template->next_run_date->toFormattedDateString()}.");
    }

    public function edit(int $id): Response
    {
        $this->authorize('recurring-invoices.edit');

        $template = RecurringInvoice::with('items')->findOrFail($id);

        return Inertia::render('RecurringInvoices/Edit', [
            'template'      => $template,
            'customers'     => $this->customerOptions(),
            'products'      => $this->productOptions(),
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function update(UpdateRecurringInvoiceRequest $request, int $id): RedirectResponse
    {
        $template = RecurringInvoice::findOrFail($id);
        $this->service->update($template, $request->validated(), $request->validated()['items']);

        return redirect()
            ->route('recurring-invoices.index')
            ->with('success', 'Recurring template updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('recurring-invoices.delete');

        $template = RecurringInvoice::findOrFail($id);
        $template->delete();

        return redirect()
            ->route('recurring-invoices.index')
            ->with('success', 'Recurring template removed. Invoices already generated remain untouched.');
    }

    /**
     * Toggle active/paused. Pausing skips the daily cron until reactivated.
     */
    public function toggle(int $id): RedirectResponse
    {
        $this->authorize('recurring-invoices.edit');

        $template = RecurringInvoice::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        return redirect()->back()->with(
            'success',
            $template->is_active ? 'Recurring template resumed.' : 'Recurring template paused.'
        );
    }

    /**
     * Manually generate an invoice from a template right now, without waiting
     * for the cron. Useful for "first invoice today" or testing.
     */
    public function runNow(int $id): RedirectResponse
    {
        $this->authorize('recurring-invoices.run');

        $template = RecurringInvoice::with('items')->findOrFail($id);

        try {
            $invoice = $this->service->generateOne($template);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route($invoice->status === 'draft' ? 'invoices.edit' : 'invoices.show', $invoice->id)
            ->with('success', $invoice->status === 'draft'
                ? "Draft invoice {$invoice->invoice_number} created from recurring template. Review and post when ready."
                : "Invoice {$invoice->invoice_number} generated and posted.");
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
            ->select(['id', 'code', 'name', 'description', 'unit_price', 'account_code', 'tax_rate', 'classification_code'])
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
