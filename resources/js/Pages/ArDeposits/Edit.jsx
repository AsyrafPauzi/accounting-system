import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function Edit({ auth, deposit, editable = true, lock_reason = null, amount_locked = false, bankAccounts = [] }) {
    const { data, setData, put, processing } = useForm({
        payment_date: deposit.payment_date?.slice?.(0, 10) || deposit.payment_date || '',
        reference: deposit.reference || '',
        notes: deposit.notes || '',
        amount: deposit.amount ?? '',
        bank_account_code: deposit.bank_account_code || bankAccounts[0]?.code || '',
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('ar-deposits.show', deposit.id)}
                    title="Edit receipt"
                    subtitle={`${deposit.customer?.name || 'Customer'} · ${deposit.reference || `DEP-${deposit.id}`}`}
                    formId="ar-edit-form"
                    processing={processing}
                    submitLabel="Save changes"
                    showSubmit={editable}
                />
            }
        >
            <Head title="Edit receipt" />
            <form id="ar-edit-form" className="space-y-6 pb-12 min-w-0" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('ar-deposits.update', deposit.id)); }}>
                {!editable && lock_reason && (
                    <div className="rounded-2xl border border-terracotta/30 bg-terracotta/10 px-5 py-4 text-sm text-terracotta">{lock_reason}</div>
                )}
                {amount_locked && editable && (
                    <div className="rounded-2xl border border-border-warm bg-cream px-5 py-4 text-sm text-ink-muted">Amount and bank account are locked after applications.</div>
                )}
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Receipt details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Date</label>
                            <input type="date" className={inputClass} value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Amount</label>
                            <input type="number" step="0.01" className={`${inputClass} font-mono text-right`} value={data.amount} onChange={(e) => setData('amount', e.target.value)} disabled={!editable || amount_locked} />
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Bank / cash</label>
                            <select className={inputClass} value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} disabled={!editable || amount_locked}>
                                {bankAccounts.map((a) => <option key={a.code} value={a.code}>{a.code} {a.name}</option>)}
                            </select>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Reference</label>
                            <input className={inputClass} value={data.reference} onChange={(e) => setData('reference', e.target.value)} disabled={!editable} />
                        </div>
                    </div>
                </div>
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <label className={labelClass}>Notes (internal)</label>
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        className={`${inputClass} resize-none h-28`}
                        disabled={!editable}
                        placeholder="Optional"
                    />
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
