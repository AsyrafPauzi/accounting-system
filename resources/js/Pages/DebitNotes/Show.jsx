import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, debitNote, myinvois_gaps = [] }) {
    const currency = debitNote.currency || 'MYR';

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={debitNote.dn_number} />
            <div className="space-y-4 min-w-0">
                <Link href={route('debit-notes.index')} className="text-xs font-semibold text-ink-muted">← Debit notes</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-display">{debitNote.dn_number}</h1>
                            <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-cream">{debitNote.status}</span>
                            {debitNote.lhdn_status && debitNote.lhdn_status !== 'pending' && (
                                <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-blue-50 text-blue-800">MyInvois {debitNote.lhdn_status}</span>
                            )}
                        </div>
                        <p className="text-sm text-ink-muted mt-1">
                            {debitNote.customer?.name}
                            {' · '}{formatCurrency(debitNote.total_amount, currency)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {debitNote.invoice_id && (
                            <Link className={btn} href={route('invoices.show', debitNote.invoice_id)}>
                                Open {debitNote.invoice?.invoice_number || 'invoice'}
                            </Link>
                        )}
                        <a className={btn} href={route('debit-notes.pdf', debitNote.id)} target="_blank" rel="noreferrer">PDF</a>
                        {auth.permissions.includes('debit-notes.create') && debitNote.status !== 'void' && (
                            <Link className={btn} href={route('debit-notes.edit', debitNote.id)}>Edit</Link>
                        )}
                        {auth.permissions.includes('invoices.email') && (
                            <button type="button" className={btn} onClick={() => router.post(route('debit-notes.email', debitNote.id))}>Email</button>
                        )}
                        {debitNote.status !== 'void' && (
                            <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('debit-notes.void', debitNote.id))}>Void</button>
                        )}
                    </div>
                </div>
                <div className="bg-surface rounded-2xl border overflow-hidden">
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
                                <tr key={item.id} className="border-t">
                                    <td className="px-4 py-3">{item.description}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.quantity}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.unit_price}</td>
                                    <td className="px-4 py-3 text-right font-mono">{item.amount}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="p-4 text-right text-sm">
                        <div>Tax {formatCurrency(debitNote.tax_amount, currency)}</div>
                        <div className="text-lg font-semibold text-terracotta">Total {formatCurrency(debitNote.total_amount, currency)}</div>
                    </div>
                </div>
                <div className="bg-surface rounded-2xl border p-5 space-y-3">
                    <h3 className="text-sm font-semibold">MyInvois</h3>
                    {myinvois_gaps.length > 0 && <ul className="text-sm text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>}
                    {auth.planPermissions?.['myinvois.submit'] && !debitNote.lhdn_uuid && myinvois_gaps.length === 0 && (
                        <button type="button" className={primary} onClick={() => router.post(route('debit-notes.myinvois.submit', debitNote.id))}>Submit e-invoice</button>
                    )}
                    {debitNote.lhdn_uuid && (
                        <button type="button" className={btn} onClick={() => router.post(route('debit-notes.myinvois.refresh', debitNote.id))}>Refresh status</button>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
