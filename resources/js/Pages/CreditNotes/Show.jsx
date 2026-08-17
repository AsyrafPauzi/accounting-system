import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import ShareButtons from '@/Components/ShareButtons';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

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
}) {
    const currency = creditNote.currency || 'MYR';
    const open = Number(creditNote.open_amount ?? (creditNote.total_amount - (creditNote.applied_amount || 0) - (creditNote.refunded_amount || 0)));
    const refund = useForm({
        amount: open > 0 ? open.toFixed(2) : '',
        payment_date: new Date().toISOString().slice(0, 10),
        bank_account_code: bankAccounts[0]?.code || '',
        reference: '',
    });
    const apply = useForm({ invoice_id: openInvoices[0]?.id || '', amount: open > 0 ? open.toFixed(2) : '' });

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={creditNote.cn_number} />
            <div className="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">
                <Link href={route('credit-notes.index')} className="text-xs font-semibold text-ink-muted hover:text-ink">← Credit notes</Link>
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-display font-medium text-ink">{creditNote.cn_number}</h1>
                            <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-cream">{creditNote.status}</span>
                            {creditNote.lhdn_status && creditNote.lhdn_status !== 'pending' && (
                                <span className="text-[10px] uppercase font-semibold px-2 py-1 rounded-md bg-blue-50 text-blue-800">MyInvois {creditNote.lhdn_status}</span>
                            )}
                        </div>
                        <p className="text-sm text-ink-muted mt-1">
                            {creditNote.customer?.name}
                            {creditNote.invoice?.invoice_number ? ` · against ${creditNote.invoice.invoice_number}` : ''}
                            {' · open '}{formatCurrency(open, currency)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a className={btn} href={route('credit-notes.pdf', creditNote.id)} target="_blank" rel="noreferrer">PDF</a>
                        <ShareButtons publicUrl={public_pdf_url} whatsappUrl={whatsapp_url} />
                        {auth.permissions.includes('credit-notes.create') && creditNote.status !== 'void' && (
                            <Link className={btn} href={route('credit-notes.edit', creditNote.id)}>Edit</Link>
                        )}
                        {auth.permissions.includes('invoices.email') && (
                            <button type="button" className={btn} onClick={() => router.post(route('credit-notes.email', creditNote.id))}>Email</button>
                        )}
                        {creditNote.status !== 'void' && (
                            <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('credit-notes.void', creditNote.id))}>Void</button>
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
                                <th className="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(creditNote.items || []).map((item) => (
                                <tr key={item.id} className="border-t border-border-warm">
                                    <td className="px-4 py-3">{item.description}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.quantity}</td>
                                    <td className="px-3 py-3 text-right font-mono">{item.unit_price}</td>
                                    <td className="px-4 py-3 text-right font-mono">{item.amount}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="p-4 text-right text-sm space-y-1">
                        <div>Tax {formatCurrency(creditNote.tax_amount, currency)}</div>
                        <div className="text-lg font-semibold text-terracotta">Total {formatCurrency(creditNote.total_amount, currency)}</div>
                        <div className="text-ink-muted">Applied {formatCurrency(creditNote.applied_amount || 0, currency)} · Refunded {formatCurrency(creditNote.refunded_amount || 0, currency)}</div>
                    </div>
                </div>

                {(creditNote.applications || []).length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-2">
                        <h3 className="text-sm font-semibold">Applied to invoices</h3>
                        {creditNote.applications.map((a) => (
                            <div key={a.id} className="flex justify-between text-sm">
                                <span>{a.invoice?.invoice_number || `Invoice #${a.invoice_id}`}</span>
                                <span className="font-mono">{formatCurrency(a.amount, currency)}</span>
                            </div>
                        ))}
                    </div>
                )}

                {creditNote.status !== 'void' && (
                    <div className="grid md:grid-cols-2 gap-4">
                        <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                            <h3 className="text-sm font-semibold">Apply leftover to an invoice</h3>
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
                                    <select className="w-full border rounded-xl px-3 py-2 text-sm" value={apply.data.invoice_id} onChange={(e) => apply.setData('invoice_id', e.target.value)}>
                                        {openInvoices.map((inv) => <option key={inv.id} value={inv.id}>{inv.invoice_number}</option>)}
                                    </select>
                                    <input className="w-full border rounded-xl px-3 py-2 text-sm" type="number" step="0.01" value={apply.data.amount} onChange={(e) => apply.setData('amount', e.target.value)} />
                                    <button className={primary} disabled={apply.processing}>Apply credit</button>
                                </form>
                            )}
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                            <h3 className="text-sm font-semibold">Refund leftover as cash</h3>
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
                                    <input className="w-full border rounded-xl px-3 py-2 text-sm" type="number" step="0.01" value={refund.data.amount} onChange={(e) => refund.setData('amount', e.target.value)} />
                                    <input className="w-full border rounded-xl px-3 py-2 text-sm" type="date" value={refund.data.payment_date} onChange={(e) => refund.setData('payment_date', e.target.value)} />
                                    <select className="w-full border rounded-xl px-3 py-2 text-sm" value={refund.data.bank_account_code} onChange={(e) => refund.setData('bank_account_code', e.target.value)}>
                                        {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                                    </select>
                                    <input className="w-full border rounded-xl px-3 py-2 text-sm" placeholder="Reference" value={refund.data.reference} onChange={(e) => refund.setData('reference', e.target.value)} />
                                    <button className={primary} disabled={refund.processing}>Refund to bank</button>
                                </form>
                            )}
                            {(creditNote.refunds || []).map((r) => (
                                <div key={r.id} className="flex justify-between text-sm text-ink-muted">
                                    <span>{formatDate(r.payment_date)} · {r.bank_account_code}</span>
                                    <span className="font-mono">{formatCurrency(r.amount, currency)}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-3">
                    <h3 className="text-sm font-semibold">MyInvois</h3>
                    {myinvois_gaps.length > 0 ? (
                        <ul className="text-sm text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>
                    ) : (
                        <p className="text-sm text-forest">Ready to submit.</p>
                    )}
                    {creditNote.lhdn_uuid && <p className="text-xs font-mono break-all">UUID {creditNote.lhdn_uuid}</p>}
                    {auth.planPermissions?.['myinvois.submit'] && !creditNote.lhdn_uuid && myinvois_gaps.length === 0 && (
                        <button type="button" className={primary} onClick={() => router.post(route('credit-notes.myinvois.submit', creditNote.id))}>Submit e-invoice</button>
                    )}
                    {creditNote.lhdn_uuid && auth.planPermissions?.['myinvois.submit'] && (
                        <button type="button" className={btn} onClick={() => router.post(route('credit-notes.myinvois.refresh', creditNote.id))}>Refresh status</button>
                    )}
                    {can_cancel_einvoice && (
                        <button type="button" className={btn} onClick={() => router.post(route('credit-notes.myinvois.cancel', creditNote.id), { reason: 'Cancelled from credit note' })}>Cancel within 72h</button>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
