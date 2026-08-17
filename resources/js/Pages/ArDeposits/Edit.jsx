import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Edit({ auth, deposit, editable = true, lock_reason = null, amount_locked = false, bankAccounts = [] }) {
    const { data, setData, put, processing } = useForm({
        payment_date: deposit.payment_date?.slice?.(0, 10) || deposit.payment_date || '',
        reference: deposit.reference || '',
        notes: deposit.notes || '',
        amount: deposit.amount ?? '',
        bank_account_code: deposit.bank_account_code || bankAccounts[0]?.code || '',
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit receipt</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">{deposit.customer?.name} · {deposit.reference || `DEP-${deposit.id}`}</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('ar-deposits.show', deposit.id)} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    {editable && (
                        <button type="submit" form="ar-edit-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                            {processing ? 'Saving…' : 'Save changes'}
                        </button>
                    )}
                </div>
            </div>
        }>
            <Head title="Edit receipt" />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            {amount_locked && editable && (
                <div className="mb-4 rounded-xl border border-border-warm bg-cream px-4 py-3 text-sm text-ink-muted">Amount and bank account are locked after applications.</div>
            )}
            <form id="ar-edit-form" className="space-y-4 min-w-0 max-w-2xl" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('ar-deposits.update', deposit.id)); }}>
                <div className="bg-surface rounded-2xl border border-border-warm p-4 sm:p-5">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Date</label>
                            <input type="date" className={inputClass} value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div>
                            <label className={labelClass}>Reference</label>
                            <input className={inputClass} value={data.reference} onChange={(e) => setData('reference', e.target.value)} disabled={!editable} />
                        </div>
                        <div>
                            <label className={labelClass}>Amount</label>
                            <input type="number" step="0.01" className={`${inputClass} font-mono`} value={data.amount} onChange={(e) => setData('amount', e.target.value)} disabled={!editable || amount_locked} />
                        </div>
                        <div>
                            <label className={labelClass}>Bank / cash</label>
                            <select className={inputClass} value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} disabled={!editable || amount_locked}>
                                {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                            </select>
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Notes</label>
                            <input className={inputClass} value={data.notes} onChange={(e) => setData('notes', e.target.value)} disabled={!editable} />
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
