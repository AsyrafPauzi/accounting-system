import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'block w-full h-10 border border-border-warm rounded-xl px-3 text-sm font-medium text-ink bg-white focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none';
const lineControl = 'w-full h-8 border border-border-warm rounded-lg px-1.5 text-xs font-medium text-ink bg-white focus:ring-1 focus:ring-terracotta';
const lineNumber = `${lineControl} font-mono tabular-nums text-right [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
};

const today = () => new Date().toISOString().split('T')[0];
const plusDays = (days) => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().split('T')[0];
};

const blankItem = () => ({
    description: '',
    quantity: 1,
    unit_price: '',
    tax_rate: 8,
});

const blankInvoice = (notes = '') => ({
    customer_id: '',
    issue_date: today(),
    due_date: plusDays(30),
    customer_notes: notes,
    items: [blankItem()],
});

function itemNet(item) {
    return (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
}

function itemTax(item) {
    return itemNet(item) * ((Number(item.tax_rate) || 0) / 100);
}

function itemTotal(item) {
    return itemNet(item) + itemTax(item);
}

function invoiceTotals(row) {
    const items = row.items || [];
    const subtotal = items.reduce((sum, item) => sum + itemNet(item), 0);
    const tax = items.reduce((sum, item) => sum + itemTax(item), 0);
    return { subtotal, tax, total: subtotal + tax };
}

function rowIsReady(row) {
    if (!row.customer_id) return false;
    const items = row.items || [];
    if (items.length === 0) return false;
    return items.every((item) => item.description && Number(item.unit_price) > 0 && Number(item.quantity) > 0);
}

export default function Batch({ auth, customers = [], default_customer_notes = '' }) {
    const customerList = Array.isArray(customers) ? customers : [];
    const { data, setData, post, processing, errors } = useForm({
        rows: [blankInvoice(default_customer_notes)],
    });

    const updateRow = (index, key, value) => {
        setData('rows', data.rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
    };

    const updateItem = (rowIndex, itemIndex, key, value) => {
        setData('rows', data.rows.map((row, i) => {
            if (i !== rowIndex) return row;
            return { ...row, items: row.items.map((item, j) => (j === itemIndex ? { ...item, [key]: value } : item)) };
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

    const addInvoice = () => {
        const last = data.rows[data.rows.length - 1];
        setData('rows', [
            ...data.rows,
            { ...blankInvoice(default_customer_notes), customer_id: last?.customer_id || '' },
        ]);
    };

    const removeInvoice = (index) => {
        if (data.rows.length <= 1) {
            setData('rows', [blankInvoice(default_customer_notes)]);
            return;
        }
        setData('rows', data.rows.filter((_, i) => i !== index));
    };

    const grandTotal = data.rows.reduce((sum, row) => sum + invoiceTotals(row).total, 0);
    const readyCount = data.rows.filter(rowIsReady).length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div className="flex items-center gap-2 min-w-0">
                        <Link href={route('invoices.index')} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200 shrink-0">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="min-w-0">
                            <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Batch invoices</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">
                                Each card is one draft invoice — add lines, then create them together
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 shrink-0">
                        <Link
                            href={route('invoices.index')}
                            className="inline-flex items-center px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            form="invoice-batch-form"
                            disabled={processing || readyCount === 0}
                            className="inline-flex items-center px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50"
                        >
                            {processing ? 'Creating…' : readyCount === 1 ? 'Create 1 draft' : `Create ${readyCount} drafts`}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Batch invoices" />

            <form
                id="invoice-batch-form"
                className="space-y-5 pb-8 min-w-0"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('invoices.batch.store'));
                }}
            >
                {errors.rows && (
                    <div className="rounded-xl border border-terracotta/30 bg-terracotta/5 px-4 py-3 text-sm text-terracotta">
                        {typeof errors.rows === 'string' ? errors.rows : 'Check each invoice and try again.'}
                    </div>
                )}

                {data.rows.map((row, i) => {
                    const totals = invoiceTotals(row);
                    return (
                        <article key={i} className="bg-white rounded-2xl border border-border-warm/70 shadow-[0_8px_30px_rgba(28,25,23,0.06)] overflow-hidden">
                            <div className="h-1 bg-terracotta" />

                            <div className="px-5 sm:px-7 pt-5 pb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                <div>
                                    <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink-muted">Invoice {i + 1}</p>
                                    <p className="mt-1 text-lg font-display font-medium text-ink">Draft</p>
                                </div>
                                <div className="flex items-start gap-4">
                                    <div className="text-left sm:text-right">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Amount</p>
                                        <p className="mt-0.5 text-xl font-display font-medium tabular-nums text-ink">{formatCurrency(totals.total, 'MYR')}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeInvoice(i)}
                                        className="p-2 rounded-xl text-ink-muted hover:text-terracotta hover:bg-cream"
                                        aria-label={`Remove invoice ${i + 1}`}
                                    >
                                        <Icons.Trash />
                                    </button>
                                </div>
                            </div>

                            <div className="px-5 sm:px-7 pb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4">
                                <div className="lg:col-span-6 min-w-0">
                                    <label className={labelClass}>Bill to</label>
                                    <select
                                        className={inputClass}
                                        value={row.customer_id}
                                        onChange={(e) => updateRow(i, 'customer_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Select customer…</option>
                                        {customerList.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="lg:col-span-3 min-w-0">
                                    <label className={labelClass}>Issued</label>
                                    <input
                                        type="date"
                                        className={inputClass}
                                        value={row.issue_date}
                                        onChange={(e) => updateRow(i, 'issue_date', e.target.value)}
                                        required
                                    />
                                </div>
                                <div className="lg:col-span-3 min-w-0">
                                    <label className={labelClass}>Due</label>
                                    <input
                                        type="date"
                                        className={inputClass}
                                        value={row.due_date}
                                        onChange={(e) => updateRow(i, 'due_date', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted border-y border-ink/10 bg-cream/40">
                                            <th className="px-5 sm:px-7 py-2.5 text-left">Description</th>
                                            <th className="px-2 py-2.5 text-right w-20">Qty</th>
                                            <th className="px-2 py-2.5 text-right w-28">Price</th>
                                            <th className="px-2 py-2.5 text-right w-20">Tax</th>
                                            <th className="px-3 py-2.5 text-right w-28">Amount</th>
                                            <th className="px-3 sm:px-5 py-2.5 w-10" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(row.items || []).map((item, j) => (
                                            <tr key={j} className="border-b border-border-warm/60 last:border-0 group">
                                                <td className="px-5 sm:px-7 py-2 align-middle">
                                                    <textarea
                                                        className="block w-full min-w-0 h-8 border border-border-warm rounded-lg py-1.5 px-2 text-xs leading-4 font-medium text-ink bg-white placeholder-ink-muted/60 focus:ring-1 focus:ring-terracotta resize-y"
                                                        placeholder="Item or service"
                                                        rows={1}
                                                        value={item.description}
                                                        onChange={(e) => updateItem(i, j, 'description', e.target.value)}
                                                        required
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-middle">
                                                    <input
                                                        type="number"
                                                        min="0.01"
                                                        step="0.01"
                                                        className={`${lineNumber} text-center`}
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(i, j, 'quantity', e.target.value)}
                                                        required
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-middle">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className={lineNumber}
                                                        placeholder="0.00"
                                                        value={item.unit_price}
                                                        onChange={(e) => updateItem(i, j, 'unit_price', e.target.value)}
                                                        required
                                                    />
                                                </td>
                                                <td className="px-2 py-2 align-middle">
                                                    <select
                                                        className={`${lineControl} text-center tabular-nums`}
                                                        value={item.tax_rate}
                                                        onChange={(e) => updateItem(i, j, 'tax_rate', e.target.value)}
                                                    >
                                                        <option value="0">0%</option>
                                                        <option value="6">6%</option>
                                                        <option value="8">8%</option>
                                                        <option value="16">16%</option>
                                                    </select>
                                                </td>
                                                <td className="px-3 py-2 align-middle text-right font-mono text-xs tabular-nums text-ink">
                                                    {itemNet(item).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                                </td>
                                                <td className="px-3 sm:px-5 py-2 align-middle text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(i, j)}
                                                        className="inline-flex items-center justify-center h-8 w-8 text-ink-muted hover:text-terracotta opacity-0 group-hover:opacity-100"
                                                        aria-label={`Remove line ${j + 1} from invoice ${i + 1}`}
                                                    >
                                                        <Icons.Trash />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="px-5 sm:px-7 py-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 border-t border-border-warm/70">
                                <div className="space-y-3 min-w-0 sm:max-w-sm flex-1">
                                    <button
                                        type="button"
                                        onClick={() => addItem(i)}
                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-terracotta hover:text-terracotta-dark"
                                    >
                                        <Icons.Plus /> Add line
                                    </button>
                                    <div>
                                        <label className={labelClass}>Notes</label>
                                        <textarea
                                            className={`${inputClass} h-20 py-2 resize-y`}
                                            placeholder="Shown on the invoice PDF"
                                            value={row.customer_notes}
                                            onChange={(e) => updateRow(i, 'customer_notes', e.target.value)}
                                        />
                                    </div>
                                </div>
                                <dl className="w-full sm:w-56 space-y-1.5 text-sm shrink-0">
                                    <div className="flex justify-between gap-6 text-ink-muted">
                                        <dt>Subtotal</dt>
                                        <dd className="font-mono tabular-nums text-ink">{formatCurrency(totals.subtotal, 'MYR')}</dd>
                                    </div>
                                    <div className="flex justify-between gap-6 text-ink-muted">
                                        <dt>Tax</dt>
                                        <dd className="font-mono tabular-nums text-ink">{formatCurrency(totals.tax, 'MYR')}</dd>
                                    </div>
                                    <div className="flex justify-between gap-6 pt-2 border-t border-ink/15 font-semibold text-ink">
                                        <dt>Total</dt>
                                        <dd className="font-mono tabular-nums">{formatCurrency(totals.total, 'MYR')}</dd>
                                    </div>
                                </dl>
                            </div>
                        </article>
                    );
                })}

                <button
                    type="button"
                    onClick={addInvoice}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-3.5 rounded-2xl border border-dashed border-border-warm bg-white/60 text-sm font-semibold text-ink-muted hover:text-terracotta hover:border-terracotta/40 hover:bg-white"
                >
                    <Icons.Plus /> Add another invoice
                </button>

                <p className="text-xs text-ink-muted">
                    Drafts appear on Invoices. Post to the ledger and email from each invoice.
                </p>
            </form>
        </AuthenticatedLayout>
    );
}
