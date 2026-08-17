import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';

export default function Show({ auth, debitNote }) {
    const currency = debitNote.currency || 'MYR';
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={debitNote.sdn_number} />
            <div className="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
                <Link href={route('supplier-debit-notes.index')} className="text-xs font-semibold text-ink-muted hover:text-ink">← Supplier debit notes</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-display font-medium text-ink">{debitNote.sdn_number}</h1>
                            <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-cream">{debitNote.status}</span>
                        </div>
                        <p className="text-sm text-ink-muted mt-1">
                            {debitNote.supplier?.name}
                            {debitNote.bill?.bill_number ? ` · against ${debitNote.bill.bill_number}` : ''}
                            {debitNote.issue_date ? ` · ${formatDate(debitNote.issue_date)}` : ''}
                            {' · '}{formatCurrency(debitNote.total_amount, currency)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {debitNote.bill?.id && <Link className={btn} href={route('bills.show', debitNote.bill.id)}>Open bill</Link>}
                        <a className={btn} href={route('supplier-debit-notes.pdf', debitNote.id)} target="_blank" rel="noreferrer">PDF</a>
                        {debitNote.status !== 'void' && <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('supplier-debit-notes.void', debitNote.id))}>Void</button>}
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
                            {(debitNote.items || []).map((item) => (
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
                        <div>Tax {formatCurrency(debitNote.tax_amount, currency)}</div>
                        <div className="text-lg font-semibold text-terracotta">Total {formatCurrency(debitNote.total_amount, currency)}</div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
