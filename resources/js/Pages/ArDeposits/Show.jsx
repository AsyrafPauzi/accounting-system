import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentShowLayout, {
    DocumentShowHeader,
    DocumentLines,
    DocumentTotals,
    docBtn,
    docPrimary,
    docGhost,
    field,
    headerBtn,
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, deposit, openInvoices = [], company = {} }) {
    const currency = 'MYR';
    const open = Number(deposit.open_amount ?? (deposit.amount - deposit.applied_amount));
    const canPay = auth.permissions.includes('invoices.record-payment');
    const canEmail = auth.permissions.includes('invoices.email');
    const apply = useForm({ invoice_id: openInvoices[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });
    const number = deposit.reference || `DEP-${deposit.id}`;
    const lines = [{
        id: 'receipt',
        description: 'Customer deposit / receipt',
        quantity: 1,
        unit_price: deposit.amount,
        amount: deposit.amount,
    }];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('ar-deposits.index')}
                    title={number}
                    status={deposit.status}
                    subtitle={deposit.customer?.name || 'No customer'}
                >
                    {canPay && deposit.status === 'open' && (
                        <Link href={route('ar-deposits.edit', deposit.id)} className={headerBtn}>Edit</Link>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={number} />
            <DocumentShowLayout
                company={company}
                docLabel="Customer receipt"
                docNumber={number}
                meta={[
                    { label: 'Received', value: formatDate(deposit.payment_date) },
                    deposit.bank_account_code ? { label: 'Bank', value: deposit.bank_account_code } : null,
                ].filter(Boolean)}
                partyTitle="Received from"
                partyName={deposit.customer?.name}
                partyLines={partyAddress(deposit.customer)}
                notes={deposit.notes}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Received', value: formatCurrency(deposit.amount, currency), tone: 'total' },
                            Number(deposit.applied_amount) > 0 ? { label: 'Applied', value: `−${formatCurrency(deposit.applied_amount, currency)}`, tone: 'negative' } : null,
                            Number(deposit.refunded_amount) > 0 ? { label: 'Refunded', value: `−${formatCurrency(deposit.refunded_amount, currency)}` } : null,
                            Number(deposit.forfeited_amount) > 0 ? { label: 'Forfeited', value: `−${formatCurrency(deposit.forfeited_amount, currency)}` } : null,
                            { label: 'Open deposit', value: formatCurrency(open, currency), tone: 'due' },
                        ].filter(Boolean)}
                    />
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Open deposit</p>
                            <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${open > 0 ? 'text-terracotta' : 'text-forest'}`}>
                                {formatCurrency(open, currency)}
                            </p>
                            <p className="mt-1 text-xs text-ink-muted">of {formatCurrency(deposit.amount, currency)} received · account 2250</p>
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            {canEmail && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('ar-deposits.email', deposit.id))}>Email customer</button>
                            )}
                            <a href={route('ar-deposits.pdf', deposit.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {deposit.customer_id && (
                                <Link href={route('customers.show', deposit.customer_id)} className={docBtn}>
                                    Open {deposit.customer?.name || 'customer'}
                                </Link>
                            )}
                        </div>
                        {(deposit.applications || []).length > 0 && (
                            <SidebarCard title="Knocked off">
                                {deposit.applications.map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm gap-2">
                                        {a.invoice_id ? (
                                            <Link href={route('invoices.show', a.invoice_id)} className="text-terracotta hover:underline truncate">
                                                {a.invoice?.invoice_number || `Invoice #${a.invoice_id}`}
                                            </Link>
                                        ) : (
                                            <span>{a.invoice?.invoice_number || `Invoice #${a.invoice_id}`}</span>
                                        )}
                                        <span className="font-mono tabular-nums shrink-0">{formatCurrency(a.amount, currency)}</span>
                                    </div>
                                ))}
                            </SidebarCard>
                        )}
                        {open > 0 && (
                            <SidebarCard title="Apply leftover">
                                {openInvoices.length === 0 ? (
                                    <p className="text-sm text-ink-muted">No open invoices for this customer.</p>
                                ) : (
                                    <form
                                        className="space-y-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            apply.post(route('ar-deposits.apply', deposit.id), { preserveScroll: true });
                                        }}
                                    >
                                        <select className={field} value={apply.data.invoice_id} onChange={(e) => apply.setData('invoice_id', e.target.value)}>
                                            {openInvoices.map((inv) => (
                                                <option key={inv.id} value={inv.id}>{inv.invoice_number} · {formatCurrency(inv.balance, inv.currency || currency)}</option>
                                            ))}
                                        </select>
                                        <input className={field} type="number" step="0.01" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                                        <button className={docPrimary} disabled={apply.processing}>Apply</button>
                                    </form>
                                )}
                            </SidebarCard>
                        )}
                        {open > 0 && canPay && (
                            <SidebarCard title="Close leftover">
                                <p className="text-sm text-ink-muted">Refund sends cash back from {deposit.bank_account_code}. Forfeit keeps the cash and records it as revenue (4000).</p>
                                <button
                                    type="button"
                                    className={docBtn}
                                    onClick={() => router.post(route('ar-deposits.refund', deposit.id), { payment_date: new Date().toISOString().slice(0, 10) })}
                                >
                                    Refund leftover
                                </button>
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={() => router.post(route('ar-deposits.forfeit', deposit.id))}
                                >
                                    Forfeit as income
                                </button>
                            </SidebarCard>
                        )}
                    </>
                }
            >
                <DocumentLines items={lines} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
