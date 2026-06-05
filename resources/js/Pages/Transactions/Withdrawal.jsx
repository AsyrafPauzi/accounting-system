import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const Icons = {
    ArrowUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>,
};

const groupLabels = {
    expense:   'Expense',
    asset:     'Asset',
    liability: 'Liability',
    equity:    'Equity',
    income:    'Income',
};

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
    const orderedTypes = ['expense', 'asset', 'liability', 'equity', 'income'].filter(t => grouped[t]?.length);

    const submit = (e) => {
        e.preventDefault();
        post(route('transactions.withdrawal.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center gap-3">
                    <span className="p-2.5 rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                        <Icons.ArrowUp />
                    </span>
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-rose-600">Money out</p>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Add Withdrawal</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Record money leaving a bank or cash account.</p>
                    </div>
                </div>
            }
        >
            <Head title="Add Withdrawal" />

            <form onSubmit={submit} className="space-y-6 max-w-3xl">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label className="block text-xs font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Date *</label>
                            <input
                                type="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta"
                                required
                            />
                            {errors.date && <p className="mt-1 text-xs text-rose-600">{errors.date}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Amount *</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                placeholder="0.00"
                                className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-mono tabular-nums focus:ring-2 focus:ring-terracotta"
                                required
                            />
                            {errors.amount && <p className="mt-1 text-xs text-rose-600">{errors.amount}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Withdraw from (bank / cash) *</label>
                        <select
                            value={data.bank_account_id}
                            onChange={(e) => setData('bank_account_id', e.target.value)}
                            className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta"
                            required
                        >
                            <option value="">Select bank or cash account…</option>
                            {bank_accounts.map(a => (
                                <option key={a.id} value={a.id}>{a.label}</option>
                            ))}
                        </select>
                        {errors.bank_account_id && <p className="mt-1 text-xs text-rose-600">{errors.bank_account_id}</p>}
                        {bank_accounts.length === 0 && (
                            <p className="mt-2 text-xs text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2">
                                No bank or cash accounts found. Add one under <Link href={route('chart-of-accounts.index')} className="underline font-semibold">Chart of Accounts</Link> first.
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Category (where did it go?) *</label>
                        <select
                            value={data.category_account_id}
                            onChange={(e) => setData('category_account_id', e.target.value)}
                            className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta"
                            required
                        >
                            <option value="">Select destination category…</option>
                            {orderedTypes.map(type => (
                                <optgroup key={type} label={groupLabels[type] || type}>
                                    {grouped[type].map(a => (
                                        <option key={a.id} value={a.id}>{a.label}</option>
                                    ))}
                                </optgroup>
                            ))}
                        </select>
                        {errors.category_account_id && <p className="mt-1 text-xs text-rose-600">{errors.category_account_id}</p>}
                        <p className="mt-1.5 text-[11px] text-ink-muted">
                            Examples: <em>Expense</em> for office costs · <em>Asset</em> for equipment purchase · <em>Liability</em> for paying down a loan · <em>Equity</em> for owner's drawings.
                        </p>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Description</label>
                        <input
                            type="text"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="What was this withdrawal for?"
                            className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-terracotta"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Reference number</label>
                        <input
                            type="text"
                            value={data.reference_number}
                            onChange={(e) => setData('reference_number', e.target.value)}
                            placeholder="e.g. cheque #, transfer ID"
                            className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-terracotta"
                        />
                    </div>
                </div>

                <div className="bg-rose-50 dark:bg-rose-900/20 rounded-2xl border border-rose-200 dark:border-rose-900/40 p-4 text-xs text-rose-900 dark:text-rose-200">
                    <strong>What this does:</strong> debits the chosen category (expense / asset / liability) and credits the bank/cash account (money out). The journal entry is posted immediately and shows up in your General Ledger and Trial Balance.
                </div>

                <div className="flex items-center gap-3">
                    <Link
                        href={route('transactions.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-surface-alt"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing || bank_accounts.length === 0}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Saving…' : 'Save withdrawal'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
