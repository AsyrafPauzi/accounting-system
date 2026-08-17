import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import DocumentShowLayout, {
    DocumentShowHeader,
    DocumentLines,
    DocumentTotals,
    docBtn,
    docPrimary,
    docGhost,
    field,
    headerBtn,
    headerPrimary,
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

function kindLabel(kind) {
    if (kind === 'cash') return 'Cash purchase';
    if (kind === 'claim') return 'Expense claim';
    return 'Bill';
}

export default function Show({ auth, bill, bankAccounts = [], myinvois_gaps = [], trail = [], company = {} }) {
    const currency = bill.currency || 'MYR';
    const due = Number(bill.balance_due ?? 0);
    const paid = Number(bill.amount_paid || 0);
    const isDraft = bill.status === 'draft';
    const isVoid = bill.status === 'void';
    const kind = bill.purchase_kind && bill.purchase_kind !== 'credit' ? bill.purchase_kind : null;
    const payLabel = bill.purchase_kind === 'claim' ? 'Reimburse' : 'Record payment';
    const pay = useForm({
        amount: due > 0 ? due.toFixed(2) : '',
        payment_date: new Date().toISOString().slice(0, 10),
        bank_account_code: bankAccounts[0]?.code || '',
        reference: '',
    });
    const canMyInvois = Boolean(auth.planPermissions?.['myinvois.submit']);
    const subtotal = Number(bill.amount_before_tax ?? (Number(bill.total_amount || 0) - Number(bill.tax_amount || 0)));

    const postAction = async (url, opts = {}) => {
        if (opts.confirm) {
            const ok = await confirm(opts.confirm);
            if (!ok) return;
        }
        router.post(url, opts.body || {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('bills.index')}
                    title={bill.bill_number}
                    status={bill.status}
                    subtitle={bill.supplier?.name || 'No supplier'}
                    badges={
                        kind ? (
                            <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-cream text-ink-muted">
                                {kindLabel(kind)}
                            </span>
                        ) : null
                    }
                >
                    {isDraft && auth.permissions.includes('bills.edit') && (
                        <Link href={route('bills.edit', bill.id)} className={headerBtn}>Edit</Link>
                    )}
                    {isDraft && auth.permissions.includes('bills.post') && (
                        <button
                            type="button"
                            className={headerPrimary}
                            onClick={() => postAction(route('bills.post', bill.id), {
                                confirm: { title: 'Post this bill?', text: 'This locks the bill and posts the purchase journal.', confirmText: 'Post', icon: 'question' },
                            })}
                        >
                            Post bill
                        </button>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={bill.bill_number} />
            <DocumentShowLayout
                company={company}
                docLabel={kindLabel(bill.purchase_kind)}
                docNumber={bill.bill_number}
                meta={[
                    { label: 'Issued', value: formatDate(bill.bill_date) },
                    { label: 'Due', value: bill.due_date ? formatDate(bill.due_date) : '—' },
                    bill.reference ? { label: 'Reference', value: bill.reference } : null,
                    bill.lhdn_status && bill.lhdn_status !== 'pending' ? { label: 'MyInvois', value: bill.lhdn_status } : null,
                ].filter(Boolean)}
                partyTitle="Supplier"
                partyName={bill.supplier?.name}
                partyLines={partyAddress(bill.supplier)}
                notes={bill.private_notes}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(subtotal, currency) },
                            Number(bill.tax_amount) > 0 ? { label: 'Tax', value: formatCurrency(bill.tax_amount, currency) } : null,
                            { label: 'Total', value: formatCurrency(bill.total_amount, currency), tone: 'total' },
                            paid > 0 ? { label: 'Paid', value: `−${formatCurrency(paid, currency)}`, tone: 'negative' } : null,
                            { label: 'Amount due', value: formatCurrency(due, currency), tone: 'due' },
                        ].filter(Boolean)}
                    />
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Amount due</p>
                            <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${due > 0 ? 'text-terracotta' : 'text-forest'}`}>
                                {formatCurrency(due, currency)}
                            </p>
                            <p className="mt-1 text-xs text-ink-muted">
                                of {formatCurrency(bill.total_amount, currency)}
                                {paid > 0 ? ` · ${formatCurrency(paid, currency)} paid` : ''}
                            </p>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            {auth.permissions.includes('bills.create') && (
                                <Link href={route('supplier-credit-notes.create', { bill_id: bill.id })} className={docBtn}>
                                    Credit note
                                </Link>
                            )}
                            {bill.supplier_id && (
                                <Link href={route('suppliers.show', bill.supplier_id)} className={docBtn}>
                                    Open supplier
                                </Link>
                            )}
                            {!isDraft && !isVoid && auth.permissions.includes('bills.void') && (
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={() => postAction(route('bills.void', bill.id), {
                                        confirm: { title: 'Void this bill?', text: 'This reverses the purchase journal. It cannot be undone.', confirmText: 'Void', confirmColor: '#dc2626', icon: 'warning' },
                                    })}
                                >
                                    Void bill
                                </button>
                            )}
                        </div>

                        <DocumentTrail steps={trail} variant="stack" />

                        {!isDraft && !isVoid && (
                            <SidebarCard title="Payments">
                                {(bill.payments || []).length === 0
                                    && (bill.credit_note_applications || []).length === 0
                                    && (bill.deposit_applications || []).length === 0 && (
                                    <p className="text-sm text-ink-muted">No payments yet.</p>
                                )}
                                {(bill.payments || []).map((p) => (
                                    <div key={p.id} className="flex justify-between text-sm gap-2">
                                        <span>{formatDate(p.payment_date)} · {p.bank_account_code}</span>
                                        <span className="font-mono tabular-nums">{formatCurrency(p.amount, currency)}</span>
                                    </div>
                                ))}
                                {(bill.credit_note_applications || []).map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm text-ink-muted gap-2">
                                        <span>CN {a.credit_note?.scn_number || 'SCN'}</span>
                                        <span className="font-mono tabular-nums">−{formatCurrency(a.amount, currency)}</span>
                                    </div>
                                ))}
                                {(bill.deposit_applications || []).map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm text-ink-muted gap-2">
                                        <span>Deposit {a.deposit?.reference || ''}</span>
                                        <span className="font-mono tabular-nums">−{formatCurrency(a.amount, currency)}</span>
                                    </div>
                                ))}
                                {auth.permissions.includes('bills.record-payment') && due > 0 && (
                                    <form
                                        className="space-y-2 pt-3 border-t border-border-warm"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            pay.post(route('bills.record-payment', bill.id), { preserveScroll: true });
                                        }}
                                    >
                                        <input className={field} type="number" step="0.01" value={pay.data.amount} onChange={(e) => pay.setData('amount', e.target.value)} />
                                        <input className={field} type="date" value={pay.data.payment_date} onChange={(e) => pay.setData('payment_date', e.target.value)} />
                                        <select className={field} value={pay.data.bank_account_code} onChange={(e) => pay.setData('bank_account_code', e.target.value)}>
                                            {bankAccounts.map((a) => (
                                                <option key={a.code} value={a.code}>{a.code} {a.name}</option>
                                            ))}
                                        </select>
                                        <input className={field} placeholder="Reference" value={pay.data.reference} onChange={(e) => pay.setData('reference', e.target.value)} />
                                        <button className={docPrimary} disabled={pay.processing}>{payLabel}</button>
                                    </form>
                                )}
                            </SidebarCard>
                        )}

                        <SidebarCard title="Self-billed e-invoice">
                            <p className="text-xs text-ink-muted">Type 12. You issue this to LHDN for the supplier. It does not post a second journal.</p>
                            <p className="text-sm">
                                Status: <span className="font-medium">{bill.lhdn_status || 'pending'}</span>
                            </p>
                            {bill.lhdn_uuid && <p className="text-xs font-mono break-all text-ink-muted">UUID {bill.lhdn_uuid}</p>}
                            {bill.lhdn_reject_reason && <p className="text-sm text-terracotta">{bill.lhdn_reject_reason}</p>}
                            {myinvois_gaps.length > 0 ? (
                                <ul className="text-sm text-terracotta list-disc pl-4">
                                    {myinvois_gaps.map((g) => <li key={g}>{g}</li>)}
                                </ul>
                            ) : (
                                !bill.lhdn_uuid && <p className="text-sm text-forest">Ready to submit.</p>
                            )}
                            {canMyInvois && !bill.lhdn_uuid && myinvois_gaps.length === 0 && (
                                <button type="button" className={docPrimary} onClick={() => postAction(route('bills.myinvois.submit', bill.id))}>
                                    Submit self-billed
                                </button>
                            )}
                            {bill.lhdn_uuid && canMyInvois && (
                                <button type="button" className={docBtn} onClick={() => postAction(route('bills.myinvois.refresh', bill.id))}>
                                    Refresh status
                                </button>
                            )}
                            {bill.lhdn_uuid && bill.lhdn_status !== 'cancelled' && canMyInvois && (
                                <button
                                    type="button"
                                    className={docBtn}
                                    onClick={() => postAction(route('bills.myinvois.cancel', bill.id), { body: { reason: 'Cancelled from bill' } })}
                                >
                                    Cancel within 72h
                                </button>
                            )}
                        </SidebarCard>

                        {bill.receipt_url && (
                            <SidebarCard title="Receipt">
                                <a href={bill.receipt_url} target="_blank" rel="noreferrer" className="text-sm text-terracotta hover:underline">
                                    View uploaded receipt
                                </a>
                            </SidebarCard>
                        )}
                    </>
                }
            >
                <DocumentLines items={bill.items || []} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
