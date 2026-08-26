import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency, currencyDecimals } from '@/utils/currency';
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
    headerPrimary,
    partyAddress,
    partyShipping,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

function MoreSection({ title, children, defaultOpen = false }) {
    return (
        <details open={defaultOpen} className="group border-t border-border-warm first:border-t-0">
            <summary className="flex cursor-pointer list-none items-center justify-between py-3 text-sm font-semibold text-ink [&::-webkit-details-marker]:hidden">
                {title}
                <svg className="w-4 h-4 text-ink-muted transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </summary>
            <div className="pb-4 space-y-3">{children}</div>
        </details>
    );
}

export default function Show({
    auth,
    invoice,
    balance = 0,
    bankAccounts = [],
    openCredits = [],
    openDeposits = [],
    myinvois_gaps = [],
    can_cancel_einvoice = false,
    pay_now_configured = false,
    public_pdf_url = null,
    public_html_url = null,
    whatsapp_url = null,
    document_trail = [],
    reminder_offsets = [-14, -7, -3, 0, 3, 7, 14],
    late_fee_percent = 1.5,
    can_issue_late_fee = false,
    company = {},
    journal_entry_id = null,
}) {
    const currency = invoice.currency || 'MYR';
    const decimals = currencyDecimals(currency);
    const isDraft = invoice.status === 'draft';
    const isVoid = invoice.status === 'void';
    const [file, setFile] = useState(null);
    const pay = useForm({
        amount: Number(balance).toFixed(decimals),
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: bankAccounts[0]?.value || '',
        reference: '',
    });
    const recurring = useForm({ cadence: 'monthly', interval: 1, start_date: new Date().toISOString().split('T')[0], auto_email: false, auto_post: false });
    const cancelEinvoice = useForm({ reason: '' });
    const reminders = useForm({ offsets: reminder_offsets });

    const postAction = (url, body = {}) => router.post(url, body, { preserveScroll: true });
    const canEmail = invoice.customer?.email && auth.permissions.includes('invoices.email');
    const canShare = Boolean(public_html_url || public_pdf_url || whatsapp_url);
    const shipTo = partyShipping(invoice.customer);
    const paid = Number(invoice.amount_paid || 0);
    const due = Number(balance);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('invoices.index')}
                    title={invoice.invoice_number}
                    status={invoice.status}
                    subtitle={invoice.customer?.name || 'No customer'}
                    badges={
                        <>
                            {invoice.is_cash_sale && (
                                <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-forest/10 text-forest">Cash sale</span>
                            )}
                            {invoice.is_late_fee && (
                                <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-amber-50 text-amber-800">Late fee</span>
                            )}
                        </>
                    }
                >
                    {isDraft && auth.permissions.includes('invoices.edit') && (
                        <Link href={route('invoices.edit', invoice.id)} className={headerBtn}>Edit</Link>
                    )}
                    {isDraft && auth.permissions.includes('invoices.post') && (
                        <button
                            type="button"
                            className={headerPrimary}
                            onClick={async () => {
                                if (await confirm({ title: 'Post to ledger?', confirmText: 'Post' })) {
                                    postAction(route('invoices.post', invoice.id));
                                }
                            }}
                        >
                            Post invoice
                        </button>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={invoice.invoice_number} />

            <DocumentShowLayout
                company={company}
                docLabel="Invoice"
                docNumber={invoice.invoice_number}
                meta={[
                    { label: 'Issued', value: formatDate(invoice.issue_date) },
                    { label: 'Due', value: invoice.due_date ? formatDate(invoice.due_date) : '—' },
                    invoice.lhdn_status && invoice.lhdn_status !== 'pending' ? { label: 'MyInvois', value: invoice.lhdn_status } : null,
                ].filter(Boolean)}
                partyTitle="Bill to"
                partyName={invoice.customer?.name}
                partyLines={[...partyAddress(invoice.customer), invoice.customer?.email].filter(Boolean)}
                secondaryParty={shipTo.length > 0 ? { title: 'Ship to', lines: shipTo } : null}
                notes={invoice.customer_notes}
                footer={company.name ? (
                    <div>
                        <p className={sectionTitle}>Payment</p>
                        <p className="mt-1.5 text-sm text-ink-muted leading-relaxed">
                            Payable to {company.name}
                            {company.tin ? ` · TIN ${company.tin}` : ''}
                        </p>
                    </div>
                ) : null}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(invoice.amount_before_tax, currency) },
                            Number(invoice.discount_total) > 0 ? { label: 'Discount', value: `−${formatCurrency(invoice.discount_total, currency)}`, tone: 'negative' } : null,
                            { label: 'Tax', value: formatCurrency(invoice.tax_amount, currency) },
                            Number(invoice.shipping_amount) > 0 ? { label: 'Shipping', value: formatCurrency(invoice.shipping_amount, currency) } : null,
                            Number(invoice.rounding_adjustment) !== 0 ? { label: 'Rounding', value: formatCurrency(invoice.rounding_adjustment, currency) } : null,
                            { label: 'Total', value: formatCurrency(invoice.total_amount, currency), tone: 'total' },
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
                                of {formatCurrency(invoice.total_amount, currency)}
                                {paid > 0 ? ` · ${formatCurrency(paid, currency)} received` : ''}
                            </p>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            {canEmail && (
                                <button type="button" className={docPrimary} onClick={() => postAction(route('invoices.email', invoice.id))}>Email customer</button>
                            )}
                            <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {public_html_url && (
                                <a href={public_html_url} target="_blank" rel="noreferrer" className={docPrimary}>View & Pay</a>
                            )}
                            {canShare && (
                                <ShareButtons publicUrl={public_html_url || public_pdf_url} whatsappUrl={whatsapp_url} className={docBtn} />
                            )}
                            {auth.planPermissions?.['general-ledger.view'] && journal_entry_id && (
                                <Link href={route('general-ledger.show', journal_entry_id)} className={docBtn}>View journal</Link>
                            )}
                            {auth.permissions.includes('invoices.create') && (
                                <button type="button" className={docGhost} onClick={() => postAction(route('invoices.duplicate', invoice.id))}>Duplicate</button>
                            )}
                            {can_issue_late_fee && auth.permissions.includes('invoices.create') && (
                                <button
                                    type="button"
                                    className={docGhost}
                                    onClick={async () => {
                                        if (await confirm({ title: `Create ${late_fee_percent}% late interest invoice?`, confirmText: 'Create draft' })) {
                                            postAction(route('invoices.late-fee', invoice.id));
                                        }
                                    }}
                                >
                                    Late fee {late_fee_percent}%
                                </button>
                            )}
                            {!isDraft && !isVoid && auth.permissions.includes('invoices.void') && (
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={async () => {
                                        if (await confirm({ title: 'Void invoice?', confirmText: 'Void', confirmColor: '#dc2626' })) {
                                            postAction(route('invoices.void', invoice.id));
                                        }
                                    }}
                                >
                                    Void invoice
                                </button>
                            )}
                        </div>

                        <DocumentTrail steps={document_trail} variant="stack" />

                        {!isDraft && !isVoid && (
                            <SidebarCard title="Payments">
                                {(invoice.payments || []).length === 0 && (invoice.credit_note_applications || []).length === 0 && (
                                    <p className="text-sm text-ink-muted">No payments yet.</p>
                                )}
                                {(invoice.payments || []).map((p) => (
                                    <div key={p.id} className={`flex justify-between text-sm gap-2 ${p.reversed_at ? 'line-through text-ink-muted' : ''}`}>
                                        <span>{formatDate(p.payment_date)}{p.reversed_at ? ' · reversed' : ''}</span>
                                        <span className="font-mono inline-flex items-center gap-2 tabular-nums">
                                            {formatCurrency(p.amount, currency)}
                                            {!p.reversed_at && (
                                                <a className="text-terracotta no-underline text-xs" href={route('invoices.payment-receipt', [invoice.id, p.id])} target="_blank" rel="noreferrer">Official receipt</a>
                                            )}
                                            {!p.reversed_at && auth.permissions.includes('invoices.record-payment') && (
                                                <button
                                                    type="button"
                                                    className="text-terracotta text-xs"
                                                    onClick={async () => {
                                                        if (await confirm({ title: 'Reverse this payment?', confirmText: 'Reverse' })) {
                                                            postAction(route('invoices.reverse-payment', [invoice.id, p.id]));
                                                        }
                                                    }}
                                                >
                                                    Reverse
                                                </button>
                                            )}
                                        </span>
                                    </div>
                                ))}
                                {(invoice.credit_note_applications || []).map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm text-ink-muted">
                                        <span>CN {a.credit_note?.cn_number}</span>
                                        <span className="font-mono tabular-nums">−{formatCurrency(a.amount, currency)}</span>
                                    </div>
                                ))}
                                {auth.permissions.includes('invoices.record-payment') && due > 0 && (
                                    <form onSubmit={(e) => { e.preventDefault(); pay.post(route('invoices.record-payment', invoice.id), { preserveScroll: true }); }} className="space-y-2 pt-3 border-t border-border-warm">
                                        <input className={field} type="number" step="0.01" value={pay.data.amount} onChange={(e) => pay.setData('amount', e.target.value)} />
                                        <input className={field} type="date" value={pay.data.payment_date} onChange={(e) => pay.setData('payment_date', e.target.value)} />
                                        <select className={field} value={pay.data.bank_account_code} onChange={(e) => pay.setData('bank_account_code', e.target.value)}>
                                            {bankAccounts.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                                        </select>
                                        <input className={field} placeholder="Reference" value={pay.data.reference} onChange={(e) => pay.setData('reference', e.target.value)} />
                                        <button className={docPrimary} disabled={pay.processing}>Record payment</button>
                                    </form>
                                )}
                                {pay_now_configured && due > 0 && (
                                    <button type="button" className={docBtn} onClick={() => postAction(route('invoices.pay-now', invoice.id))}>Pay Now</button>
                                )}
                            </SidebarCard>
                        )}

                        {!isDraft && !isVoid && (
                            <SidebarCard title="Credits & deposits">
                                {(openCredits.length > 0 || openDeposits.length > 0) ? (
                                    <ul className="max-h-48 overflow-y-auto divide-y divide-border-warm -mx-1">
                                        {openCredits.map((cn) => {
                                            const open = Number(cn.open);
                                            const applyAmt = Math.min(open, due);
                                            return (
                                                <li key={`cn-${cn.id}`} className="flex items-center gap-2 py-2 px-1">
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium text-ink truncate">{cn.number}</p>
                                                        <p className="text-[11px] text-ink-muted">Credit note</p>
                                                    </div>
                                                    <span className="font-mono text-sm tabular-nums text-ink shrink-0">{formatCurrency(open, currency)}</span>
                                                    {due > 0 && applyAmt > 0 && (
                                                        <button
                                                            type="button"
                                                            className="text-xs font-semibold text-terracotta hover:underline shrink-0"
                                                            onClick={() => router.post(route('credit-notes.apply', cn.id), { invoice_id: invoice.id, amount: applyAmt }, { preserveScroll: true })}
                                                        >
                                                            Apply
                                                        </button>
                                                    )}
                                                </li>
                                            );
                                        })}
                                        {openDeposits.map((d) => {
                                            const open = Number(d.open);
                                            const applyAmt = Math.min(open, due);
                                            return (
                                                <li key={`dep-${d.id}`} className="flex items-center gap-2 py-2 px-1">
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium text-ink truncate">{d.number}</p>
                                                        <p className="text-[11px] text-ink-muted">{d.date ? formatDate(d.date) : 'Deposit'}</p>
                                                    </div>
                                                    <span className="font-mono text-sm tabular-nums text-ink shrink-0">{formatCurrency(open, currency)}</span>
                                                    {due > 0 && applyAmt > 0 && (
                                                        <button
                                                            type="button"
                                                            className="text-xs font-semibold text-terracotta hover:underline shrink-0"
                                                            onClick={() => router.post(route('ar-deposits.apply', d.id), { invoice_id: invoice.id, amount: applyAmt }, { preserveScroll: true })}
                                                        >
                                                            Apply
                                                        </button>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-ink-muted">Nothing open to apply.</p>
                                )}
                                {(auth.permissions.includes('credit-notes.create') || auth.permissions.includes('debit-notes.create')) && (
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 pt-2 border-t border-border-warm">
                                        {auth.permissions.includes('credit-notes.create') && (
                                            <Link href={route('credit-notes.create', invoice.id)} className="text-sm font-semibold text-terracotta hover:underline">Credit note</Link>
                                        )}
                                        {auth.permissions.includes('debit-notes.create') && (
                                            <Link href={`${route('debit-notes.create')}?invoice_id=${invoice.id}`} className="text-sm font-semibold text-terracotta hover:underline">Debit note</Link>
                                        )}
                                    </div>
                                )}
                            </SidebarCard>
                        )}

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm px-5">
                            <MoreSection title="MyInvois" defaultOpen={myinvois_gaps.length > 0 || Boolean(invoice.lhdn_uuid)}>
                                {myinvois_gaps.length > 0 ? (
                                    <ul className="text-sm text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>
                                ) : (
                                    <p className="text-sm text-forest">Ready to submit.</p>
                                )}
                                {invoice.lhdn_uuid && <p className="text-xs font-mono break-all text-ink-muted">UUID {invoice.lhdn_uuid}</p>}
                                {invoice.lhdn_qr_url && <a className="text-sm text-terracotta" href={invoice.lhdn_qr_url} target="_blank" rel="noreferrer">QR / share link</a>}
                                {auth.planPermissions?.['myinvois.submit'] && auth.permissions.includes('myinvois.submit') && !invoice.lhdn_uuid && myinvois_gaps.length === 0 && (
                                    <button type="button" className={docPrimary} onClick={() => postAction(route('invoices.myinvois.submit', invoice.id))}>Submit e-invoice</button>
                                )}
                                {invoice.lhdn_uuid && auth.planPermissions?.['myinvois.submit'] && (
                                    <button type="button" className={docBtn} onClick={() => postAction(route('invoices.myinvois.refresh', invoice.id))}>Refresh status</button>
                                )}
                                {can_cancel_einvoice && (
                                    <form onSubmit={(e) => { e.preventDefault(); cancelEinvoice.post(route('invoices.myinvois.cancel', invoice.id)); }} className="space-y-2">
                                        <input className={field} placeholder="Cancel reason" value={cancelEinvoice.data.reason} onChange={(e) => cancelEinvoice.setData('reason', e.target.value)} />
                                        <button className={docBtn} type="submit">Cancel within 72h</button>
                                    </form>
                                )}
                            </MoreSection>

                            <MoreSection title="Attachments">
                                {(invoice.attachments || []).map((a) => (
                                    <div key={a.id} className="flex justify-between text-sm gap-2">
                                        <span className="truncate">{a.original_name}</span>
                                        <button type="button" className="text-terracotta shrink-0" onClick={() => router.delete(route('invoices.detach', [invoice.id, a.id]))}>Remove</button>
                                    </div>
                                ))}
                                <form onSubmit={(e) => { e.preventDefault(); if (!file) return; router.post(route('invoices.attach', invoice.id), { file }, { forceFormData: true, preserveScroll: true }); }} className="space-y-2">
                                    <input type="file" onChange={(e) => setFile(e.target.files?.[0])} className="text-sm w-full" />
                                    <button className={docBtn} type="submit">Upload</button>
                                </form>
                            </MoreSection>

                            {auth.permissions.includes('recurring-invoices.create') && (
                                <MoreSection title="Make recurring">
                                    <form onSubmit={(e) => { e.preventDefault(); recurring.post(route('invoices.create-recurring', invoice.id)); }} className="space-y-2">
                                        <select className={field} value={recurring.data.cadence} onChange={(e) => recurring.setData('cadence', e.target.value)}>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="quarterly">Quarterly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                        <label className="text-sm flex gap-2 items-center">
                                            <input type="checkbox" checked={recurring.data.auto_email} onChange={(e) => recurring.setData('auto_email', e.target.checked)} />
                                            Auto-email
                                        </label>
                                        <label className="text-sm flex gap-2 items-center">
                                            <input type="checkbox" checked={recurring.data.auto_post} onChange={(e) => recurring.setData('auto_post', e.target.checked)} />
                                            Auto-post
                                        </label>
                                        <button className={docBtn} type="submit">Create template</button>
                                    </form>
                                </MoreSection>
                            )}

                            {!isDraft && !isVoid && (
                                <MoreSection title="Reminders">
                                    <form onSubmit={(e) => { e.preventDefault(); reminders.post(route('invoices.reminders', invoice.id)); }} className="space-y-2">
                                        <div className="flex flex-wrap gap-2">
                                            {[-14, -7, -3, 0, 3, 7, 14].map((offset) => (
                                                <label key={offset} className="text-xs flex gap-1 items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={reminders.data.offsets.map(Number).includes(offset)}
                                                        onChange={(e) => {
                                                            const current = reminders.data.offsets.map(Number);
                                                            reminders.setData('offsets', e.target.checked ? [...current, offset] : current.filter((n) => n !== offset));
                                                        }}
                                                    />
                                                    {offset === 0 ? 'Due' : offset < 0 ? `${Math.abs(offset)}d before` : `${offset}d after`}
                                                </label>
                                            ))}
                                        </div>
                                        <button className={docBtn} type="submit">Save reminders</button>
                                    </form>
                                </MoreSection>
                            )}
                        </div>
                    </>
                }
            >
                <DocumentLines items={invoice.items || []} currency={currency} formatCurrency={formatCurrency} showTax />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
