import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';
const sectionTitle = 'text-[10px] font-semibold uppercase tracking-wider text-ink-muted';
const lineNumberClass = 'w-full h-8 border border-border-warm rounded-lg py-1 px-1.5 text-xs font-medium font-mono tabular-nums text-ink bg-surface text-right focus:ring-1 focus:ring-terracotta [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';

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

    const paid = Number(data.amount) || 0;
    const allocated = (data.allocations || []).reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
    const leftover = Math.max(0, paid - allocated);
    const fmt = (n) => n.toLocaleString('en-MY', { minimumFractionDigits: 2 });

    const setAlloc = (billId, amount) => {
        setData('allocations', (data.allocations || []).map((row) => (
            Number(row.bill_id) === Number(billId) ? { ...row, amount } : row
        )));
    };

    const allocateFifo = () => {
        let remaining = paid;
        setData('allocations', openBills.map((bill) => {
            const take = Math.min(remaining, Number(bill.balance) || 0);
            remaining = Math.round((remaining - take) * 100) / 100;
            return { bill_id: bill.id, amount: take > 0 ? take.toFixed(2) : '' };
        }));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('ap-deposits.index')}
                    title="New supplier payment"
                    subtitle="Pay the supplier once, then allocate across open bills"
                    formId="ap-payment-form"
                    processing={processing}
                    submitLabel="Save payment"
                />
            }
        >
            <Head title="Supplier payment" />
            <form
                id="ap-payment-form"
                className="space-y-6 pb-12 min-w-0"
                onSubmit={(e) => {
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
                }}
            >
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Payment details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="md:col-span-2 min-w-0">
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
                        <div className="min-w-0">
                            <label className={labelClass}>Amount paid</label>
                            <input type="number" step="0.01" className={`${inputClass} font-mono text-right`} placeholder="0.00" value={data.amount} onChange={(e) => setData('amount', e.target.value)} required />
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Date</label>
                            <input type="date" className={inputClass} value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} />
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Bank / cash</label>
                            <select className={inputClass} value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)}>
                                {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                            </select>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Reference</label>
                            <input className={inputClass} placeholder="Cheque / bank ref" value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                        </div>
                    </div>
                </div>

                {openBills.length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                                <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Allocate to bills</h3>
                            </div>
                            <button type="button" className="text-sm font-semibold text-terracotta hover:underline" onClick={allocateFifo}>Fill oldest first</button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className={`${sectionTitle} border-b border-ink/15 bg-cream/40`}>
                                        <th className="px-6 py-3 text-left font-semibold">Bill</th>
                                        <th className="px-3 py-3 text-right font-semibold w-32">Open</th>
                                        <th className="px-6 py-3 text-right font-semibold w-40">Allocate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {openBills.map((bill) => {
                                        const row = (data.allocations || []).find((a) => Number(a.bill_id) === Number(bill.id)) || { amount: '' };
                                        return (
                                            <tr key={bill.id} className="border-b border-border-warm/60 last:border-0">
                                                <td className="px-6 py-3 align-top">
                                                    <Link href={route('bills.show', bill.id)} className="font-semibold text-ink hover:text-terracotta">{bill.bill_number}</Link>
                                                    <p className="text-xs text-ink-muted mt-0.5">Due {bill.due_date || '—'}</p>
                                                </td>
                                                <td className="px-3 py-3 text-right font-mono text-ink-muted tabular-nums align-top">{formatCurrency(bill.balance, bill.currency)}</td>
                                                <td className="px-6 py-3 align-top">
                                                    <input type="number" step="0.01" className={lineNumberClass} value={row.amount} onChange={(e) => setAlloc(bill.id, e.target.value)} />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-surface-alt border border-border-warm/80 p-6 rounded-2xl shadow-sm">
                            <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">Unapplied leftover</h4>
                            <p className="text-terracotta text-sm leading-relaxed">
                                {data.supplier_id && openBills.length === 0
                                    ? 'No open bills for this supplier. The full amount will sit as a prepaid deposit (1300) until you apply it later.'
                                    : 'Anything not allocated stays as a prepaid deposit (1300) and can be knocked off later.'}
                            </p>
                        </div>
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                            <label className={labelClass}>Notes (internal)</label>
                            <textarea
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                className={`${inputClass} resize-none h-28`}
                                placeholder="Optional — not shown on the payment PDF"
                            />
                        </div>
                    </div>
                    <div className="space-y-4 min-w-0">
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm space-y-3 overflow-hidden min-w-0">
                            <div className="flex justify-between items-baseline">
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase">Paid</span>
                                <span className="text-sm font-mono tabular-nums text-ink">{fmt(paid)}</span>
                            </div>
                            <div className="flex justify-between items-baseline">
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase">Allocated</span>
                                <span className="text-sm font-mono tabular-nums text-ink">− {fmt(allocated)}</span>
                            </div>
                            <div className="flex justify-between items-baseline pt-3 border-t border-border-warm">
                                <span className="text-eyebrow font-semibold text-ink uppercase">Leftover prepaid</span>
                                <span className="text-lg font-mono tabular-nums font-semibold text-terracotta">{fmt(leftover)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
