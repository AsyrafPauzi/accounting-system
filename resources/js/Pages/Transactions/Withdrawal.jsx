import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const groupLabels = {
    expense: 'Expense',
    asset: 'Asset',
    liability: 'Liability',
    equity: 'Equity',
    income: 'Income',
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function Withdrawal({ auth, bank_accounts = [], category_accounts = [], today = '' }) {
    const { data, setData, post, processing, errors } = useForm({
        date: today,
        bank_account_id: bank_accounts[0]?.id ?? '',
        category_account_id: '',
        amount: '',
        description: '',
        reference_number: '',
    });

    const grouped = category_accounts.reduce((acc, a) => {
        (acc[a.type] = acc[a.type] || []).push(a);
        return acc;
    }, {});
    const orderedTypes = ['expense', 'asset', 'liability', 'equity', 'income'].filter((t) => grouped[t]?.length);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('transactions.index')}
                    title="Add withdrawal"
                    subtitle="Record money leaving a bank or cash account"
                    formId="bank-withdrawal-form"
                    processing={processing}
                    submitLabel="Save withdrawal"
                    submitDisabled={bank_accounts.length === 0}
                />
            }
        >
            <Head title="Add Withdrawal" />
            <form id="bank-withdrawal-form" onSubmit={(e) => { e.preventDefault(); post(route('transactions.withdrawal.store')); }} className="space-y-6 pb-12 min-w-0">
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Withdrawal details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Date</label>
                            <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className={inputClass} required />
                            {errors.date && <p className="mt-1 text-xs text-terracotta">{errors.date}</p>}
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Amount</label>
                            <input type="number" step="0.01" min="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} placeholder="0.00" className={`${inputClass} font-mono text-right`} required />
                            {errors.amount && <p className="mt-1 text-xs text-terracotta">{errors.amount}</p>}
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Withdraw from (bank / cash)</label>
                            <select value={data.bank_account_id} onChange={(e) => setData('bank_account_id', e.target.value)} className={inputClass} required>
                                <option value="">Select bank or cash account…</option>
                                {bank_accounts.map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
                            </select>
                            {errors.bank_account_id && <p className="mt-1 text-xs text-terracotta">{errors.bank_account_id}</p>}
                            {bank_accounts.length === 0 && (
                                <p className="mt-2 text-xs text-ink-muted">No bank or cash accounts found. Add one under <Link href={route('chart-of-accounts.index')} className="underline font-semibold">Chart of Accounts</Link> first.</p>
                            )}
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Category (where did it go?)</label>
                            <select value={data.category_account_id} onChange={(e) => setData('category_account_id', e.target.value)} className={inputClass} required>
                                <option value="">Select destination category…</option>
                                {orderedTypes.map((type) => (
                                    <optgroup key={type} label={groupLabels[type] || type}>
                                        {grouped[type].map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
                                    </optgroup>
                                ))}
                            </select>
                            {errors.category_account_id && <p className="mt-1 text-xs text-terracotta">{errors.category_account_id}</p>}
                            <p className="mt-1.5 text-[11px] text-ink-muted">Expense for costs · Asset for equipment · Liability for loan repayments · Equity for drawings.</p>
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Description</label>
                            <input type="text" value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="What was this withdrawal for?" className={inputClass} />
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Reference</label>
                            <input type="text" value={data.reference_number} onChange={(e) => setData('reference_number', e.target.value)} placeholder="Cheque #, transfer ID" className={inputClass} />
                        </div>
                    </div>
                </div>
                <div className="bg-surface-alt border border-border-warm/80 p-6 rounded-2xl shadow-sm">
                    <h4 className="font-semibold text-ink text-xs uppercase tracking-wider mb-2">Posts immediately</h4>
                    <p className="text-terracotta text-sm leading-relaxed">Debits the chosen category and credits the bank/cash account. The journal shows up in General Ledger and Trial Balance.</p>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
