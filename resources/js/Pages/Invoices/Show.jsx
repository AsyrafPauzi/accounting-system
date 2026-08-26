import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency, currencyDecimals } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import ShareButtons from '@/Components/ShareButtons';

const btn = 'inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream text-ink';
const primary = 'inline-flex items-center justify-center gap-1.5 w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50';
const ghost = 'inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-xl text-sm font-medium text-ink-muted hover:text-ink hover:bg-cream';
const headerBtn = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200';
const headerPrimary = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';
const field = 'w-full border border-border-warm rounded-xl px-3 py-2 text-sm bg-surface focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const sectionTitle = 'text-[10px] font-semibold uppercase tracking-wider text-ink-muted';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
};

function statusTone(status) {
    if (status === 'draft') return 'bg-amber-50 text-amber-800';
    if (status === 'void') return 'bg-stone-100 text-stone-600';
    if (status === 'paid') return 'bg-forest/10 text-forest';
    if (status === 'overdue') return 'bg-terracotta/10 text-terracotta';
    return 'bg-cream text-ink-muted';
}

function linesFrom(parts) {
    return parts.filter(Boolean);
}

function customerAddress(customer) {
    if (!customer) return [];
    return linesFrom([
        customer.billing_street,
        [customer.billing_city, customer.billing_state, customer.billing_zip].filter(Boolean).join(' '),
        customer.billing_country,
    ]);
}

function customerShipping(customer) {
    if (!customer) return [];
    return linesFrom([
        customer.shipping_street,
        [customer.shipping_city, customer.shipping_state, customer.shipping_zip].filter(Boolean).join(' '),
        customer.shipping_country,
    ]);
}

function companyAddress(company) {
    if (!company) return [];
    return linesFrom([
        company.address,
        [company.city, company.state, company.zip].filter(Boolean).join(' '),
        company.country,
        company.phone ? `Tel ${company.phone}` : null,
        company.email,
    ]);
}

function SidebarCard({ title, children }) {
    return (
        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5 space-y-3">
            {title && <h3 className="text-sm font-semibold text-ink">{title}</h3>}
            {children}
        </div>
    );
}

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
    const decimals = currencyDecimals(invoice.currency);
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
    const billTo = customerAddress(invoice.customer);
    const shipTo = customerShipping(invoice.customer);
    const fromLines = companyAddress(company);
    const paid = Number(invoice.amount_paid || 0);
    const due = Number(balance);
    const brand = company.brand_color && company.brand_color !== '#0f172a' ? company.brand_color : null;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div className="flex items-center gap-2 min-w-0">
                        <Link href={route('invoices.index')} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200 shrink-0">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">{invoice.invoice_number}</h2>
                                <span className={`text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md ${statusTone(invoice.status)}`}>
                                    {invoice.status}
                                </span>
                                {invoice.is_cash_sale && (
                                    <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-forest/10 text-forest">Cash sale</span>
                                )}
                                {invoice.is_late_fee && (
                                    <span className="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-amber-50 text-amber-800">Late fee</span>
                                )}
                            </div>
                            <p className="text-sm text-ink-muted mt-0.5 truncate">{invoice.customer?.name || 'No customer'}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 shrink-0">
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
                    </div>
                </div>
            }
        >
            <Head title={invoice.invoice_number} />

            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem] gap-6 items-start pb-8">
                <article className="bg-white rounded-2xl border border-border-warm/70 shadow-[0_8px_30px_rgba(28,25,23,0.06)] overflow-hidden">
                    <div className={brand ? 'h-1.5' : 'h-1.5 bg-terracotta'} style={brand ? { backgroundColor: brand } : undefined} />

                    <div className="px-6 sm:px-10 pt-8 pb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                        <div className="min-w-0">
                            {company.logo_url && (
                                <img src={company.logo_url} alt="" className="h-10 w-auto max-w-[180px] object-contain mb-3" />
                            )}
                            <p className="text-lg font-display font-medium text-ink tracking-tight">{company.name || 'Invoice'}</p>
                            {fromLines.map((line) => (
                                <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>
                            ))}
                            {(company.tin || company.brn) && (
                                <p className="text-xs text-ink-muted mt-1">
                                    {company.brn ? `Reg ${company.brn}` : ''}
                                    {company.brn && company.tin ? ' · ' : ''}
                                    {company.tin ? `TIN ${company.tin}` : ''}
                                </p>
                            )}
                        </div>
                        <div className="text-left sm:text-right shrink-0">
                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-ink-muted">Invoice</p>
                            <p className="mt-1 text-2xl font-display font-medium text-ink tracking-tight">{invoice.invoice_number}</p>
                            {invoice.lhdn_status && invoice.lhdn_status !== 'pending' && (
                                <p className="mt-1 text-xs text-blue-800">MyInvois {invoice.lhdn_status}</p>
                            )}
                            <dl className="mt-4 space-y-1.5 text-sm">
                                <div className="flex sm:justify-end gap-3">
                                    <dt className="text-ink-muted">Issued</dt>
                                    <dd className="font-medium text-ink tabular-nums">{formatDate(invoice.issue_date)}</dd>
                                </div>
                                <div className="flex sm:justify-end gap-3">
                                    <dt className="text-ink-muted">Due</dt>
                                    <dd className="font-medium text-ink tabular-nums">{invoice.due_date ? formatDate(invoice.due_date) : '—'}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div className="px-6 sm:px-10 pb-8 grid sm:grid-cols-2 gap-8">
                        <div>
                            <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>Bill to</p>
                            <p className="font-semibold text-ink">{invoice.customer?.name || 'No customer'}</p>
                            {billTo.map((line) => (
                                <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>
                            ))}
                            {invoice.customer?.email && (
                                <p className="text-sm text-ink-muted">{invoice.customer.email}</p>
                            )}
                        </div>
                        {shipTo.length > 0 && (
                            <div>
                                <p className={`${sectionTitle} border-b border-border-warm pb-1.5 mb-2`}>Ship to</p>
                                {shipTo.map((line) => (
                                    <p key={line} className="text-sm text-ink-muted leading-relaxed">{line}</p>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className={`${sectionTitle} border-y border-ink/15 bg-cream/40`}>
                                    <th className="px-6 sm:px-10 py-3 text-left font-semibold">Description</th>
                                    <th className="px-3 py-3 text-right font-semibold w-16">Qty</th>
                                    <th className="px-3 py-3 text-right font-semibold w-24">Price</th>
                                    <th className="px-3 py-3 text-right font-semibold w-16">Tax</th>
                                    <th className="px-6 sm:px-10 py-3 text-right font-semibold w-28">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(invoice.items || []).map((item) => (
                                    <tr key={item.id} className="border-b border-border-warm/60 last:border-0">
                                        <td className="px-6 sm:px-10 py-3.5 whitespace-pre-wrap text-ink align-top">{item.description}</td>
                                        <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{item.quantity}</td>
                                        <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{Number(item.unit_price).toFixed(decimals)}</td>
                                        <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{item.tax_rate}%</td>
                                        <td className="px-6 sm:px-10 py-3.5 text-right font-mono text-ink tabular-nums align-top">{Number(item.amount).toFixed(decimals)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="px-6 sm:px-10 py-8 grid sm:grid-cols-[minmax(0,1fr)_16rem] gap-8 items-start">
                        <div className="space-y-5">
                            {invoice.customer_notes && (
                                <div>
                                    <p className={sectionTitle}>Notes</p>
                                    <p className="mt-1.5 text-sm text-ink-muted whitespace-pre-line leading-relaxed">{invoice.customer_notes}</p>
                                </div>
                            )}
                            {company.name && (
                                <div>
                                    <p className={sectionTitle}>Payment</p>
                                    <p className="mt-1.5 text-sm text-ink-muted leading-relaxed">
                                        Payable to {company.name}
                                        {company.tin ? ` · TIN ${company.tin}` : ''}
                                    </p>
                                </div>
                            )}
                        </div>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-6 text-ink-muted">
                                <dt>Subtotal</dt>
                                <dd className="font-mono tabular-nums text-ink">{formatCurrency(invoice.amount_before_tax, invoice.currency)}</dd>
                            </div>
                            {Number(invoice.discount_total) > 0 && (
                                <div className="flex justify-between gap-6 text-ink-muted">
                                    <dt>Discount</dt>
                                    <dd className="font-mono tabular-nums text-terracotta">−{formatCurrency(invoice.discount_total, invoice.currency)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between gap-6 text-ink-muted">
                                <dt>Tax</dt>
                                <dd className="font-mono tabular-nums text-ink">{formatCurrency(invoice.tax_amount, invoice.currency)}</dd>
                            </div>
                            {Number(invoice.shipping_amount) > 0 && (
                                <div className="flex justify-between gap-6 text-ink-muted">
                                    <dt>Shipping</dt>
                                    <dd className="font-mono tabular-nums text-ink">{formatCurrency(invoice.shipping_amount, invoice.currency)}</dd>
                                </div>
                            )}
                            {Number(invoice.rounding_adjustment) !== 0 && (
                                <div className="flex justify-between gap-6 text-ink-muted">
                                    <dt>Rounding</dt>
                                    <dd className="font-mono tabular-nums text-ink">{formatCurrency(invoice.rounding_adjustment, invoice.currency)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between gap-6 pt-2 border-t border-ink/15 text-base font-semibold text-ink">
                                <dt>Total</dt>
                                <dd className="font-mono tabular-nums">{formatCurrency(invoice.total_amount, invoice.currency)}</dd>
                            </div>
                            {paid > 0 && (
                                <div className="flex justify-between gap-6 text-ink-muted">
                                    <dt>Paid</dt>
                                    <dd className="font-mono tabular-nums text-forest">−{formatCurrency(paid, invoice.currency)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between gap-4 mt-2 px-3 py-2.5 rounded-xl bg-ink text-white">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-white/70 self-center">Amount due</dt>
                                <dd className="font-mono tabular-nums text-lg font-semibold">{formatCurrency(due, invoice.currency)}</dd>
                            </div>
                        </dl>
                    </div>
                </article>

                <aside className="lg:sticky lg:top-4 space-y-4">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                        <p className={sectionTitle}>Amount due</p>
                        <p className={`mt-1 text-3xl font-display font-medium tabular-nums ${due > 0 ? 'text-terracotta' : 'text-forest'}`}>
                            {formatCurrency(due, invoice.currency)}
                        </p>
                        <p className="mt-1 text-xs text-ink-muted">
                            of {formatCurrency(invoice.total_amount, invoice.currency)}
                            {paid > 0 ? ` · ${formatCurrency(paid, invoice.currency)} received` : ''}
                        </p>
                    </div>

                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                        {canEmail && (
                            <button type="button" className={primary} onClick={() => postAction(route('invoices.email', invoice.id))}>Email customer</button>
                        )}
                        <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noreferrer" className={btn}>View PDF</a>
                        {public_html_url && (
                            <a href={public_html_url} target="_blank" rel="noreferrer" className={primary}>View & Pay</a>
                        )}
                        {canShare && (
                            <ShareButtons publicUrl={public_html_url || public_pdf_url} whatsappUrl={whatsapp_url} className={btn} />
                        )}
                        {auth.planPermissions?.['general-ledger.view'] && journal_entry_id && (
                            <Link href={route('general-ledger.show', journal_entry_id)} className={btn}>View journal</Link>
                        )}
                        {auth.permissions.includes('invoices.create') && (
                            <button type="button" className={ghost} onClick={() => postAction(route('invoices.duplicate', invoice.id))}>Duplicate</button>
                        )}
                        {can_issue_late_fee && auth.permissions.includes('invoices.create') && (
                            <button
                                type="button"
                                className={ghost}
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
                                className={`${ghost} text-terracotta hover:text-terracotta`}
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
                                        {formatCurrency(p.amount, invoice.currency)}
                                        {!p.reversed_at && (
                                            <a className="text-terracotta no-underline text-xs" href={route('invoices.payment-receipt', [invoice.id, p.id])} target="_blank" rel="noreferrer">Receipt</a>
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
                                    <span className="font-mono tabular-nums">−{formatCurrency(a.amount, invoice.currency)}</span>
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
                                    <button className={primary} disabled={pay.processing}>Record payment</button>
                                </form>
                            )}
                            {pay_now_configured && due > 0 && (
                                <button type="button" className={btn} onClick={() => postAction(route('invoices.pay-now', invoice.id))}>Pay Now</button>
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
                                                <span className="font-mono text-sm tabular-nums text-ink shrink-0">{formatCurrency(open, invoice.currency)}</span>
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
                                                <span className="font-mono text-sm tabular-nums text-ink shrink-0">{formatCurrency(open, invoice.currency)}</span>
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
                                <button type="button" className={primary} onClick={() => postAction(route('invoices.myinvois.submit', invoice.id))}>Submit e-invoice</button>
                            )}
                            {invoice.lhdn_uuid && auth.planPermissions?.['myinvois.submit'] && (
                                <button type="button" className={btn} onClick={() => postAction(route('invoices.myinvois.refresh', invoice.id))}>Refresh status</button>
                            )}
                            {can_cancel_einvoice && (
                                <form onSubmit={(e) => { e.preventDefault(); cancelEinvoice.post(route('invoices.myinvois.cancel', invoice.id)); }} className="space-y-2">
                                    <input className={field} placeholder="Cancel reason" value={cancelEinvoice.data.reason} onChange={(e) => cancelEinvoice.setData('reason', e.target.value)} />
                                    <button className={btn} type="submit">Cancel within 72h</button>
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
                                <button className={btn} type="submit">Upload</button>
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
                                    <button className={btn} type="submit">Create template</button>
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
                                    <button className={btn} type="submit">Save reminders</button>
                                </form>
                            </MoreSection>
                        )}
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
