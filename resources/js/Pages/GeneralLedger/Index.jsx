import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import IndexPagination from '@/Components/IndexPagination';

const Icons = {
    BookOpen: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Check: () => <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    Calendar: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>,
};

const TYPE_OPTIONS = [
    { value: '', label: 'All types' },
    { value: 'Invoice', label: 'Invoice' },
    { value: 'Invoice Payment', label: 'Invoice Payment' },
    { value: 'Credit Note', label: 'Credit Note' },
    { value: 'Bill', label: 'Bill' },
    { value: 'Bill Payment', label: 'Bill Payment' },
    { value: 'Manual', label: 'Manual journal' },
];

function exportQuery(params) {
    return new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([, v]) => v != null && v !== ''))
    ).toString();
}

function queryParams({ dateFrom, dateTo, referenceType, perPage, page }) {
    const params = { per_page: perPage, page };
    if (dateFrom) params.date_from = dateFrom;
    if (dateTo) params.date_to = dateTo;
    if (referenceType) params.reference_type = referenceType;
    return params;
}

export default function Index({ auth, entries = [], filters = {}, stats = {}, paginator = {} }) {
    const dateFromFilter = filters.date_from || '';
    const dateToFilter = filters.date_to || '';
    const typeFilter = filters.reference_type || '';
    const perPageFilter = Number(filters.per_page || paginator.per_page || 25);

    const [dateFrom, setDateFrom] = useState(dateFromFilter);
    const [dateTo, setDateTo] = useState(dateToFilter);
    const [referenceType, setReferenceType] = useState(typeFilter);
    const [perPage, setPerPage] = useState(perPageFilter);

    const {
        entries_count: entriesCount = 0,
        total_debits: totalDebits = 0,
        total_credits: totalCredits = 0,
        balanced_count: balancedCount = 0,
    } = stats;

    const visit = ({
        dateFrom: nextDateFrom,
        dateTo: nextDateTo,
        referenceType: nextType,
        perPage: nextPerPage,
        page = 1,
    }) => {
        router.get(
            route('general-ledger.index'),
            queryParams({
                dateFrom: nextDateFrom,
                dateTo: nextDateTo,
                referenceType: nextType,
                perPage: nextPerPage,
                page,
            }),
            { preserveState: false, preserveScroll: true }
        );
    };

    const applyFilters = (page = 1) => {
        visit({ dateFrom, dateTo, referenceType, perPage, page });
    };

    const hasFilters = Boolean(dateFromFilter || dateToFilter || typeFilter);
    const exportParams = exportQuery({
        date_from: dateFromFilter,
        date_to: dateToFilter,
        reference_type: typeFilter,
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-terracotta">Accounting</p>
                            <h1 className="font-display text-xl lg:text-2xl font-medium text-ink tracking-tight">General ledger</h1>
                        </div>
                        <p className="text-ink-muted text-sm mt-1 truncate">
                            Journal entries posted from invoices, bills, payments, and adjustments.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                        <Link
                            href={route('general-ledger.report')}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors text-sm"
                        >
                            <Icons.BookOpen /> Transaction report
                        </Link>
                        <a
                            href={`${route('general-ledger.export.csv')}?${exportParams}`}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors text-sm"
                        >
                            <Icons.ArrowDownTray /> CSV
                        </a>
                        <a
                            href={auth.planPermissions['reports.export.full'] ? `${route('general-ledger.export.pdf')}?${exportParams}` : '#'}
                            onClick={(e) => {
                                if (! auth.planPermissions['reports.export.full']) {
                                    e.preventDefault();
                                    alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                }
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold transition-colors text-sm ${
                                auth.planPermissions['reports.export.full']
                                    ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                    : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                            }`}
                        >
                            <Icons.DocumentArrowDown /> PDF
                            {! auth.planPermissions['reports.export.full'] && (
                                <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                            )}
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="General Ledger" />

            <div className="space-y-4 sm:space-y-5 min-w-0">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-border-warm">
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Entries</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-ink text-lg">{entriesCount}</p>
                        </div>
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Total debits</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-ink text-sm sm:text-base">
                                {formatCurrency(totalDebits, 'MYR')}
                            </p>
                        </div>
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Total credits</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-ink text-sm sm:text-base">
                                {formatCurrency(totalCredits, 'MYR')}
                            </p>
                        </div>
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Balanced</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-forest text-lg">{balancedCount}</p>
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <form
                        onSubmit={(e) => { e.preventDefault(); applyFilters(1); }}
                        className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50"
                    >
                        <div className="flex items-center gap-2 rounded-xl border border-border-warm px-3 py-2 bg-surface">
                            <Icons.Calendar />
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="border-0 bg-transparent text-sm text-ink focus:ring-0 p-0 w-[7.5rem]"
                                aria-label="Date from"
                            />
                            <span className="text-ink-muted text-xs">to</span>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="border-0 bg-transparent text-sm text-ink focus:ring-0 p-0 w-[7.5rem]"
                                aria-label="Date to"
                            />
                        </div>

                        <select
                            value={referenceType}
                            onChange={(e) => {
                                const nextType = e.target.value;
                                setReferenceType(nextType);
                                visit({ dateFrom, dateTo, referenceType: nextType, perPage, page: 1 });
                            }}
                            className="border border-border-warm rounded-xl py-2.5 pl-3 pr-8 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[160px]"
                        >
                            {TYPE_OPTIONS.map((opt) => (
                                <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>

                        <select
                            value={perPage}
                            onChange={(e) => {
                                const next = Number(e.target.value);
                                setPerPage(next);
                                visit({
                                    dateFrom: dateFromFilter,
                                    dateTo: dateToFilter,
                                    referenceType: typeFilter,
                                    perPage: next,
                                    page: 1,
                                });
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
                                href={route('general-ledger.index', { per_page: perPage })}
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
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Source</th>
                                    <th className="px-4 py-3 text-right">Debit</th>
                                    <th className="px-4 py-3 text-right">Credit</th>
                                    <th className="px-4 py-3 text-right">Balanced</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length > 0 ? (
                                    entries.map((entry) => (
                                        <tr key={entry.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                            <td className="px-4 py-3 font-mono text-ink text-xs tabular-nums whitespace-nowrap">{entry.date}</td>
                                            <td className="px-4 py-3 text-ink max-w-xs">
                                                <Link href={route('general-ledger.show', entry.id)} className="font-medium hover:text-terracotta truncate block" title={entry.description}>
                                                    {entry.description}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-alt text-ink">
                                                    {entry.reference_type || 'Journal'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                {entry.source_route ? (
                                                    <a href={entry.source_route} className="text-terracotta hover:text-terracotta text-xs font-semibold">
                                                        {entry.source_label || entry.reference_type}
                                                    </a>
                                                ) : (
                                                    <span className="text-ink-muted text-xs">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-ink tabular-nums whitespace-nowrap">
                                                {formatCurrency(entry.total_debit, 'MYR')}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-ink tabular-nums whitespace-nowrap">
                                                {formatCurrency(entry.total_credit, 'MYR')}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {entry.balanced ? (
                                                    <span className="inline-flex items-center gap-1 text-forest text-xs font-semibold justify-end w-full">
                                                        <Icons.Check /> Yes
                                                    </span>
                                                ) : (
                                                    <span className="text-ink-muted text-xs">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-16 text-center">
                                            <p className="text-ink font-semibold mb-1">No entries in this period.</p>
                                            <p className="text-ink-muted text-sm">
                                                {hasFilters
                                                    ? 'Try a different date range or clear filters.'
                                                    : 'Post an invoice or record a payment to create ledger entries.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <IndexPagination
                        currentPage={paginator.current_page || 1}
                        lastPage={paginator.last_page || 1}
                        onPage={(page) => visit({
                            dateFrom: dateFromFilter,
                            dateTo: dateToFilter,
                            referenceType: typeFilter,
                            perPage: perPageFilter,
                            page,
                        })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
