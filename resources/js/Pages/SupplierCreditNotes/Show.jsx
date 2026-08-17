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
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, creditNote, openBills = [], bankAccounts = [], company = {} }) {
    const currency = creditNote.currency || 'MYR';
    const open = Number(creditNote.open_amount ?? 0);
    const isVoid = creditNote.status === 'void';
    const refund = useForm({
        amount: open > 0 ? open.toFixed(2) : '',
        payment_date: new Date().toISOString().slice(0, 10),
        bank_account_code: bankAccounts[0]?.code || '',
        reference: '',
    });
    const apply = useForm({ bill_id: openBills[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('supplier-credit-notes.index')}
                    title={creditNote.scn_number}
                    status={creditNote.status}
                    subtitle={creditNote.supplier?.name || 'No supplier'}
                />
            }
        >
            <Head title={creditNote.scn_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Supplier credit note"
                docNumber={creditNote.scn_number}
                meta={[
                    { label: 'Issued', value: formatDate(creditNote.issue_date) },
                    creditNote.bill?.bill_number ? { label: 'Against', value: creditNote.bill.bill_number } : null,
                ].filter(Boolean)}
                partyTitle="Supplier"
                partyName={creditNote.supplier?.name}
                partyLines={partyAddress(creditNote.supplier)}
                notes={creditNote.notes}
                footer={creditNote.reason_description ? (
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Reason</p>
                        <p className="mt-1.5 text-sm text-ink-muted leading-relaxed">{creditNote.reason_description}</p>
                    </div>
                ) : null}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(creditNote.amount_before_tax ?? (Number(creditNote.total_amount) - Number(creditNote.tax_amount || 0)), currency) },
                            { label: 'Tax', value: formatCurrency(creditNote.tax_amount, currency) },
                            { label: 'Total', value: formatCurrency(creditNote.total_amount, currency), tone: 'total' },
                            { label: 'Open credit', value: formatCurrency(open, currency), tone: 'due' },
                        ]}
                    />
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Open credit</p>
                            <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${open > 0 ? 'text-terracotta' : 'text-forest'}`}>
                                {formatCurrency(open, currency)}
                            </p>
                            <p className="mt-1 text-xs text-ink-muted">of {formatCurrency(creditNote.total_amount, currency)}</p>
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            <a href={route('supplier-credit-notes.pdf', creditNote.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {creditNote.bill?.id && (
                                <Link href={route('bills.show', creditNote.bill.id)} className={docBtn}>Open {creditNote.bill.bill_number}</Link>
                            )}
                            {!isVoid && (
                                <button type="button" className={`${docGhost} text-terracotta hover:text-terracotta`} onClick={() => router.post(route('supplier-credit-notes.void', creditNote.id))}>Void credit note</button>
                            )}
                        </div>
                        {(creditNote.applications || []).length > 0 && (
                            <SidebarCard title="Applied to bills">
                                {creditNote.applications.map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm gap-2">
                                        {a.bill_id ? (
                                            <Link href={route('bills.show', a.bill_id)} className="text-terracotta hover:underline truncate">
                                                {a.bill?.bill_number || `Bill #${a.bill_id}`}
                                            </Link>
                                        ) : (
                                            <span>{a.bill?.bill_number || `Bill #${a.bill_id}`}</span>
                                        )}
                                        <span className="font-mono tabular-nums shrink-0">{formatCurrency(a.amount, currency)}</span>
                                    </div>
                                ))}
                            </SidebarCard>
                        )}
                        {!isVoid && open > 0 && (
                            <SidebarCard title="Apply to bill">
                                {openBills.length > 0 ? (
                                    <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); apply.post(route('supplier-credit-notes.apply', creditNote.id), { preserveScroll: true }); }}>
                                        <select className={field} value={apply.data.bill_id} onChange={(e) => apply.setData('bill_id', e.target.value)}>
                                            {openBills.map((b) => <option key={b.id} value={b.id}>{b.bill_number}</option>)}
                                        </select>
                                        <input className={field} type="number" min="0.01" step="0.01" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                                        <button className={docPrimary} disabled={apply.processing}>Apply</button>
                                    </form>
                                ) : (
                                    <p className="text-sm text-ink-muted">No open bills for this supplier.</p>
                                )}
                            </SidebarCard>
                        )}
                        {!isVoid && open > 0 && (
                            <SidebarCard title="Refund leftover">
                                <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); refund.post(route('supplier-credit-notes.refund', creditNote.id), { preserveScroll: true }); }}>
                                    <input className={field} type="number" min="0.01" step="0.01" value={refund.data.amount} onChange={(e) => refund.setData('amount', e.target.value)} />
                                    <input className={field} type="date" value={refund.data.payment_date} onChange={(e) => refund.setData('payment_date', e.target.value)} />
                                    <select className={field} value={refund.data.bank_account_code} onChange={(e) => refund.setData('bank_account_code', e.target.value)}>
                                        {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                                    </select>
                                    <input className={field} placeholder="Reference" value={refund.data.reference} onChange={(e) => refund.setData('reference', e.target.value)} />
                                    <button className={docPrimary} disabled={refund.processing}>Refund to bank</button>
                                </form>
                            </SidebarCard>
                        )}
                        {(creditNote.refunds || []).length > 0 && (
                            <SidebarCard title="Refunds">
                                {creditNote.refunds.map((r) => (
                                    <div key={r.id} className="flex justify-between text-sm text-ink-muted">
                                        <span>{formatDate(r.payment_date)} · {r.bank_account_code}</span>
                                        <span className="font-mono tabular-nums">{formatCurrency(r.amount, currency)}</span>
                                    </div>
                                ))}
                            </SidebarCard>
                        )}
                    </>
                }
            >
                <DocumentLines items={creditNote.items || []} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
