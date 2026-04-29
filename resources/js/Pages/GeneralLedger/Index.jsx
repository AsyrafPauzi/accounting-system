import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';


const Icons = {
    BookOpen: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>,
    Document: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    Scale: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    TrendingUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>,
    TrendingDown: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" /></svg>,
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

export default function Index({ auth, entries = [], filters = {}, stats = {}, paginator = {} }) {
    const { flash } = usePage().props;
    const { date_from = '', date_to = '', reference_type = '' } = filters;
    const { entries_count = 0, total_debits = 0, total_credits = 0, balanced_count = 0 } = stats;
    const { current_page = 1, last_page = 1, prev_url, next_url, total } = paginator;

    const getSourceLabel = (referenceType) => {
        if (referenceType === 'Invoice' || referenceType === 'Invoice Payment') return 'View Invoice';
        if (referenceType === 'Credit Note') return 'View Credit Note';
        if (referenceType === 'Bill' || referenceType === 'Bill Payment') return 'View Bill';
        return null;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">General Ledger</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Double-entry journal created automatically from invoices, payments, and credit notes.
                        </p>
                        <p className="text-slate-400 text-xs mt-0.5">
                            One row per journal entry (invoice, payment, or credit note).
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`${route('general-ledger.export.csv')}?${new URLSearchParams(Object.fromEntries(Object.entries({ date_from, date_to, reference_type }).filter(([, v]) => v != null && v !== '')))}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            <Icons.ArrowDownTray /> CSV
                        </a>
                        <a
                            href={auth.planPermissions['reports.export.full'] ? `${route('general-ledger.export.pdf')}?${new URLSearchParams(Object.fromEntries(Object.entries({ date_from, date_to, reference_type }).filter(([, v]) => v != null && v !== '')))}` : '#'}
                            onClick={(e) => {
                                if (!auth.planPermissions['reports.export.full']) {
                                    e.preventDefault();
                                    alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                }
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors ${
                                auth.planPermissions['reports.export.full']
                                    ? 'text-slate-600 bg-white border border-slate-200 hover:bg-slate-50'
                                    : 'text-slate-400 bg-slate-50 border border-slate-100 cursor-pointer hover:bg-slate-100'
                            }`}
                        >
                            <Icons.DocumentArrowDown /> PDF
                            {!auth.planPermissions['reports.export.full'] && (
                                <svg className="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                            )}
                        </a>
                        <Link
                            href={route('general-ledger.report')}
                            className="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            View transaction report
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="General Ledger" />


            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg transition-all hover:shadow-xl hover:-translate-y-0.5">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Journal entries</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.BookOpen /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{entries_count}</p>
                        <p className="text-xs text-blue-100 mt-1">Filtered period</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total debits</span>
                            <span className="p-2 rounded-xl bg-blue-50 text-blue-600"><Icons.TrendingUp /></span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 font-mono tabular-nums">RM {formatMoney(total_debits)}</p>
                        <p className="text-xs text-slate-500 mt-1">Filtered period</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total credits</span>
                            <span className="p-2 rounded-xl bg-indigo-50 text-indigo-600"><Icons.TrendingDown /></span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 font-mono tabular-nums">RM {formatMoney(total_credits)}</p>
                        <p className="text-xs text-slate-500 mt-1">Filtered period</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Balanced</span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600"><Icons.Scale /></span>
                        </div>
                        <p className="text-xl font-bold text-emerald-700 font-mono tabular-nums">{balanced_count}</p>
                        <p className="text-xs text-slate-500 mt-1">Entries with debit = credit</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-end gap-3 bg-slate-50/50">
                        <form method="get" action={route('general-ledger.index')} className="flex flex-wrap items-end gap-3">
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
                            <button
                                type="submit"
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                            >
                                Apply filters
                            </button>
                        </form>
                        {(date_from || date_to || reference_type) && (
                            <Link
                                href={route('general-ledger.index')}
                                className="text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                Clear filters
                            </Link>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            Page {current_page} of {last_page} ({total} entries)
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Date</th>
                                    <th className="px-6 py-4">Description</th>
                                    <th className="px-6 py-4">Reference</th>
                                    <th className="px-6 py-4">Source</th>
                                    <th className="px-6 py-4 text-right">Debit</th>
                                    <th className="px-6 py-4 text-right">Credit</th>
                                    <th className="px-6 py-4 text-right">Balanced</th>
                                    <th className="px-6 py-4 text-right w-24">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length > 0 ? (
                                    entries.map((entry) => (
                                        <tr key={entry.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors">
                                            <td className="px-6 py-4 font-mono text-slate-800 text-xs">{entry.date}</td>
                                            <td className="px-6 py-4 text-slate-800 max-w-xs truncate" title={entry.description}>{entry.description}</td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                    {entry.reference_type}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                {entry.source_route ? (
                                                    <a
                                                        href={entry.source_route}
                                                        className="text-blue-600 hover:text-blue-700 text-xs font-semibold"
                                                    >
                                                        {getSourceLabel(entry.reference_type)}
                                                    </a>
                                                ) : (
                                                    <span className="text-slate-400 text-xs">—</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-slate-800 tabular-nums">RM {formatMoney(entry.total_debit)}</td>
                                            <td className="px-6 py-4 text-right font-mono text-slate-800 tabular-nums">RM {formatMoney(entry.total_credit)}</td>
                                            <td className="px-6 py-4 text-right">
                                                {entry.balanced ? (
                                                    <span className="inline-flex items-center gap-1 text-emerald-600 text-xs font-semibold justify-end w-full"><Icons.Check /> Yes</span>
                                                ) : (
                                                    <span className="text-slate-400 text-xs">—</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={route('general-ledger.show', entry.id)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors"
                                                >
                                                    View <Icons.ChevronRight />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center">
                                            <p className="text-slate-600 font-semibold mb-1">
                                                No entries in this period.
                                            </p>
                                            <p className="text-slate-400 text-sm">
                                                {(date_from || date_to || reference_type)
                                                    ? 'Try a different date range or clear filters.'
                                                    : 'Post an invoice or record a payment to create ledger entries.'}
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
