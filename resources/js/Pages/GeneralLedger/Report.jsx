import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    ListBullet: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const REFERENCE_OPTIONS = [
    { value: '', label: 'All types' },
    { value: 'Invoice', label: 'Invoice' },
    { value: 'Invoice Payment', label: 'Invoice Payment' },
    { value: 'Credit Note', label: 'Credit Note' },
    { value: 'Bill', label: 'Bill' },
    { value: 'Bill Payment', label: 'Bill Payment' },
];

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Report({ auth, transactions = [], accountsMap = {}, filters = {}, stats = {}, accountOptions = [], paginator = {} }) {
    const { flash } = usePage().props;
    const { date_from = '', date_to = '', reference_type = '', account_code = '' } = filters;
    const { transactions_count = 0, total_debits = 0, total_credits = 0 } = stats;
    const { current_page = 1, last_page = 1, prev_url, next_url, total } = paginator;

    const getSourceLabel = (refType) => {
        if (refType === 'Invoice' || refType === 'Invoice Payment') return 'View Invoice';
        if (refType === 'Credit Note') return 'View Credit Note';
        if (refType === 'Bill' || refType === 'Bill Payment') return 'View Bill';
        return null;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">General Ledger Report</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            One row per debit or credit line — use filters to narrow by date, type, or account.
                        </p>
                        <p className="text-slate-400 text-xs mt-0.5">
                            Every line from posted invoices, payments, and credit notes.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`${route('general-ledger.report.export.csv')}?${new URLSearchParams(Object.fromEntries(Object.entries({ date_from, date_to, reference_type, account_code }).filter(([, v]) => v != null && v !== '')))}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            <Icons.ArrowDownTray /> CSV
                        </a>
                        <a
                            href={`${route('general-ledger.report.export.pdf')}?${new URLSearchParams(Object.fromEntries(Object.entries({ date_from, date_to, reference_type, account_code }).filter(([, v]) => v != null && v !== '')))}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            <Icons.DocumentArrowDown /> PDF
                        </a>
                        <Link
                            href={route('general-ledger.index')}
                            className="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            View by entry
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="General Ledger Report" />

            {(flash?.success || flash?.error) && (
                <div
                    className={`mb-4 rounded-xl border px-4 py-3 text-sm font-medium ${flash.error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
                >
                    {flash.success || flash.error}
                </div>
            )}

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Transactions</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.ListBullet /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{transactions_count}</p>
                        <p className="text-xs text-blue-100 mt-1">Debit & credit lines</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total debits</span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 font-mono tabular-nums">RM {formatMoney(total_debits)}</p>
                        <p className="text-xs text-slate-500 mt-1">Filtered period</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total credits</span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 font-mono tabular-nums">RM {formatMoney(total_credits)}</p>
                        <p className="text-xs text-slate-500 mt-1">Filtered period</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex items-center">
                        <Link
                            href={route('general-ledger.index')}
                            className="text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-2"
                        >
                            <Icons.Document /> Switch to entry view
                        </Link>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-end gap-3 bg-slate-50/50">
                        <form method="get" action={route('general-ledger.report')} className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Date from</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    defaultValue={date_from}
                                    className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Date to</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    defaultValue={date_to}
                                    className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Reference type</label>
                                <select
                                    name="reference_type"
                                    defaultValue={reference_type}
                                    className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                                >
                                    {REFERENCE_OPTIONS.map((opt) => (
                                        <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Account</label>
                                <select
                                    name="account_code"
                                    defaultValue={account_code}
                                    className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 min-w-[180px]"
                                >
                                    <option value="">All accounts</option>
                                    {accountOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                            <button
                                type="submit"
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                            >
                                Apply filters
                            </button>
                        </form>
                        {(date_from || date_to || reference_type || account_code) && (
                            <Link href={route('general-ledger.report')} className="text-xs font-semibold text-blue-600 hover:text-blue-700">
                                Clear filters
                            </Link>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            Page {current_page} of {last_page} ({total} lines)
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Date</th>
                                    <th className="px-6 py-4">Entry #</th>
                                    <th className="px-6 py-4">Description</th>
                                    <th className="px-6 py-4">Account</th>
                                    <th className="px-6 py-4">Reference</th>
                                    <th className="px-6 py-4 text-right">Debit</th>
                                    <th className="px-6 py-4 text-right">Credit</th>
                                    <th className="px-6 py-4 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.length > 0 ? (
                                    transactions.map((tx) => (
                                        <tr key={tx.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors">
                                            <td className="px-6 py-4 font-mono text-slate-800 text-xs">{tx.date}</td>
                                            <td className="px-6 py-4 font-mono text-slate-600 text-xs">#{tx.entry_id}</td>
                                            <td className="px-6 py-4 text-slate-800 max-w-[200px] truncate" title={tx.description}>{tx.description}</td>
                                            <td className="px-6 py-4">
                                                <span className="font-mono font-semibold text-slate-800">{tx.account_code}</span>
                                                <span className="block text-xs text-slate-500">{accountsMap[tx.account_code] || '—'}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                    {tx.reference_type}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-slate-800">
                                                {tx.debit > 0 ? `RM ${formatMoney(tx.debit)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-slate-800">
                                                {tx.credit > 0 ? `RM ${formatMoney(tx.credit)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex flex-col items-end gap-1">
                                                    <Link
                                                        href={route('general-ledger.show', tx.entry_id)}
                                                        className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700"
                                                    >
                                                        View entry <Icons.ChevronRight />
                                                    </Link>
                                                    {tx.source_route && (
                                                        <a href={tx.source_route} className="text-xs font-semibold text-slate-500 hover:text-slate-700">
                                                            {getSourceLabel(tx.reference_type)}
                                                        </a>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center">
                                            <p className="text-slate-600 font-semibold mb-1">
                                                No transactions in this period.
                                            </p>
                                            <p className="text-slate-400 text-sm">
                                                {(date_from || date_to || reference_type || account_code)
                                                    ? 'Try a different date range or clear filters.'
                                                    : 'Post an invoice or record a payment to see ledger lines.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {last_page > 1 && (
                        <div className="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <span className="text-slate-500 text-sm">Page {current_page} of {last_page}</span>
                            <div className="flex gap-2">
                                {prev_url && (
                                    <Link href={prev_url} className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                                        Previous
                                    </Link>
                                )}
                                {next_url && (
                                    <Link href={next_url} className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
