import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

export default function Show({ auth, deposit, openInvoices = [] }) {
    const open = Number(deposit.open_amount ?? (deposit.amount - deposit.applied_amount));
    const apply = useForm({ invoice_id: openInvoices[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={deposit.reference || `DEP-${deposit.id}`} />
            <div className="space-y-4 min-w-0">
                <Link href={route('ar-deposits.index')} className="text-xs text-ink-muted">← Receipts & deposits</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{deposit.reference || `DEP-${deposit.id}`}</h1>
                        <p className="text-sm text-ink-muted">
                            {deposit.customer?.name} · {formatDate(deposit.payment_date)} · {deposit.status} · unapplied {formatCurrency(open, 'MYR')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream" href={route('ar-deposits.pdf', deposit.id)} target="_blank" rel="noreferrer">PDF</a>
                        {auth.permissions.includes('invoices.record-payment') && deposit.status === 'open' && (
                            <Link className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream" href={route('ar-deposits.edit', deposit.id)}>Edit</Link>
                        )}
                        {auth.permissions.includes('invoices.email') && (
                            <button type="button" className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream" onClick={() => router.post(route('ar-deposits.email', deposit.id))}>Email</button>
                        )}
                    </div>
                </div>
                <div className="bg-surface rounded-2xl border p-5 space-y-2 text-sm">
                    <div className="flex justify-between"><span>Received</span><span className="font-mono">{formatCurrency(deposit.amount, 'MYR')}</span></div>
                    <div className="flex justify-between"><span>Applied</span><span className="font-mono">{formatCurrency(deposit.applied_amount, 'MYR')}</span></div>
                    <div className="flex justify-between font-semibold"><span>Open deposit (2200)</span><span className="font-mono">{formatCurrency(open, 'MYR')}</span></div>
                    <p className="text-ink-muted">{deposit.bank_account_code}{deposit.notes ? ` · ${deposit.notes}` : ''}</p>
                </div>
                {(deposit.applications || []).length > 0 && (
                    <div className="bg-surface rounded-2xl border p-5 space-y-2">
                        <h3 className="text-sm font-semibold">Knocked off</h3>
                        {deposit.applications.map((a) => (
                            <div key={a.id} className="flex justify-between text-sm">
                                <Link className="text-terracotta" href={a.invoice_id ? route('invoices.show', a.invoice_id) : '#'}>{a.invoice?.invoice_number || `Invoice #${a.invoice_id}`}</Link>
                                <span className="font-mono">{formatCurrency(a.amount, 'MYR')}</span>
                            </div>
                        ))}
                    </div>
                )}
                {open > 0 && openInvoices.length > 0 && (
                    <form
                        className="bg-surface rounded-2xl border p-5 space-y-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply.post(route('ar-deposits.apply', deposit.id), { preserveScroll: true });
                        }}
                    >
                        <h3 className="text-sm font-semibold">Apply leftover</h3>
                        <select className="w-full border rounded-xl px-3 py-2 text-sm" value={apply.data.invoice_id} onChange={(e) => apply.setData('invoice_id', e.target.value)}>
                            {openInvoices.map((inv) => <option key={inv.id} value={inv.id}>{inv.invoice_number} · {formatCurrency(inv.balance, inv.currency)}</option>)}
                        </select>
                        <input className="w-full border rounded-xl px-3 py-2 text-sm" type="number" step="0.01" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                        <button className="px-4 py-2 rounded-xl bg-terracotta text-white text-sm font-semibold" disabled={apply.processing}>Apply</button>
                    </form>
                )}
                {open > 0 && auth.permissions.includes('invoices.record-payment') && (
                    <div className="bg-surface rounded-2xl border p-5 space-y-3">
                        <h3 className="text-sm font-semibold">Close leftover</h3>
                        <p className="text-sm text-ink-muted">Refund sends cash back from {deposit.bank_account_code}. Forfeit keeps the cash and records it as revenue (4000).</p>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="px-4 py-2 rounded-xl border text-sm font-semibold"
                                onClick={() => router.post(route('ar-deposits.refund', deposit.id), { payment_date: new Date().toISOString().slice(0, 10) })}
                            >
                                Refund leftover
                            </button>
                            <button
                                type="button"
                                className="px-4 py-2 rounded-xl border text-sm font-semibold"
                                onClick={() => router.post(route('ar-deposits.forfeit', deposit.id))}
                            >
                                Forfeit as income
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
