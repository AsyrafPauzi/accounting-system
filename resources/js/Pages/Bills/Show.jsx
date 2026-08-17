import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, bill, bankAccounts = [], myinvois_gaps = [], trail = [] }) {
    const currency = bill.currency || 'MYR';
    const balance = Number(bill.balance_due ?? 0);
    const pay = useForm({
        amount: balance > 0 ? balance.toFixed(2) : '',
        payment_date: new Date().toISOString().slice(0, 10),
        bank_account_code: bankAccounts[0]?.code || '',
        reference: '',
    });

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={bill.bill_number} />
            <div className="max-w-4xl mx-auto p-6 space-y-6">
                <Link href={route('bills.index')} className="text-xs text-ink-muted">← Bills</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{bill.bill_number}</h1>
                        <p className="text-sm text-ink-muted">
                            {bill.supplier?.name} · {formatDate(bill.bill_date)} · {bill.status}
                            {bill.purchase_kind && bill.purchase_kind !== 'credit' ? ` · ${bill.purchase_kind === 'cash' ? 'cash purchase' : 'expense claim'}` : ''}
                            {bill.due_date ? ` · due ${formatDate(bill.due_date)}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {bill.status === 'draft' && auth.permissions.includes('bills.edit') && (
                            <Link className={btn} href={route('bills.edit', bill.id)}>Edit</Link>
                        )}
                        {bill.status === 'draft' && auth.permissions.includes('bills.post') && (
                            <button type="button" className={primary} onClick={() => router.post(route('bills.post', bill.id))}>Post</button>
                        )}
                        {auth.permissions.includes('bills.create') && (
                            <Link className={btn} href={route('supplier-credit-notes.create', { bill_id: bill.id })}>Credit note</Link>
                        )}
                    </div>
                </div>
                <DocumentTrail steps={trail} />
                <div className="bg-surface rounded-2xl border p-5 space-y-2 text-sm">
                    <h3 className="text-sm font-semibold">Self-billed e-invoice (type 12)</h3>
                    <p className="text-ink-muted">You issue this to LHDN on behalf of the supplier. It does not post a second purchase journal.</p>
                    <p>Status: {bill.lhdn_status || 'pending'}{bill.lhdn_uuid ? ` · ${bill.lhdn_uuid}` : ''}</p>
                    {bill.lhdn_reject_reason && <p className="text-terracotta">{bill.lhdn_reject_reason}</p>}
                    {myinvois_gaps.length > 0 && <ul className="text-terracotta list-disc pl-4">{myinvois_gaps.map((g) => <li key={g}>{g}</li>)}</ul>}
                    {auth.planPermissions?.['myinvois.submit'] && !bill.lhdn_uuid && myinvois_gaps.length === 0 && (
                        <button type="button" className={primary} onClick={() => router.post(route('bills.myinvois.submit', bill.id))}>Submit self-billed e-invoice</button>
                    )}
                    {bill.lhdn_uuid && auth.planPermissions?.['myinvois.submit'] && (
                        <button type="button" className={btn} onClick={() => router.post(route('bills.myinvois.refresh', bill.id))}>Refresh status</button>
                    )}
                    {bill.lhdn_uuid && bill.lhdn_status !== 'cancelled' && auth.planPermissions?.['myinvois.submit'] && (
                        <button type="button" className={btn} onClick={() => router.post(route('bills.myinvois.cancel', bill.id), { reason: 'Cancelled from bill' })}>Cancel within 72h</button>
                    )}
                </div>
                <div className="bg-surface rounded-2xl border p-5 space-y-2 text-sm">
                    {(bill.items || []).map((i) => (
                        <div key={i.id} className="flex justify-between">
                            <span>{i.description}</span>
                            <span className="font-mono">{formatCurrency(i.amount, currency)}</span>
                        </div>
                    ))}
                    <div className="flex justify-between pt-2 border-t"><span>Tax</span><span className="font-mono">{formatCurrency(bill.tax_amount, currency)}</span></div>
                    <div className="flex justify-between font-semibold"><span>Total</span><span className="font-mono">{formatCurrency(bill.total_amount, currency)}</span></div>
                    <div className="flex justify-between text-terracotta"><span>Balance</span><span className="font-mono">{formatCurrency(balance, currency)}</span></div>
                </div>
                {(bill.payments || []).length > 0 && (
                    <div className="bg-surface rounded-2xl border p-5 space-y-2">
                        <h3 className="text-sm font-semibold">Payments</h3>
                        {bill.payments.map((p) => (
                            <div key={p.id} className="flex justify-between text-sm">
                                <span>{formatDate(p.payment_date)} · {p.bank_account_code}</span>
                                <span className="font-mono">{formatCurrency(p.amount, currency)}</span>
                            </div>
                        ))}
                    </div>
                )}
                {(bill.credit_note_applications || []).length > 0 && (
                    <div className="bg-surface rounded-2xl border p-5 space-y-2">
                        <h3 className="text-sm font-semibold">Credits applied</h3>
                        {bill.credit_note_applications.map((a) => (
                            <div key={a.id} className="flex justify-between text-sm">
                                <span>{a.credit_note?.scn_number || 'SCN'}</span>
                                <span className="font-mono">{formatCurrency(a.amount, currency)}</span>
                            </div>
                        ))}
                    </div>
                )}
                {balance > 0 && !['draft', 'void'].includes(bill.status) && auth.permissions.includes('bills.record-payment') && (
                    <form className="bg-surface rounded-2xl border p-5 space-y-2" onSubmit={(e) => { e.preventDefault(); pay.post(route('bills.record-payment', bill.id)); }}>
                        <h3 className="text-sm font-semibold">{bill.purchase_kind === 'claim' ? 'Reimburse' : 'Record payment'}</h3>
                        <input type="number" step="0.01" className="w-full border rounded-xl px-3 py-2" value={pay.data.amount} onChange={(e) => pay.setData('amount', e.target.value)} />
                        <input type="date" className="w-full border rounded-xl px-3 py-2" value={pay.data.payment_date} onChange={(e) => pay.setData('payment_date', e.target.value)} />
                        <select className="w-full border rounded-xl px-3 py-2" value={pay.data.bank_account_code} onChange={(e) => pay.setData('bank_account_code', e.target.value)}>
                            {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                        </select>
                        <input className="w-full border rounded-xl px-3 py-2" placeholder="Reference" value={pay.data.reference} onChange={(e) => pay.setData('reference', e.target.value)} />
                        <button className={primary} disabled={pay.processing}>{bill.purchase_kind === 'claim' ? 'Save reimbursement' : 'Save payment'}</button>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
