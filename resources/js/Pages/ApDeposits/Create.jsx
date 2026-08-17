import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, suppliers = [], bankAccounts = [], supplier_id = null, openBills = [] }) {
    const { data, setData, post, processing, transform } = useForm({
        supplier_id: supplier_id || '',
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: bankAccounts[0]?.code || '1200',
        reference: '',
        notes: '',
        allocations: openBills.map((i) => ({ bill_id: i.id, amount: '' })),
    });

    useEffect(() => {
        setData('allocations', openBills.map((i) => ({ bill_id: i.id, amount: '' })));
    }, [openBills]);

    const allocated = (data.allocations || []).reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
    const leftover = Math.max(0, (Number(data.amount) || 0) - allocated);

    const setAlloc = (billId, amount) => {
        setData('allocations', (data.allocations || []).map((row) => (
            Number(row.bill_id) === Number(billId) ? { ...row, amount } : row
        )));
    };

    const allocateFifo = () => {
        let remaining = Number(data.amount) || 0;
        setData('allocations', openBills.map((bill) => {
            const take = Math.min(remaining, Number(bill.balance) || 0);
            remaining = Math.round((remaining - take) * 100) / 100;
            return { bill_id: bill.id, amount: take > 0 ? take.toFixed(2) : '' };
        }));
    };

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">New supplier payment</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Pay the supplier once, then allocate across open bills</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('ap-deposits.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="ap-payment-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Save payment'}
                    </button>
                </div>
            </div>
        }>
            <Head title="Supplier payment" />
            <form id="ap-payment-form" className="space-y-4 min-w-0" onSubmit={(e) => {
                e.preventDefault();
                transform((formData) => ({
                    ...formData,
                    allocations: (formData.allocations || [])
                        .filter((row) => {
                            const amount = Number(row.amount);
                            return Number.isFinite(amount) && amount > 0;
                        })
                        .map((row) => ({ bill_id: row.bill_id, amount: Number(row.amount) })),
                })).post(route('ap-deposits.store'));
            }}>
                <div className="bg-surface rounded-2xl border border-border-warm p-4 sm:p-5">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="md:col-span-2">
                            <label className={labelClass}>Supplier</label>
                            <select
                                className={inputClass}
                                value={data.supplier_id}
                                onChange={(e) => {
                                    const id = e.target.value;
                                    setData('supplier_id', id);
                                    router.get(route('ap-deposits.create'), { supplier_id: id }, { preserveState: true, preserveScroll: true, only: ['openBills', 'supplier_id'] });
                                }}
                                required
                            >
                                <option value="">Select supplier…</option>
                                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Amount paid</label>
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
                {openBills.length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm overflow-hidden">
                        <div className="px-4 sm:px-5 py-3 border-b border-border-warm bg-cream/50 flex justify-between items-center">
                            <h3 className="text-sm font-semibold">Allocate to bills</h3>
                            <button type="button" className="text-sm text-terracotta font-semibold" onClick={allocateFifo}>Fill oldest first</button>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] uppercase tracking-widest text-ink-muted border-b border-border-warm">
                                    <th className="px-4 sm:px-5 py-2">Bill</th>
                                    <th className="px-4 py-2 text-right">Open</th>
                                    <th className="px-4 sm:px-5 py-2 text-right w-40">Allocate</th>
                                </tr>
                            </thead>
                            <tbody>
                                {openBills.map((bill) => {
                                    const row = (data.allocations || []).find((a) => Number(a.bill_id) === Number(bill.id)) || { amount: '' };
                                    return (
                                        <tr key={bill.id} className="border-b border-border-warm last:border-0">
                                            <td className="px-4 sm:px-5 py-2">
                                                <Link href={route('bills.show', bill.id)} className="font-semibold text-ink hover:text-terracotta">{bill.bill_number}</Link>
                                                <p className="text-xs text-ink-muted">Due {bill.due_date || '—'}</p>
                                            </td>
                                            <td className="px-4 py-2 text-right font-mono text-ink-muted">{formatCurrency(bill.balance, bill.currency)}</td>
                                            <td className="px-4 sm:px-5 py-2">
                                                <input type="number" step="0.01" className="w-full border border-border-warm rounded-xl px-3 py-2 text-right font-mono" value={row.amount} onChange={(e) => setAlloc(bill.id, e.target.value)} />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="px-4 sm:px-5 py-3 flex justify-between text-sm bg-cream/30">
                            <span>Allocated {formatCurrency(allocated, 'MYR')}</span>
                            <span className="font-semibold">Leftover prepaid {formatCurrency(leftover, 'MYR')}</span>
                        </div>
                    </div>
                )}
                {data.supplier_id && openBills.length === 0 && (
                    <p className="text-sm text-ink-muted">No open bills for this supplier. The full amount will sit as a prepaid deposit (1300) until you apply it later.</p>
                )}
            </form>
        </AuthenticatedLayout>
    );
}
