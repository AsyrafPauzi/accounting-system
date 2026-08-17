<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\DeliveryOrder;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\SalesOrder;

/**
 * Read-only estimate → SO → DO → invoice → CN chain. No posting.
 */
class SalesDocumentTrail
{
    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['creditNoteApplications.creditNote']);
        $salesOrderId = $this->attr($invoice, 'sales_order_id');
        $deliveryOrderId = $this->attr($invoice, 'delivery_order_id');
        $so = $salesOrderId ? SalesOrder::query()->find($salesOrderId) : null;
        $do = $deliveryOrderId ? DeliveryOrder::query()->find($deliveryOrderId) : null;
        $estimate = $this->resolveEstimate($invoice, $so);

        $steps = [];
        if ($estimate) {
            $steps[] = $this->step('estimate', $estimate->id, $estimate->estimate_number, 'estimates.show', $estimate->status);
        }
        if ($so) {
            $steps[] = $this->step('sales_order', $so->id, $so->so_number, 'sales-orders.show', $so->status);
        }
        if ($do) {
            $steps[] = $this->step('delivery_order', $do->id, $do->do_number, 'delivery-orders.show', $do->status);
        }
        $steps[] = $this->step('invoice', $invoice->id, $invoice->invoice_number, 'invoices.show', $invoice->status);

        $cns = CreditNote::query()
            ->where(function ($q) use ($invoice) {
                $q->where('invoice_id', $invoice->id);
                if (\Illuminate\Support\Facades\Schema::hasTable('credit_note_applications')) {
                    $q->orWhereIn('id', $invoice->creditNoteApplications->pluck('credit_note_id'));
                }
            })
            ->whereNull('deleted_at')
            ->get(['id', 'cn_number', 'status']);
        foreach ($cns as $cn) {
            $steps[] = $this->step('credit_note', $cn->id, $cn->cn_number, 'credit-notes.show', $cn->status);
        }

        return $steps;
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forEstimate(Estimate $estimate): array
    {
        $sos = SalesOrder::query()->where('estimate_id', $estimate->id)->get(['id', 'so_number', 'status']);
        $invoiceIds = Invoice::query()->where('estimate_id', $estimate->id)->pluck('id');
        if ($estimate->converted_invoice_id) {
            $invoiceIds->push($estimate->converted_invoice_id);
        }
        $invoices = Invoice::query()->whereIn('id', $invoiceIds->unique()->filter())->get();

        $steps = [$this->step('estimate', $estimate->id, $estimate->estimate_number, 'estimates.show', $estimate->status)];
        foreach ($sos as $so) {
            $steps[] = $this->step('sales_order', $so->id, $so->so_number, 'sales-orders.show', $so->status);
            foreach (DeliveryOrder::query()->where('sales_order_id', $so->id)->get(['id', 'do_number', 'status']) as $do) {
                $steps[] = $this->step('delivery_order', $do->id, $do->do_number, 'delivery-orders.show', $do->status);
            }
        }
        foreach ($invoices as $invoice) {
            $steps = array_merge($steps, $this->forInvoice($invoice));
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forSalesOrder(SalesOrder $order): array
    {
        $order->loadMissing(['estimate', 'deliveryOrders', 'invoices']);
        $steps = [];
        if ($order->estimate) {
            $steps[] = $this->step('estimate', $order->estimate->id, $order->estimate->estimate_number, 'estimates.show', $order->estimate->status);
        }
        $steps[] = $this->step('sales_order', $order->id, $order->so_number, 'sales-orders.show', $order->status);
        foreach ($order->deliveryOrders as $do) {
            $steps[] = $this->step('delivery_order', $do->id, $do->do_number, 'delivery-orders.show', $do->status);
        }
        foreach ($order->invoices as $invoice) {
            $steps = array_merge($steps, $this->forInvoice($invoice));
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forDeliveryOrder(DeliveryOrder $order): array
    {
        $order->loadMissing(['salesOrder.estimate', 'invoices']);
        $so = $order->salesOrder;
        $steps = [];
        if ($so?->estimate) {
            $steps[] = $this->step('estimate', $so->estimate->id, $so->estimate->estimate_number, 'estimates.show', $so->estimate->status);
        }
        if ($so) {
            $steps[] = $this->step('sales_order', $so->id, $so->so_number, 'sales-orders.show', $so->status);
        }
        $steps[] = $this->step('delivery_order', $order->id, $order->do_number, 'delivery-orders.show', $order->status);
        foreach ($order->invoices as $invoice) {
            $steps = array_merge($steps, $this->forInvoice($invoice));
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forCreditNote(CreditNote $cn): array
    {
        if ($cn->invoice_id) {
            $invoice = Invoice::query()->find($cn->invoice_id);
            if ($invoice) {
                return $this->forInvoice($invoice);
            }
        }

        return [$this->step('credit_note', $cn->id, $cn->cn_number, 'credit-notes.show', $cn->status)];
    }

    private function resolveEstimate(Invoice $invoice, ?SalesOrder $so): ?Estimate
    {
        $estimateId = $this->attr($invoice, 'estimate_id');
        if ($estimateId) {
            return Estimate::query()->find($estimateId);
        }
        if ($so?->estimate_id) {
            return Estimate::query()->find($so->estimate_id);
        }

        return null;
    }

    /**
     * Safe read when invoice was loaded with a partial column select
     * (Laravel throws MissingAttributeException otherwise).
     */
    private function attr(Invoice $invoice, string $key): mixed
    {
        $attributes = $invoice->getAttributes();
        if (! array_key_exists($key, $attributes)) {
            // Reload just this row if a caller omitted FK columns.
            $fresh = Invoice::query()->find($invoice->id, ['id', 'sales_order_id', 'delivery_order_id', 'estimate_id']);

            return $fresh?->getAttributes()[$key] ?? null;
        }

        return $attributes[$key];
    }

    /**
     * @return array{type: string, id: int, number: string, href: string|null, status: string|null}
     */
    private function step(string $type, int $id, string $number, string $route, ?string $status): array
    {
        $href = null;
        if (\Illuminate\Support\Facades\Route::has($route)) {
            $href = route($route, $id);
        }

        return compact('type', 'id', 'number', 'href', 'status');
    }

    /**
     * @param  list<array{type: string, id: int, number: string, href: string|null, status: string|null}>  $steps
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    private function uniqueSteps(array $steps): array
    {
        $seen = [];
        $out = [];
        foreach ($steps as $step) {
            $key = $step['type'].':'.$step['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $step;
        }

        return $out;
    }
}
