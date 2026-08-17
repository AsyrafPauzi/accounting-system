import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'block w-full border border-border-warm rounded-xl py-2.5 px-3 text-sm font-medium text-ink bg-surface focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';
const textareaClass = `${inputClass} min-h-[2.75rem] leading-snug resize-y`;

const blankItem = () => ({
    description: '',
    quantity: 1,
    unit_price: '',
    tax_rate: 8,
});

const blankRow = () => ({
    customer_id: '',
    issue_date: new Date().toISOString().split('T')[0],
    items: [blankItem()],
});

function itemTotal(item) {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    const tax = Number(item.tax_rate) || 0;
    const net = qty * price;
    return net + (net * tax) / 100;
}

function rowTotal(row) {
    return (row.items || []).reduce((sum, item) => sum + itemTotal(item), 0);
}

function rowIsReady(row) {
    if (!row.customer_id) return false;
    const items = row.items || [];
    if (items.length === 0) return false;
    return items.every((item) => item.description && Number(item.unit_price) > 0 && Number(item.quantity) > 0);
}

export default function Batch({ auth, customers = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        rows: [blankRow()],
    });

    const updateRow = (index, key, value) => {
        setData('rows', data.rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
    };

    const updateItem = (rowIndex, itemIndex, key, value) => {
        setData('rows', data.rows.map((row, i) => {
            if (i !== rowIndex) return row;
            const items = row.items.map((item, j) => (j === itemIndex ? { ...item, [key]: value } : item));
            return { ...row, items };
        }));
    };

    const addItem = (rowIndex) => {
        setData('rows', data.rows.map((row, i) => (
            i === rowIndex ? { ...row, items: [...row.items, blankItem()] } : row
        )));
    };

    const removeItem = (rowIndex, itemIndex) => {
        setData('rows', data.rows.map((row, i) => {
            if (i !== rowIndex) return row;
            if (row.items.length <= 1) return { ...row, items: [blankItem()] };
            return { ...row, items: row.items.filter((_, j) => j !== itemIndex) };
        }));
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

    const grandTotal = data.rows.reduce((sum, row) => sum + rowTotal(row), 0);
    const readyCount = data.rows.filter(rowIsReady).length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div>
                        <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Batch estimates</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            Each card is one draft quote — add line items, then create them together
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('estimates.index')}
                            className="inline-flex items-center px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            form="estimate-batch-form"
                            disabled={processing || readyCount === 0}
                            className="inline-flex items-center px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 shadow-lg"
                        >
                            {processing ? 'Creating…' : readyCount === 1 ? 'Create 1 draft' : `Create ${readyCount} drafts`}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Batch estimates" />

            <form
                id="estimate-batch-form"
                className="space-y-4 pb-8 min-w-0"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('estimates.batch.store'));
                }}
            >
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 max-w-xl">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 p-4 shadow-sm">
                        <p className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">Estimates</p>
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
                            <h3 className="text-sm font-semibold text-ink">Quote rows</h3>
                            <p className="text-xs text-ink-muted mt-0.5">Customer and issue date once — then as many items as you need</p>
                        </div>
                        <button
                            type="button"
                            onClick={addRow}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold text-terracotta border border-terracotta/30 bg-terracotta/5 hover:bg-terracotta/10"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add estimate
                        </button>
                    </div>

                    <div className="divide-y divide-border-warm">
                        {data.rows.map((row, i) => (
                            <div key={i} className="p-4 sm:p-5 space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">Estimate {i + 1}</span>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs font-medium text-ink-muted tabular-nums">
                                            {formatCurrency(rowTotal(row), 'MYR')}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => removeRow(i)}
                                            className="text-xs font-semibold text-ink-muted hover:text-terracotta"
                                            aria-label={`Remove estimate ${i + 1}`}
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                                    <div className="lg:col-span-8 min-w-0">
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
                                    <div className="lg:col-span-4 min-w-0">
                                        <label className={labelClass}>Issue date</label>
                                        <input
                                            type="date"
                                            className={inputClass}
                                            value={row.issue_date}
                                            onChange={(e) => updateRow(i, 'issue_date', e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {(row.items || []).map((item, j) => (
                                        <div key={j} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center rounded-xl border border-border-warm/60 bg-cream/30 p-3">
                                            <div className="lg:col-span-5 min-w-0">
                                                <label className={labelClass}>Description</label>
                                                <textarea
                                                    className={textareaClass}
                                                    placeholder="Item or service"
                                                    rows={2}
                                                    value={item.description}
                                                    onChange={(e) => updateItem(i, j, 'description', e.target.value)}
                                                    required
                                                />
                                            </div>
                                            <div className="lg:col-span-2 min-w-0">
                                                <label className={labelClass}>Qty</label>
                                                <input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    className={inputClass}
                                                    value={item.quantity}
                                                    onChange={(e) => updateItem(i, j, 'quantity', e.target.value)}
                                                    required
                                                />
                                            </div>
                                            <div className="lg:col-span-2 min-w-0">
                                                <label className={labelClass}>Price</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    className={inputClass}
                                                    placeholder="0.00"
                                                    value={item.unit_price}
                                                    onChange={(e) => updateItem(i, j, 'unit_price', e.target.value)}
                                                    required
                                                />
                                            </div>
                                            <div className="lg:col-span-2 min-w-0">
                                                <label className={labelClass}>Tax %</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    className={inputClass}
                                                    value={item.tax_rate}
                                                    onChange={(e) => updateItem(i, j, 'tax_rate', e.target.value)}
                                                />
                                            </div>
                                            <div className="lg:col-span-1 flex items-end justify-end min-h-[2.75rem]">
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(i, j)}
                                                    className="text-xs font-semibold text-ink-muted hover:text-terracotta py-2"
                                                    aria-label={`Remove item ${j + 1} from estimate ${i + 1}`}
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    ))}

                                    <button
                                        type="button"
                                        onClick={() => addItem(i)}
                                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-terracotta border border-terracotta/30 bg-terracotta/5 hover:bg-terracotta/10"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                                        Add item
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                    <p className="text-xs text-ink-muted">
                        Drafts appear on Estimates. Convert to invoice or sales order when the customer accepts.
                    </p>
                    <button
                        type="button"
                        onClick={addRow}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink border border-border-warm bg-surface hover:bg-cream sm:hidden"
                    >
                        Add another estimate
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
