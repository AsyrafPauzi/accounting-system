import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import ShareButtons from '@/Components/ShareButtons';
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

export default function Show({
    auth,
    creditNote,
    openInvoices = [],
    bankAccounts = [],
    myinvois_gaps = [],
    can_cancel_einvoice = false,
    document_trail = [],
    public_pdf_url = null,
    whatsapp_url = null,
    company = {},
}) {
    const currency = creditNote.currency || 'MYR';
    const open = Number(creditNote.open_amount ?? (creditNote.total_amount - (creditNote.applied_amount || 0) - (creditNote.refunded_amount || 0)));
    const isVoid = creditNote.status === 'void';
    const refund = useForm({
        amount: open > 0 ? open.toFixed(2) : '',
        payment_date: new Date().toISOString().slice(0, 10),
        bank_account_code: bankAccounts[0]?.code || '',
        reference: '',
    });
    const apply = useForm({ invoice_id: openInvoices[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });
    const canEmail = auth.permissions.includes('invoices.email');
    const canShare = Boolean(public_pdf_url || whatsapp_url);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('credit-notes.index')}
                    title={creditNote.cn_number}
                    status={creditNote.status}
                    subtitle={creditNote.customer?.name || 'No customer'}
                >
                    {auth.permissions.includes('credit-notes.create') && !isVoid && (
                        <Link href={route('credit-notes.edit', creditNote.id)} className={headerBtn}>Edit</Link>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={creditNote.cn_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Credit note"
                docNumber={creditNote.cn_number}
                meta={[
                    { label: 'Issued', value: formatDate(creditNote.issue_date) },
                    creditNote.invoice?.invoice_number ? { label: 'Against', value: creditNote.invoice.invoice_number } : null,
                    creditNote.lhdn_status && creditNote.lhdn_status !== 'pending' ? { label: 'MyInvois', value: creditNote.lhdn_status } : null,
                ].filter(Boolean)}
                partyTitle="Credit to"
                partyName={creditNote.customer?.name}
                partyLines={partyAddress(creditNote.customer)}
                notes={creditNote.customer_notes}
                footer={creditNote.reason_description ? (
                    <div>
                        <p className={sectionTitle}>Reason</p>
                        <p className="mt-1.5 text-sm text-ink-muted leading-relaxed">{creditNote.reason_description}</p>
                    </div>
                ) : null}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(creditNote.amount_before_tax, currency) },
                            { label: 'Tax', value: formatCurrency(creditNote.tax_amount, currency) },
                            { label: 'Total', value: formatCurrency(creditNote.total_amount, currency), tone: 'total' },
                            Number(creditNote.applied_amount) > 0 ? { label: 'Applied', value: `−${formatCurrency(creditNote.applied_amount, currency)}`, tone: 'negative' } : null,
                            Number(creditNote.refunded_amount) > 0 ? { label: 'Refunded', value: `−${formatCurrency(creditNote.refunded_amount, currency)}` } : null,
                            { label: 'Open credit', value: formatCurrency(open, currency), tone: 'due' },
                        ].filter(Boolean)}
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
                            {canEmail && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('credit-notes.email', creditNote.id))}>Email customer</button>
                            )}
                            <a href={route('credit-notes.pdf', creditNote.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {canShare && <ShareButtons publicUrl={public_pdf_url} whatsappUrl={whatsapp_url} className={docBtn} />}
                            {creditNote.invoice_id && (
                                <Link href={route('invoices.show', creditNote.invoice_id)} className={docBtn}>
                                    Open {creditNote.invoice?.invoice_number || 'invoice'}
                                </Link>
                            )}
                            {!isVoid && (
                                <button type="button" className={`${docGhost} text-terracotta hover:text-terracotta`} onClick={() => router.post(route('credit-notes.void', creditNote.id))}>Void credit note</button>
                            )}
                        </div>
                        <DocumentTrail steps={document_trail} variant="stack" />
                        {(creditNote.applications || []).length > 0 && (
                            <SidebarCard title="Applied to invoices">
                                {creditNote.applications.map((a) => (
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
                        {!isVoid && (
                            <SidebarCard title="Apply leftover">
                                {openInvoices.length === 0 || open <= 0 ? (
                                    <p className="text-sm text-ink-muted">No open invoices, or nothing left to apply.</p>
                                ) : (
                                    <form
                                        className="space-y-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            apply.post(route('credit-notes.apply', creditNote.id), { preserveScroll: true });
                                        }}
                                    >
                                        <select className={field} value={apply.data.invoice_id} onChange={(e) => apply.setData('invoice_id', e.target.value)}>
                                            {openInvoices.map((inv) => <option key={inv.id} value={inv.id}>{inv.invoice_number}</option>)}
                                        </select>
                                        <input className={field} type="number" step="0.01" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                                        <button className={docPrimary} disabled={apply.processing}>Apply credit</button>
                                    </form>
                                )}
                            </SidebarCard>
                        )}
                        {!isVoid && (
                            <SidebarCard title="Refund leftover">
                                {open <= 0 ? (
                                    <p className="text-sm text-ink-muted">Nothing left to refund.</p>
                                ) : (
                                    <form
                                        className="space-y-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            refund.post(route('credit-notes.refund', creditNote.id), { preserveScroll: true });
                                        }}
                                    >
                                        <input className={field} type="number" step="0.01" value={refund.data.amount} onChange={(e) => refund.setData('amount', e.target.value)} />
                                        <input className={field} type="date" value={refund.data.payment_date} onChange={(e) => refund.setData('payment_date', e.target.value)} />
                                        <select className={field} value={refund.data.bank_account_code} onChange={(e) => refund.setData('bank_account_code', e.target.value)}>
                                            {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                                        </select>
                                        <input className={field} placeholder="Reference" value={refund.data.reference} onChange={(e) => refund.setData('reference', e.target.value)} />
                                        <button className={docPrimary} disabled={refund.processing}>Refund to bank</button>
                                    </form>
                                )}
                                {(creditNote.refunds || []).map((r) => (
                                    <div key={r.id} className="flex justify-between text-sm text-ink-muted">
                                        <span>{formatDate(r.payment_date)} · {r.bank_account_code}</span>
                                        <span className="font-mono tabular-nums">{formatCurrency(r.amount, currency)}</span>
                                    </div>
                                ))}
                            </SidebarCard>
                        )}
                        <SidebarCard title="MyInvois">
                            {myinvois_gaps.length > 0 ? (
                                <ul className="text-sm text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>
                            ) : (
                                <p className="text-sm text-forest">Ready to submit.</p>
                            )}
                            {creditNote.lhdn_uuid && <p className="text-xs font-mono break-all text-ink-muted">UUID {creditNote.lhdn_uuid}</p>}
                            {auth.planPermissions?.['myinvois.submit'] && !creditNote.lhdn_uuid && myinvois_gaps.length === 0 && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('credit-notes.myinvois.submit', creditNote.id))}>Submit e-invoice</button>
                            )}
                            {creditNote.lhdn_uuid && auth.planPermissions?.['myinvois.submit'] && (
                                <button type="button" className={docBtn} onClick={() => router.post(route('credit-notes.myinvois.refresh', creditNote.id))}>Refresh status</button>
                            )}
                            {can_cancel_einvoice && (
                                <button type="button" className={docBtn} onClick={() => router.post(route('credit-notes.myinvois.cancel', creditNote.id), { reason: 'Cancelled from credit note' })}>Cancel within 72h</button>
                            )}
                        </SidebarCard>
                    </>
                }
            >
                <DocumentLines items={creditNote.items || []} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
