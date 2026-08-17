import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-3 text-sm font-medium text-ink bg-surface focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

const blankRow = () => ({
    customer_id: '',
    description: '',
    quantity: 1,
    unit_price: '',
    tax_rate: 8,
    issue_date: new Date().toISOString().split('T')[0],
});

function lineTotal(row) {
    const qty = Number(row.quantity) || 0;
    const price = Number(row.unit_price) || 0;
    const tax = Number(row.tax_rate) || 0;
    const net = qty * price;
    return net + (net * tax) / 100;
}

export default function Batch({ auth, customers = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        rows: [blankRow()],
    });

    const updateRow = (index, key, value) => {
        const rows = data.rows.map((row, i) => (i === index ? { ...row, [key]: value } : row));
        setData('rows', rows);
    };

    const addRow = () => {
        setData('rows', [
            ...data.rows,
            { ...blankRow(), customer_id: data.rows[data.rows.length - 1]?.customer_id || '' },
        ]);
    };

    const removeRow = (index) => {
        if (data.rows.length <= 1) {
            setData('rows', [blankRow()]);
            return;
        }
        setData('rows', data.rows.filter((_, i) => i !== index));
    };

    const grandTotal = data.rows.reduce((sum, row) => sum + lineTotal(row), 0);
    const readyCount = data.rows.filter((r) => r.customer_id && r.description && Number(r.unit_price) > 0).length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Batch sales orders</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            Each row creates one confirmed sales order with a single line — then deliver or invoice from the SO
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('sales-orders.index')}
                            className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            form="so-batch-form"
                            disabled={processing || readyCount === 0}
                            className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 shadow-lg"
                        >
                            {processing ? 'Creating…' : readyCount === 1 ? 'Create 1 order' : `Create ${readyCount} orders`}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Batch sales orders" />

            <form
                id="so-batch-form"
                className="space-y-4 pb-8 min-w-0"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('sales-orders.batch.store'));
                }}
            >
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 max-w-xl">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 p-4 shadow-sm">
                        <p className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">Rows</p>
                        <p className="text-xl font-display font-medium text-ink tabular-nums mt-1">{data.rows.length}</p>
                    </div>
                    <div className="bg-terracotta text-white rounded-2xl p-4 shadow-lg">
                        <p className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Ready</p>
                        <p className="text-xl font-display font-medium tabular-nums mt-1">{readyCount}</p>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm/80 p-4 shadow-sm col-span-2 sm:col-span-1">
                        <p className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">Est. total</p>
                        <p className="text-xl font-display font-medium text-ink tabular-nums mt-1">{formatCurrency(grandTotal, 'MYR')}</p>
                    </div>
                </div>

                {errors.rows && (
                    <div className="rounded-xl border border-terracotta/30 bg-terracotta/5 px-4 py-3 text-sm text-terracotta">
                        {typeof errors.rows === 'string' ? errors.rows : 'Check each row and try again.'}
                    </div>
                )}

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-5 py-3 border-b border-border-warm bg-cream/50 flex items-center justify-between gap-3">
                        <div>
                            <h3 className="text-sm font-semibold text-ink">Order rows</h3>
                            <p className="text-xs text-ink-muted mt-0.5">Customer, description, qty, price, and tax per order</p>
                        </div>
                        <button
                            type="button"
                            onClick={addRow}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold text-terracotta border border-terracotta/30 bg-terracotta/5 hover:bg-terracotta/10"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add row
                        </button>
                    </div>

                    <div className="divide-y divide-border-warm">
                        {data.rows.map((row, i) => (
                            <div key={i} className="p-4 sm:p-5 space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">Order {i + 1}</span>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs font-medium text-ink-muted tabular-nums">
                                            {formatCurrency(lineTotal(row), 'MYR')}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => removeRow(i)}
                                            className="text-xs font-semibold text-ink-muted hover:text-terracotta"
                                            aria-label={`Remove order row ${i + 1}`}
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                                    <div className="lg:col-span-3">
                                        <label className={labelClass}>Customer</label>
                                        <select
                                            className={inputClass}
                                            value={row.customer_id}
                                            onChange={(e) => updateRow(i, 'customer_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Select customer…</option>
                                            {customers.map((c) => (
                                                <option key={c.id} value={c.id}>{c.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="lg:col-span-3">
                                        <label className={labelClass}>Description</label>
                                        <input
                                            className={inputClass}
                                            placeholder="Item or service"
                                            value={row.description}
                                            onChange={(e) => updateRow(i, 'description', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="lg:col-span-2">
                                        <label className={labelClass}>Issue date</label>
                                        <input
                                            type="date"
                                            className={inputClass}
                                            value={row.issue_date}
                                            onChange={(e) => updateRow(i, 'issue_date', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="lg:col-span-1">
                                        <label className={labelClass}>Qty</label>
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            className={inputClass}
                                            value={row.quantity}
                                            onChange={(e) => updateRow(i, 'quantity', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="lg:col-span-1">
                                        <label className={labelClass}>Price</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={inputClass}
                                            placeholder="0.00"
                                            value={row.unit_price}
                                            onChange={(e) => updateRow(i, 'unit_price', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="lg:col-span-2">
                                        <label className={labelClass}>Tax %</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={inputClass}
                                            value={row.tax_rate}
                                            onChange={(e) => updateRow(i, 'tax_rate', e.target.value)}
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                    <p className="text-xs text-ink-muted">
                        Confirmed orders appear on the sales orders list. Delivery and invoicing stay on each SO.
                    </p>
                    <button
                        type="button"
                        onClick={addRow}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink border border-border-warm bg-surface hover:bg-cream sm:hidden"
                    >
                        Add another row
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
