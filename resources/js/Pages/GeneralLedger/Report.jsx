import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import IndexPagination from '@/Components/IndexPagination';


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
    { value: 'Manual', label: 'Manual journal' },
];

function queryParams(filters) {
    const params = {};
    if (filters.dateFrom) params.date_from = filters.dateFrom;
    if (filters.dateTo) params.date_to = filters.dateTo;
    if (filters.referenceType) params.reference_type = filters.referenceType;
    if (filters.accountCode) params.account_code = filters.accountCode;
    if (filters.from) params.from = filters.from;
    if (filters.perPage) params.per_page = filters.perPage;
    if (filters.page) params.page = filters.page;
    return params;
}

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Report({
    auth,
    transactions = [],
    accountsMap = {},
    filters = {},
    stats = {},
    accountOptions = [],
    paginator = {},
    ledgerMode = false,
    accountLedger = null,
    openingBalance = null,
    closingBalance = null,
    periodMovement = null,
}) {
    const dateFromFilter = filters.date_from || '';
    const dateToFilter = filters.date_to || '';
    const typeFilter = filters.reference_type || '';
    const accountFilter = filters.account_code || '';
    const from = filters.from || '';
    const perPageFilter = Number(filters.per_page || paginator.per_page || 25);

    const [dateFrom, setDateFrom] = useState(dateFromFilter);
    const [dateTo, setDateTo] = useState(dateToFilter);
    const [referenceType, setReferenceType] = useState(typeFilter);
    const [accountCode, setAccountCode] = useState(accountFilter);
    const [perPage, setPerPage] = useState(perPageFilter);

    const { transactions_count: transactionsCount = 0, total_debits: totalDebits = 0, total_credits: totalCredits = 0 } = stats;

    const visit = ({
        dateFrom: nextDateFrom,
        dateTo: nextDateTo,
        referenceType: nextType,
        accountCode: nextAccount,
        perPage: nextPerPage,
        page = 1,
    }) => {
        router.get(
            route('general-ledger.report'),
            queryParams({
                dateFrom: nextDateFrom,
                dateTo: nextDateTo,
                referenceType: nextType,
                accountCode: nextAccount,
                from,
                perPage: nextPerPage,
                page,
            }),
            { preserveState: false, preserveScroll: true }
        );
    };

    const appliedFilters = {
        dateFrom: dateFromFilter,
        dateTo: dateToFilter,
        referenceType: typeFilter,
        accountCode: accountFilter,
        perPage: perPageFilter,
    };

    const hasFilters = Boolean(dateFromFilter || dateToFilter || typeFilter || accountFilter);
    const exportParams = new URLSearchParams(queryParams({ ...appliedFilters, page: undefined })).toString();

    const backHref = from === 'coa'
        ? route('chart-of-accounts.index')
        : from === 'tb'
            ? route('trial-balance.index', { as_of_date: dateToFilter || undefined })
            : from === 'pl'
                ? route('profit-and-loss.index', {
                    date_from: dateFromFilter || undefined,
                    date_to: dateToFilter || undefined,
                })
                : from === 'bs'
                    ? route('balance-sheet.index', { as_at_date: dateToFilter || undefined })
                    : route('general-ledger.index');

    const backLabel = from === 'coa'
        ? 'Back to Chart of Accounts'
        : from === 'tb'
            ? 'Back to Trial Balance'
            : from === 'pl'
                ? 'Back to Profit & Loss'
                : from === 'bs'
                    ? 'Back to Balance Sheet'
                    : 'Back to General Ledger';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        {ledgerMode && accountLedger ? (
                            <>
                                <Link href={backHref} className="text-xs font-semibold text-terracotta hover:text-terracotta mb-2 inline-block">
                                    ← {backLabel}
                                </Link>
                                <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">
                                    {accountLedger.code} — {accountLedger.name}
                                </h2>
                                <p className="text-ink-muted text-sm font-medium mt-1">Every posting to this account</p>
                            </>
                        ) : (
                            <>
                                <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">General Ledger Report</h2>
                                <p className="text-ink-muted text-sm font-medium mt-1">
                                    One row per debit or credit line — use filters to narrow by date, type, or account.
                                </p>
                            </>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`${route('general-ledger.report.export.csv')}?${exportParams}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                        >
                            <Icons.ArrowDownTray /> CSV
                        </a>
                        <a
                            href={auth.planPermissions['reports.export.full'] ? `${route('general-ledger.report.export.pdf')}?${exportParams}` : '#'}
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
            <Head title={ledgerMode && accountLedger ? `${accountLedger.code} — ${accountLedger.name}` : 'General Ledger Report'} />


            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Transactions</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.ListBullet /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{transactionsCount}</p>
                        <p className="text-xs text-terracotta mt-1">Debit & credit lines</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Total debits</span>
                            <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.TrendingUp /></span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink font-mono tabular-nums">{formatCurrency(totalDebits, 'MYR')}</p>
                        <p className="text-xs text-ink-muted mt-1">Filtered period</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Total credits</span>
                            <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.TrendingDown /></span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink font-mono tabular-nums">{formatCurrency(totalCredits, 'MYR')}</p>
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
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            visit({ dateFrom, dateTo, referenceType, accountCode, perPage, page: 1 });
                        }}
                        className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50"
                    >
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="border border-border-warm rounded-xl py-2.5 px-3 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                            aria-label="Date from"
                        />
                        <span className="text-ink-muted text-xs">to</span>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="border border-border-warm rounded-xl py-2.5 px-3 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                            aria-label="Date to"
                        />
                        <select
                            value={referenceType}
                            onChange={(e) => {
                                const nextType = e.target.value;
                                setReferenceType(nextType);
                                visit({ dateFrom, dateTo, referenceType: nextType, accountCode, perPage, page: 1 });
                            }}
                            className="border border-border-warm rounded-xl py-2.5 pl-3 pr-8 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[160px]"
                        >
                            {REFERENCE_OPTIONS.map((opt) => (
                                <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                        <select
                            value={accountCode}
                            onChange={(e) => {
                                const nextAccount = e.target.value;
                                setAccountCode(nextAccount);
                                visit({ dateFrom, dateTo, referenceType, accountCode: nextAccount, perPage, page: 1 });
                            }}
                            className="border border-border-warm rounded-xl py-2.5 pl-3 pr-8 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[180px]"
                        >
                            <option value="">All accounts</option>
                            {accountOptions.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                        <select
                            value={perPage}
                            onChange={(e) => {
                                const next = Number(e.target.value);
                                setPerPage(next);
                                visit({ ...appliedFilters, perPage: next, page: 1 });
                            }}
                            className="border border-border-warm rounded-xl py-2.5 pl-3 pr-8 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]"
                        >
                            <option value={10}>10 per page</option>
                            <option value={25}>25 per page</option>
                            <option value={50}>50 per page</option>
                            <option value={100}>100 per page</option>
                        </select>
                        <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark">
                            Apply
                        </button>
                        {hasFilters && (
                            <Link
                                href={route('general-ledger.report', from ? { from, per_page: perPage } : { per_page: perPage })}
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                            >
                                Clear
                            </Link>
                        )}
                        <span className="text-ink-muted text-sm font-medium ml-auto whitespace-nowrap">
                            {paginator.total > 0
                                ? `${paginator.from}–${paginator.to} of ${paginator.total}`
                                : '0 of 0'}
                        </span>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3">Description</th>
                                    {!ledgerMode && <th className="px-4 py-3">Account</th>}
                                    <th className="px-4 py-3">Source</th>
                                    <th className="px-4 py-3 text-right">Debit</th>
                                    <th className="px-4 py-3 text-right">Credit</th>
                                    {ledgerMode && <th className="px-4 py-3 text-right">Balance</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {ledgerMode && (
                                    <tr className="border-b border-border-warm bg-cream/40 text-sm">
                                        <td className="px-4 py-3 text-xs text-ink-muted" colSpan={5}>Opening balance</td>
                                        <td className="px-4 py-3 text-right font-mono tabular-nums font-semibold text-ink">
                                            RM {formatMoney(openingBalance ?? 0)}
                                        </td>
                                    </tr>
                                )}
                                {transactions.length > 0 ? (
                                    transactions.map((tx) => (
                                        <tr key={tx.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                            <td className="px-4 py-3 font-mono text-ink text-xs">{tx.date}</td>
                                            <td className="px-4 py-3 text-ink max-w-[220px]">
                                                <p className="truncate" title={tx.description}>{tx.description}</p>
                                                <Link href={route('general-ledger.show', tx.entry_id)} className="text-[10px] text-ink-muted hover:text-terracotta">
                                                    Entry #{tx.entry_id}
                                                </Link>
                                            </td>
                                            {!ledgerMode && (
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={route('general-ledger.report', queryParams({
                                                            ...appliedFilters,
                                                            accountCode: tx.account_code,
                                                            page: 1,
                                                        }))}
                                                        className="font-mono font-semibold text-terracotta hover:text-terracotta"
                                                    >
                                                        {tx.account_code}
                                                    </Link>
                                                    <span className="block text-xs text-ink-muted">{accountsMap[tx.account_code] || '—'}</span>
                                                </td>
                                            )}
                                            <td className="px-4 py-3">
                                                {tx.source_route ? (
                                                    <a href={tx.source_route} className="text-xs font-semibold text-terracotta hover:text-terracotta">
                                                        {tx.source_label || tx.reference_type}
                                                    </a>
                                                ) : (
                                                    <span className="text-xs text-ink-muted">{tx.reference_type || '—'}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono tabular-nums text-ink">
                                                {tx.debit > 0 ? `RM ${formatMoney(tx.debit)}` : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono tabular-nums text-ink">
                                                {tx.credit > 0 ? `RM ${formatMoney(tx.credit)}` : '—'}
                                            </td>
                                            {ledgerMode && (
                                                <td className="px-4 py-3 text-right font-mono tabular-nums font-semibold text-ink">
                                                    RM {formatMoney(tx.running_balance ?? 0)}
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-16 text-center">
                                            <p className="text-ink font-semibold mb-1">
                                                {ledgerMode ? 'Nothing posted to this account yet' : 'No transactions in this period.'}
                                            </p>
                                            <p className="text-ink-muted text-sm">
                                                {hasFilters
                                                    ? 'Try a different date range or clear filters.'
                                                    : 'Post an invoice or record a payment to see ledger lines.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                                {ledgerMode && transactions.length > 0 && (
                                    <tr className="border-t-2 border-border-warm bg-cream/60 text-sm font-semibold">
                                        <td className="px-4 py-3" colSpan={5}>Period movement</td>
                                        <td className="px-4 py-3 text-right font-mono tabular-nums" colSpan={1}>
                                            RM {formatMoney(periodMovement ?? 0)}
                                        </td>
                                    </tr>
                                )}
                                {ledgerMode && (
                                    <tr className="border-t border-border-warm bg-cream/80 text-sm font-semibold">
                                        <td className="px-4 py-3" colSpan={5}>Closing balance</td>
                                        <td className="px-4 py-3 text-right font-mono tabular-nums" colSpan={1}>
                                            RM {formatMoney(closingBalance ?? openingBalance ?? 0)}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <IndexPagination
                        currentPage={paginator.current_page || 1}
                        lastPage={paginator.last_page || 1}
                        onPage={(page) => visit({ ...appliedFilters, page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
