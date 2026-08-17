import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency, currencyDecimals } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import ShareButtons from '@/Components/ShareButtons';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

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
    whatsapp_url = null,
    document_trail = [],
    reminder_offsets = [-14, -7, -3, 0, 3, 7, 14],
    late_fee_percent = 1.5,
    can_issue_late_fee = false,
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
    const applyCredit = useForm({ invoice_id: invoice.id, amount: '' });
    const applyDeposit = useForm({ invoice_id: invoice.id, amount: '' });
    const recurring = useForm({ cadence: 'monthly', interval: 1, start_date: new Date().toISOString().split('T')[0], auto_email: false, auto_post: false });
    const cancelEinvoice = useForm({ reason: '' });
    const reminders = useForm({ offsets: reminder_offsets });

    const postAction = (url, body = {}) => router.post(url, body, { preserveScroll: true });

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={invoice.invoice_number} />
            <div className="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">
                <Link href={route('invoices.index')} className="text-xs font-semibold text-ink-muted hover:text-ink">← Back to invoices</Link>
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-display font-medium text-ink">{invoice.invoice_number}</h1>
                            <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-cream">{invoice.status}</span>
                            {invoice.is_cash_sale && <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-forest/10 text-forest">Cash sale</span>}
                            {invoice.is_late_fee && <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-amber-50 text-amber-800">Late fee</span>}
                            {invoice.lhdn_status && invoice.lhdn_status !== 'pending' && (
                                <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-blue-50 text-blue-800">MyInvois {invoice.lhdn_status}</span>
                            )}
                        </div>
                        <p className="text-sm text-ink-muted mt-1">{invoice.customer?.name} · balance {formatCurrency(balance, invoice.currency)}</p>
                        {invoice.last_viewed_at && (
                            <p className="text-xs text-ink-muted mt-1">Viewed {invoice.view_count || 1}× · last {new Date(invoice.last_viewed_at).toLocaleString('en-MY')}</p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {auth.permissions.includes('invoices.create') && (
                            <button type="button" className={btn} onClick={() => postAction(route('invoices.duplicate', invoice.id))}>Duplicate</button>
                        )}
                        {isDraft && auth.permissions.includes('invoices.edit') && (
                            <Link href={route('invoices.edit', invoice.id)} className={btn}>Edit</Link>
                        )}
                        <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noreferrer" className={btn}>PDF</a>
                        {invoice.customer?.email && auth.permissions.includes('invoices.email') && (
                            <button type="button" className={btn} onClick={() => postAction(route('invoices.email', invoice.id))}>Email</button>
                        )}
                        <ShareButtons publicUrl={public_pdf_url} whatsappUrl={whatsapp_url} />
                        {isDraft && auth.permissions.includes('invoices.post') && (
                            <button type="button" className={primary} onClick={async () => { if (await confirm({ title: 'Post to ledger?', confirmText: 'Post' })) postAction(route('invoices.post', invoice.id)); }}>Post</button>
                        )}
                        {can_issue_late_fee && auth.permissions.includes('invoices.create') && (
                            <button
                                type="button"
                                className={btn}
                                onClick={async () => {
                                    if (await confirm({ title: `Create ${late_fee_percent}% late interest invoice?`, confirmText: 'Create draft' })) {
                                        postAction(route('invoices.late-fee', invoice.id));
                                    }
                                }}
                            >
                                Late fee {late_fee_percent}%
                            </button>
                        )}
                    </div>
                </div>

                <DocumentTrail steps={document_trail} />

                <div className="bg-surface rounded-2xl border border-border-warm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/50 text-[10px] uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Description</th>
                                <th className="px-3 py-3 text-right">Qty</th>
                                <th className="px-3 py-3 text-right">Price</th>
                                <th className="px-3 py-3 text-right">Tax</th>
                                <th className="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(invoice.items || []).map((item) => (
                                <tr key={item.id} className="border-t border-border-warm">
                                    <td className="px-4 py-3 whitespace-pre-wrap">{item.description}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.quantity}</td>
                                    <td className="px-3 py-3 text-right font-mono">{Number(item.unit_price).toFixed(decimals)}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.tax_rate}%</td>
                                    <td className="px-4 py-3 text-right font-mono">{Number(item.amount).toFixed(decimals)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="p-4 text-right text-sm">
                        <div>Tax {formatCurrency(invoice.tax_amount, invoice.currency)}</div>
                        <div className="text-lg font-semibold text-terracotta">Total {formatCurrency(invoice.total_amount, invoice.currency)}</div>
                    </div>
                </div>

                {!isDraft && !isVoid && (
                    <div className="grid md:grid-cols-2 gap-4">
                        <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                            <h3 className="text-sm font-semibold">Knock-off / payments</h3>
                            {(invoice.payments || []).map((p) => (
                                <div key={p.id} className={`flex justify-between text-sm gap-2 ${p.reversed_at ? 'line-through text-ink-muted' : ''}`}>
                                    <span>{formatDate(p.payment_date)} · {p.bank_account_code}{p.reversed_at ? ' · reversed' : ''}</span>
                                    <span className="font-mono inline-flex items-center gap-2">
                                        {formatCurrency(p.amount, invoice.currency)}{' '}
                                        {!p.reversed_at && (
                                            <a className="text-terracotta no-underline" href={route('invoices.payment-receipt', [invoice.id, p.id])} target="_blank" rel="noreferrer">Receipt</a>
                                        )}
                                        {!p.reversed_at && auth.permissions.includes('invoices.record-payment') && (
                                            <button
                                                type="button"
                                                className="text-terracotta no-underline"
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
                                    <span className="font-mono">-{formatCurrency(a.amount, invoice.currency)}</span>
                                </div>
                            ))}
                            {auth.permissions.includes('invoices.record-payment') && Number(balance) > 0 && (
                                <form onSubmit={(e) => { e.preventDefault(); pay.post(route('invoices.record-payment', invoice.id), { preserveScroll: true }); }} className="space-y-2 pt-2 border-t border-border-warm">
                                    <input className="w-full border rounded-xl px-3 py-2 text-sm" type="number" step="0.01" value={pay.data.amount} onChange={(e) => pay.setData('amount', e.target.value)} />
                                    <select className="w-full border rounded-xl px-3 py-2 text-sm" value={pay.data.bank_account_code} onChange={(e) => pay.setData('bank_account_code', e.target.value)}>
                                        {bankAccounts.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                                    </select>
                                    <button className={primary} disabled={pay.processing}>Record payment</button>
                                </form>
                            )}
                            {pay_now_configured && Number(balance) > 0 && (
                                <button type="button" className={btn} onClick={() => postAction(route('invoices.pay-now', invoice.id))}>Pay Now</button>
                            )}
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                            <h3 className="text-sm font-semibold">Apply credit / deposit</h3>
                            {openCredits.map((cn) => (
                                <div key={cn.id} className="flex gap-2 text-sm items-center">
                                    <span className="flex-1">{cn.cn_number} open {formatCurrency((cn.total_amount - cn.applied_amount - (cn.refunded_amount || 0)), invoice.currency)}</span>
                                    <button type="button" className={btn} onClick={() => router.post(route('credit-notes.apply', cn.id), { invoice_id: invoice.id, amount: Number(cn.total_amount) - Number(cn.applied_amount) - Number(cn.refunded_amount || 0) }, { preserveScroll: true })}>Apply</button>
                                </div>
                            ))}
                            {openDeposits.map((d) => (
                                <div key={d.id} className="flex gap-2 text-sm items-center">
                                    <span className="flex-1">Deposit {formatCurrency(d.amount - d.applied_amount, invoice.currency)}</span>
                                    <button type="button" className={btn} onClick={() => router.post(route('ar-deposits.apply', d.id), { invoice_id: invoice.id, amount: Number(d.amount) - Number(d.applied_amount) }, { preserveScroll: true })}>Apply</button>
                                </div>
                            ))}
                            {auth.permissions.includes('credit-notes.create') && (
                                <Link href={route('credit-notes.create', invoice.id)} className={btn}>Copy to credit note</Link>
                            )}
                            {auth.permissions.includes('debit-notes.create') && (
                                <Link href={`${route('debit-notes.create')}?invoice_id=${invoice.id}`} className={btn}>Debit note</Link>
                            )}
                        </div>
                    </div>
                )}

                <div className="grid md:grid-cols-2 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                        <h3 className="text-sm font-semibold">MyInvois</h3>
                        {myinvois_gaps.length > 0 ? (
                            <ul className="text-sm text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>
                        ) : (
                            <p className="text-sm text-forest">Ready to submit.</p>
                        )}
                        {invoice.lhdn_uuid && <p className="text-xs font-mono break-all">UUID {invoice.lhdn_uuid}</p>}
                        {invoice.lhdn_qr_url && <a className="text-sm text-terracotta" href={invoice.lhdn_qr_url} target="_blank" rel="noreferrer">QR / share link</a>}
                        {auth.planPermissions?.['myinvois.submit'] && auth.permissions.includes('myinvois.submit') && !invoice.lhdn_uuid && myinvois_gaps.length === 0 && (
                            <button type="button" className={primary} onClick={() => postAction(route('invoices.myinvois.submit', invoice.id))}>Submit e-invoice</button>
                        )}
                        {invoice.lhdn_uuid && auth.planPermissions?.['myinvois.submit'] && (
                            <button type="button" className={btn} onClick={() => postAction(route('invoices.myinvois.refresh', invoice.id))}>Refresh status</button>
                        )}
                        {can_cancel_einvoice && (
                            <form onSubmit={(e) => { e.preventDefault(); cancelEinvoice.post(route('invoices.myinvois.cancel', invoice.id)); }} className="space-y-2">
                                <input className="w-full border rounded-xl px-3 py-2 text-sm" placeholder="Cancel reason" value={cancelEinvoice.data.reason} onChange={(e) => cancelEinvoice.setData('reason', e.target.value)} />
                                <button className={btn} type="submit">Cancel within 72h</button>
                            </form>
                        )}
                        {auth.planPermissions?.['myinvois.submit'] && (
                            <Link href={route('myinvois.consolidated.index')} className="text-sm text-terracotta">Consolidated e-invoice →</Link>
                        )}
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                        <h3 className="text-sm font-semibold">Attachments & recurring</h3>
                        {(invoice.attachments || []).map((a) => (
                            <div key={a.id} className="flex justify-between text-sm">
                                <span>{a.original_name}</span>
                                <button type="button" className="text-terracotta" onClick={() => router.delete(route('invoices.detach', [invoice.id, a.id]))}>Remove</button>
                            </div>
                        ))}
                        <form onSubmit={(e) => { e.preventDefault(); if (!file) return; router.post(route('invoices.attach', invoice.id), { file }, { forceFormData: true, preserveScroll: true }); }} className="flex gap-2">
                            <input type="file" onChange={(e) => setFile(e.target.files?.[0])} />
                            <button className={btn} type="submit">Upload</button>
                        </form>
                        {auth.permissions.includes('recurring-invoices.create') && (
                            <form onSubmit={(e) => { e.preventDefault(); recurring.post(route('invoices.create-recurring', invoice.id)); }} className="space-y-2 pt-2 border-t border-border-warm">
                                <select className="w-full border rounded-xl px-3 py-2 text-sm" value={recurring.data.cadence} onChange={(e) => recurring.setData('cadence', e.target.value)}>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                                <label className="text-sm flex gap-2 items-center">
                                    <input type="checkbox" checked={recurring.data.auto_email} onChange={(e) => recurring.setData('auto_email', e.target.checked)} />
                                    Auto-email generated invoices
                                </label>
                                <label className="text-sm flex gap-2 items-center">
                                    <input type="checkbox" checked={recurring.data.auto_post} onChange={(e) => recurring.setData('auto_post', e.target.checked)} />
                                    Auto-post generated invoices
                                </label>
                                <button className={btn} type="submit">Create recurring from this invoice</button>
                            </form>
                        )}
                        {!isDraft && !isVoid && (
                            <form onSubmit={(e) => { e.preventDefault(); reminders.post(route('invoices.reminders', invoice.id)); }} className="space-y-2 pt-2 border-t border-border-warm">
                                <h4 className="text-sm font-semibold">Reminders</h4>
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
                        )}
                    </div>
                </div>

                {!isDraft && !isVoid && auth.permissions.includes('invoices.void') && (
                    <button type="button" className="text-sm text-terracotta" onClick={async () => { if (await confirm({ title: 'Void invoice?', confirmText: 'Void', confirmColor: '#dc2626' })) postAction(route('invoices.void', invoice.id)); }}>Void invoice</button>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
