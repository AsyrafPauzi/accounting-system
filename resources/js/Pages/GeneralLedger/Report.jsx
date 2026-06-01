import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';


const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    ListBullet: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    TrendingUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>,
    TrendingDown: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" /></svg>,
    ArrowTopRightOnSquare: () => <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>,
    Eye: () => <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>,
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
        if (refType === 'Invoice' || refType === 'Invoice Payment') return 'Invoice';
        if (refType === 'Credit Note') return 'Credit Note';
        if (refType === 'Bill' || refType === 'Bill Payment') return 'Bill';
        return refType;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">General Ledger Report</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            One row per debit or credit line — use filters to narrow by date, type, or account.
                        </p>
                        <p className="text-ink-muted text-xs mt-0.5">
                            Every line from posted invoices, payments, and credit notes.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`${route('general-ledger.report.export.csv')}?${new URLSearchParams(Object.fromEntries(Object.entries({ date_from, date_to, reference_type, account_code }).filter(([, v]) => v != null && v !== '')))}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                        >
                            <Icons.ArrowDownTray /> CSV
                        </a>
                        <a
                            href={auth.planPermissions['reports.export.full'] ? `${route('general-ledger.report.export.pdf')}?${new URLSearchParams(Object.fromEntries(Object.entries({ date_from, date_to, reference_type, account_code }).filter(([, v]) => v != null && v !== '')))}` : '#'}
                            onClick={(e) => {
                                if (!auth.planPermissions['reports.export.full']) {
                                    e.preventDefault();
                                    alertUpgrade('Professional PDF reports are available on the Corporate plan.');
                                }
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors ${
                                auth.planPermissions['reports.export.full']
                                    ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                    : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                            }`}
                        >
                            <Icons.DocumentArrowDown /> PDF
                            {!auth.planPermissions['reports.export.full'] && (
                                <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                            )}
                        </a>
                        <Link
                            href={route('general-ledger.index')}
                            className="px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                        >
                            View by entry
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="General Ledger Report" />


            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Transactions</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.ListBullet /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{transactions_count}</p>
                        <p className="text-xs text-terracotta mt-1">Debit & credit lines</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Total debits</span>
                            <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.TrendingUp /></span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink font-mono tabular-nums">RM {formatMoney(total_debits)}</p>
                        <p className="text-xs text-ink-muted mt-1">Filtered period</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Total credits</span>
                            <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.TrendingDown /></span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink font-mono tabular-nums">RM {formatMoney(total_credits)}</p>
                        <p className="text-xs text-ink-muted mt-1">Filtered period</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm transition-all hover:shadow-md flex items-center">
                        <Link
                            href={route('general-ledger.index')}
                            className="text-sm font-semibold text-terracotta hover:text-terracotta flex items-center gap-2"
                        >
                            <Icons.Document /> Switch to entry view
                        </Link>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-end gap-3 bg-cream/50">
                        <form method="get" action={route('general-ledger.report')} className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">Date from</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    defaultValue={date_from}
                                    className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">Date to</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    defaultValue={date_to}
                                    className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">Reference type</label>
                                <select
                                    name="reference_type"
                                    defaultValue={reference_type}
                                    className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                                >
                                    {REFERENCE_OPTIONS.map((opt) => (
                                        <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">Account</label>
                                <select
                                    name="account_code"
                                    defaultValue={account_code}
                                    className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[180px]"
                                >
                                    <option value="">All accounts</option>
                                    {accountOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>
                            <button
                                type="submit"
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta transition-colors"
                            >
                                Apply filters
                            </button>
                        </form>
                        {(date_from || date_to || reference_type || account_code) && (
                            <Link href={route('general-ledger.report')} className="text-xs font-semibold text-terracotta hover:text-terracotta">
                                Clear filters
                            </Link>
                        )}
                        <span className="text-ink-muted text-sm font-medium ml-auto">
                            Page {current_page} of {last_page} ({total} lines)
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
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
                                        <tr key={tx.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                            <td className="px-6 py-4 font-mono text-ink text-xs">{tx.date}</td>
                                            <td className="px-6 py-4 font-mono text-ink text-xs">#{tx.entry_id}</td>
                                            <td className="px-6 py-4 text-ink max-w-[200px] truncate" title={tx.description}>{tx.description}</td>
                                            <td className="px-6 py-4">
                                                <span className="font-mono font-semibold text-ink">{tx.account_code}</span>
                                                <span className="block text-xs text-ink-muted">{accountsMap[tx.account_code] || '—'}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-alt text-ink">
                                                    {tx.reference_type}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                                {tx.debit > 0 ? `RM ${formatMoney(tx.debit)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                                {tx.credit > 0 ? `RM ${formatMoney(tx.credit)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex flex-col items-end gap-1.5">
                                                    <Link
                                                        href={route('general-ledger.show', tx.entry_id)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-bold text-terracotta bg-surface-alt hover:bg-surface-alt transition-colors whitespace-nowrap uppercase tracking-wider shadow-sm"
                                                    >
                                                        <Icons.Eye /> View entry
                                                    </Link>
                                                    {tx.source_route && (
                                                        <a 
                                                            href={tx.source_route} 
                                                            className="inline-flex items-center gap-1 text-[9px] font-display font-medium text-ink-muted hover:text-ink transition-colors uppercase tracking-tight"
                                                        >
                                                            {getSourceLabel(tx.reference_type)} <Icons.ArrowTopRightOnSquare />
                                                        </a>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center">
                                            <p className="text-ink font-semibold mb-1">
                                                No transactions in this period.
                                            </p>
                                            <p className="text-ink-muted text-sm">
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
                        <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between bg-cream/50">
                            <span className="text-ink-muted text-sm">Page {current_page} of {last_page}</span>
                            <div className="flex gap-2">
                                {prev_url && (
                                    <Link href={prev_url} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                                        Previous
                                    </Link>
                                )}
                                {next_url && (
                                    <Link href={next_url} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
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
