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
    docGhost,
    partyAddress,
    sectionTitle,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, debitNote, company = {} }) {
    const currency = debitNote.currency || 'MYR';
    const isVoid = debitNote.status === 'void';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('supplier-debit-notes.index')}
                    title={debitNote.sdn_number}
                    status={debitNote.status}
                    subtitle={debitNote.supplier?.name || 'No supplier'}
                />
            }
        >
            <Head title={debitNote.sdn_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Supplier debit note"
                docNumber={debitNote.sdn_number}
                meta={[
                    { label: 'Issued', value: formatDate(debitNote.issue_date) },
                    debitNote.bill?.bill_number ? { label: 'Against', value: debitNote.bill.bill_number } : null,
                ].filter(Boolean)}
                partyTitle="Supplier"
                partyName={debitNote.supplier?.name}
                partyLines={partyAddress(debitNote.supplier)}
                notes={debitNote.notes}
                footer={debitNote.reason_description ? (
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Reason</p>
                        <p className="mt-1.5 text-sm text-ink-muted leading-relaxed">{debitNote.reason_description}</p>
                    </div>
                ) : null}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(debitNote.amount_before_tax ?? (Number(debitNote.total_amount) - Number(debitNote.tax_amount || 0)), currency) },
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
                            <a href={route('supplier-debit-notes.pdf', debitNote.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {debitNote.bill?.id && (
                                <Link href={route('bills.show', debitNote.bill.id)} className={docBtn}>Open {debitNote.bill.bill_number}</Link>
                            )}
                            {!isVoid && (
                                <button type="button" className={`${docGhost} text-terracotta hover:text-terracotta`} onClick={() => router.post(route('supplier-debit-notes.void', debitNote.id))}>Void debit note</button>
                            )}
                        </div>
                    </>
                }
            >
                <DocumentLines items={debitNote.items || []} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
