import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentShowLayout, {
    DocumentShowHeader,
    DocumentLines,
    DocumentTotals,
    docBtn,
    docPrimary,
    docGhost,
    headerBtn,
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, debitNote, myinvois_gaps = [], company = {} }) {
    const currency = debitNote.currency || 'MYR';
    const isVoid = debitNote.status === 'void';
    const canEmail = auth.permissions.includes('invoices.email');

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('debit-notes.index')}
                    title={debitNote.dn_number}
                    status={debitNote.status}
                    subtitle={debitNote.customer?.name || 'No customer'}
                >
                    {auth.permissions.includes('debit-notes.create') && !isVoid && (
                        <Link href={route('debit-notes.edit', debitNote.id)} className={headerBtn}>Edit</Link>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={debitNote.dn_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Debit note"
                docNumber={debitNote.dn_number}
                meta={[
                    { label: 'Issued', value: formatDate(debitNote.issue_date) },
                    debitNote.invoice?.invoice_number ? { label: 'Against', value: debitNote.invoice.invoice_number } : null,
                    debitNote.lhdn_status && debitNote.lhdn_status !== 'pending' ? { label: 'MyInvois', value: debitNote.lhdn_status } : null,
                ].filter(Boolean)}
                partyTitle="Bill to"
                partyName={debitNote.customer?.name}
                partyLines={partyAddress(debitNote.customer)}
                notes={debitNote.customer_notes}
                footer={debitNote.reason_description ? (
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Reason</p>
                        <p className="mt-1.5 text-sm text-ink-muted leading-relaxed">{debitNote.reason_description}</p>
                    </div>
                ) : null}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(debitNote.amount_before_tax, currency) },
                            { label: 'Tax', value: formatCurrency(debitNote.tax_amount, currency) },
                            { label: 'Total', value: formatCurrency(debitNote.total_amount, currency), tone: 'total' },
                        ]}
                    />
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Total</p>
                            <p className="mt-1 text-3xl font-display font-medium tabular-nums text-ink">
                                {formatCurrency(debitNote.total_amount, currency)}
                            </p>
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            {canEmail && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('debit-notes.email', debitNote.id))}>Email customer</button>
                            )}
                            <a href={route('debit-notes.pdf', debitNote.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {debitNote.invoice_id && (
                                <Link href={route('invoices.show', debitNote.invoice_id)} className={docBtn}>
                                    Open {debitNote.invoice?.invoice_number || 'invoice'}
                                </Link>
                            )}
                            {!isVoid && (
                                <button type="button" className={`${docGhost} text-terracotta hover:text-terracotta`} onClick={() => router.post(route('debit-notes.void', debitNote.id))}>Void debit note</button>
                            )}
                        </div>
                        <SidebarCard title="MyInvois">
                            {myinvois_gaps.length > 0 ? (
                                <ul className="text-sm text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>
                            ) : (
                                <p className="text-sm text-forest">Ready to submit.</p>
                            )}
                            {debitNote.lhdn_uuid && <p className="text-xs font-mono break-all text-ink-muted">UUID {debitNote.lhdn_uuid}</p>}
                            {auth.planPermissions?.['myinvois.submit'] && !debitNote.lhdn_uuid && myinvois_gaps.length === 0 && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('debit-notes.myinvois.submit', debitNote.id))}>Submit e-invoice</button>
                            )}
                            {debitNote.lhdn_uuid && (
                                <button type="button" className={docBtn} onClick={() => router.post(route('debit-notes.myinvois.refresh', debitNote.id))}>Refresh status</button>
                            )}
                        </SidebarCard>
                    </>
                }
            >
                <DocumentLines items={debitNote.items || []} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
