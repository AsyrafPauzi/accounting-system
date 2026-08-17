import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, customers = [], bankAccounts = [], customer_id = null, openInvoices = [] }) {
    const { data, setData, post, processing } = useForm({
        customer_id: customer_id || '',
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: bankAccounts[0]?.code || '1000',
        reference: '',
        notes: '',
        allocations: openInvoices.map((i) => ({ invoice_id: i.id, amount: '' })),
    });

    useEffect(() => {
        setData('allocations', openInvoices.map((i) => ({ invoice_id: i.id, amount: '' })));
    }, [openInvoices]);

    const allocated = (data.allocations || []).reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
    const leftover = Math.max(0, (Number(data.amount) || 0) - allocated);

    const setAlloc = (invoiceId, amount) => {
        setData('allocations', (data.allocations || []).map((row) => (
            Number(row.invoice_id) === Number(invoiceId) ? { ...row, amount } : row
        )));
    };

    const allocateFifo = () => {
        let remaining = Number(data.amount) || 0;
        setData('allocations', openInvoices.map((inv) => {
            const take = Math.min(remaining, Number(inv.balance) || 0);
            remaining = Math.round((remaining - take) * 100) / 100;
            return { invoice_id: inv.id, amount: take > 0 ? take.toFixed(2) : '' };
        }));
    };

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">New receipt</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Record one bank receipt, then allocate it across open invoices</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('ar-deposits.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="ar-receipt-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Save receipt'}
                    </button>
                </div>
            </div>
        }>
            <Head title="Customer receipt" />
            <form id="ar-receipt-form" className="space-y-4 min-w-0" onSubmit={(e) => { e.preventDefault(); post(route('ar-deposits.store')); }}>
                <div className="bg-surface rounded-2xl border border-border-warm p-4 sm:p-5">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="md:col-span-2">
                            <label className={labelClass}>Customer</label>
                            <select
                                className={inputClass}
                                value={data.customer_id}
                                onChange={(e) => {
                                    const id = e.target.value;
                                    setData('customer_id', id);
                                    router.get(route('ar-deposits.create'), { customer_id: id }, { preserveState: true, preserveScroll: true, only: ['openInvoices', 'customer_id'] });
                                }}
                                required
                            >
                                <option value="">Select customer…</option>
                                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Amount received</label>
                            <input type="number" step="0.01" className={`${inputClass} font-mono text-right`} placeholder="0.00" value={data.amount} onChange={(e) => setData('amount', e.target.value)} required />
                        </div>
                        <div>
                            <label className={labelClass}>Date</label>
                            <input type="date" className={inputClass} value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Bank / cash</label>
                            <select className={inputClass} value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)}>
                                {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Reference</label>
                            <input className={inputClass} placeholder="Cheque / bank ref" value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Notes</label>
                            <input className={inputClass} placeholder="Optional" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                    </div>
                </div>

                {openInvoices.length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm overflow-hidden">
                        <div className="px-4 sm:px-5 py-3 border-b border-border-warm bg-cream/50 flex justify-between items-center">
                            <h3 className="text-sm font-semibold">Allocate to invoices</h3>
                            <button type="button" className="text-sm text-terracotta font-semibold" onClick={allocateFifo}>Fill oldest first</button>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] uppercase tracking-widest text-ink-muted border-b border-border-warm">
                                    <th className="px-4 sm:px-5 py-2">Invoice</th>
                                    <th className="px-4 py-2 text-right">Open</th>
                                    <th className="px-4 sm:px-5 py-2 text-right w-40">Allocate</th>
                                </tr>
                            </thead>
                            <tbody>
                                {openInvoices.map((inv) => {
                                    const row = (data.allocations || []).find((a) => Number(a.invoice_id) === Number(inv.id)) || { amount: '' };
                                    return (
                                        <tr key={inv.id} className="border-b border-border-warm last:border-0">
                                            <td className="px-4 sm:px-5 py-2">
                                                <Link href={route('invoices.show', inv.id)} className="font-semibold text-ink hover:text-terracotta">{inv.invoice_number}</Link>
                                                <p className="text-xs text-ink-muted">Due {inv.due_date || '—'}</p>
                                            </td>
                                            <td className="px-4 py-2 text-right font-mono text-ink-muted">{formatCurrency(inv.balance, inv.currency)}</td>
                                            <td className="px-4 sm:px-5 py-2">
                                                <input type="number" step="0.01" className="w-full border border-border-warm rounded-xl px-3 py-2 text-right font-mono" value={row.amount} onChange={(e) => setAlloc(inv.id, e.target.value)} />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="px-4 sm:px-5 py-3 flex justify-between text-sm bg-cream/30">
                            <span>Allocated {formatCurrency(allocated, 'MYR')}</span>
                            <span className="font-semibold">Leftover deposit {formatCurrency(leftover, 'MYR')}</span>
                        </div>
                    </div>
                )}
                {data.customer_id && openInvoices.length === 0 && (
                    <p className="text-sm text-ink-muted">No open invoices for this customer. The full amount will sit as a deposit (2200) until you apply it later.</p>
                )}
            </form>
        </AuthenticatedLayout>
    );
}
