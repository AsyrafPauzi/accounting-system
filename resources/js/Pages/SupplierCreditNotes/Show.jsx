import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';
const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Show({ auth, creditNote, openBills = [], bankAccounts = [] }) {
    const currency = creditNote.currency || 'MYR';
    const open = Number(creditNote.open_amount ?? 0);
    const refund = useForm({
        amount: open > 0 ? open.toFixed(2) : '',
        payment_date: new Date().toISOString().slice(0, 10),
        bank_account_code: bankAccounts[0]?.code || '',
        reference: '',
    });
    const apply = useForm({ bill_id: openBills[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={creditNote.scn_number} />
            <div className="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
                <Link href={route('supplier-credit-notes.index')} className="text-xs font-semibold text-ink-muted hover:text-ink">← Supplier credit notes</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-display font-medium text-ink">{creditNote.scn_number}</h1>
                            <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-cream">{creditNote.status}</span>
                        </div>
                        <p className="text-sm text-ink-muted mt-1">
                            {creditNote.supplier?.name}
                            {creditNote.bill?.bill_number ? ` · against ${creditNote.bill.bill_number}` : ''}
                            {creditNote.issue_date ? ` · ${formatDate(creditNote.issue_date)}` : ''}
                            {' · open '}{formatCurrency(open, currency)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a className={btn} href={route('supplier-credit-notes.pdf', creditNote.id)} target="_blank" rel="noreferrer">PDF</a>
                        {creditNote.status !== 'void' && <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('supplier-credit-notes.void', creditNote.id))}>Void</button>}
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/50 text-[10px] uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Description</th>
                                <th className="px-3 py-3 text-right">Qty</th>
                                <th className="px-3 py-3 text-right">Price</th>
                                <th className="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(creditNote.items || []).map((item) => (
                                <tr key={item.id} className="border-t border-border-warm">
                                    <td className="px-4 py-3">{item.description}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.quantity}</td>
                                    <td className="px-3 py-3 text-right font-mono">{formatCurrency(item.unit_price, currency)}</td>
                                    <td className="px-4 py-3 text-right font-mono">{formatCurrency(item.amount, currency)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="p-4 text-right text-sm space-y-1">
                        <div>Tax {formatCurrency(creditNote.tax_amount, currency)}</div>
                        <div className="text-lg font-semibold text-terracotta">Total {formatCurrency(creditNote.total_amount, currency)}</div>
                        <div className="text-ink-muted">Open {formatCurrency(open, currency)}</div>
                    </div>
                </div>

                {creditNote.status !== 'void' && open > 0 && (
                    <div className="grid md:grid-cols-2 gap-4">
                        <form className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3" onSubmit={(e) => { e.preventDefault(); apply.post(route('supplier-credit-notes.apply', creditNote.id)); }}>
                            <h3 className="text-sm font-semibold">Apply to bill</h3>
                            {openBills.length > 0 ? (
                                <>
                                    <div>
                                        <label className={labelClass}>Bill</label>
                                        <select className={inputClass} value={apply.data.bill_id} onChange={(e) => apply.setData('bill_id', e.target.value)}>
                                            {openBills.map((b) => <option key={b.id} value={b.id}>{b.bill_number}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className={labelClass}>Amount</label>
                                        <input type="number" min="0.01" step="0.01" className={inputClass} value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                                    </div>
                                    <button className={primary} disabled={apply.processing}>Apply</button>
                                </>
                            ) : <p className="text-sm text-ink-muted">No open bills for this supplier.</p>}
                        </form>
                        <form className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3" onSubmit={(e) => { e.preventDefault(); refund.post(route('supplier-credit-notes.refund', creditNote.id)); }}>
                            <h3 className="text-sm font-semibold">Refund leftover to bank</h3>
                            <div>
                                <label className={labelClass}>Amount</label>
                                <input type="number" min="0.01" step="0.01" className={inputClass} value={refund.data.amount} onChange={(e) => refund.setData('amount', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Payment date</label>
                                <input type="date" className={inputClass} value={refund.data.payment_date} onChange={(e) => refund.setData('payment_date', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Bank account</label>
                                <select className={inputClass} value={refund.data.bank_account_code} onChange={(e) => refund.setData('bank_account_code', e.target.value)}>
                                    {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={labelClass}>Reference (optional)</label>
                                <input className={inputClass} value={refund.data.reference} onChange={(e) => refund.setData('reference', e.target.value)} />
                            </div>
                            <button className={primary} disabled={refund.processing}>Refund</button>
                        </form>
                    </div>
                )}
                {(creditNote.refunds || []).map((r) => (
                    <div key={r.id} className="flex justify-between text-sm text-ink-muted">
                        <span>{formatDate(r.payment_date)} · {r.bank_account_code}</span>
                        <span className="font-mono">{formatCurrency(r.amount, currency)}</span>
                    </div>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
