import React, { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDown: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>,
};

const groupLabels = {
    income: 'Income',
    expense: 'Expense',
    asset: 'Asset',
    liability: 'Liability',
    equity: 'Equity',
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function Deposit({ auth, bank_accounts = [], category_accounts = [], today = '' }) {
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
    const orderedTypes = ['income', 'liability', 'equity', 'asset', 'expense'].filter((t) => grouped[t]?.length);
    const selectedBank = useMemo(
        () => bank_accounts.find((account) => String(account.id) === String(data.bank_account_id)),
        [bank_accounts, data.bank_account_id]
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('transactions.index')}
                    title="Add deposit"
                    subtitle="Record money coming into a bank or cash account"
                    formId="bank-deposit-form"
                    processing={processing}
                    submitLabel="Save deposit"
                    submitDisabled={bank_accounts.length === 0}
                />
            }
        >
            <Head title="Add Deposit" />
            <form id="bank-deposit-form" onSubmit={(e) => { e.preventDefault(); post(route('transactions.deposit.store')); }} className="space-y-6 pb-12 min-w-0">
                <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-5 border-b border-border-warm bg-cream/40">
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.ArrowDown /></span>
                                <div>
                                    <h3 className="font-semibold text-ink">Deposit details</h3>
                                    <p className="text-sm text-ink-muted mt-0.5">Record one incoming movement and post it immediately.</p>
                                </div>
                            </div>
                        </div>

                        <div className="p-6 space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className={labelClass}>Date</label>
                                    <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className={inputClass} required />
                                    {errors.date && <p className="mt-1 text-xs text-terracotta">{errors.date}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Amount</label>
                                    <input type="number" step="0.01" min="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} placeholder="0.00" className={`${inputClass} font-mono text-right`} required />
                                    {errors.amount && <p className="mt-1 text-xs text-terracotta">{errors.amount}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Reference</label>
                                    <input type="text" value={data.reference_number} onChange={(e) => setData('reference_number', e.target.value)} placeholder="Transfer ID, cheque #" className={inputClass} />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>Deposit into</label>
                                    <select value={data.bank_account_id} onChange={(e) => setData('bank_account_id', e.target.value)} className={inputClass} required>
                                        <option value="">Select bank or cash account…</option>
                                        {bank_accounts.map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
                                    </select>
                                    {errors.bank_account_id && <p className="mt-1 text-xs text-terracotta">{errors.bank_account_id}</p>}
                                    {bank_accounts.length === 0 && (
                                        <p className="mt-2 text-xs text-ink-muted">No bank or cash accounts found. Add one under <Link href={route('chart-of-accounts.index')} className="underline font-semibold">Chart of Accounts</Link> first.</p>
                                    )}
                                </div>
                                <div>
                                    <label className={labelClass}>Category</label>
                                    <select value={data.category_account_id} onChange={(e) => setData('category_account_id', e.target.value)} className={inputClass} required>
                                        <option value="">Select source category…</option>
                                        {orderedTypes.map((type) => (
                                            <optgroup key={type} label={groupLabels[type] || type}>
                                                {grouped[type].map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
                                            </optgroup>
                                        ))}
                                    </select>
                                    {errors.category_account_id && <p className="mt-1 text-xs text-terracotta">{errors.category_account_id}</p>}
                                    <p className="mt-1.5 text-[11px] text-ink-muted">Use revenue for sales, equity for owner injections, or liability for loans received.</p>
                                </div>
                            </div>

                            <div>
                                <label className={labelClass}>Description</label>
                                <input type="text" value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="What was this deposit for?" className={inputClass} />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Selected account</p>
                            <p className="mt-2 font-semibold text-ink">{selectedBank?.name || 'Choose a bank/cash account'}</p>
                            <p className="text-sm text-ink-muted mt-1">{selectedBank?.code ? `${selectedBank.code} — ready to receive cash` : 'This is the cash or bank account that will increase.'}</p>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">What gets posted</p>
                            <ul className="mt-3 space-y-2 text-sm text-ink-muted">
                                <li><span className="font-semibold text-ink">Debit</span> the bank or cash account</li>
                                <li><span className="font-semibold text-ink">Credit</span> the category you selected</li>
                                <li>Shows up in General Ledger and Trial Balance right away</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
