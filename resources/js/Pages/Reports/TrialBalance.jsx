import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import ReportPeriodChips from '@/Components/ReportPeriodChips';

const Icons = {
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    Warning: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

const TYPE_ORDER = ['asset', 'liability', 'equity', 'income', 'expense'];

const TYPE_LABELS = {
    asset: 'Assets',
    liability: 'Liabilities',
    equity: 'Equity',
    income: 'Income',
    expense: 'Expenses',
};

const TYPE_OPTIONS = [
    { value: '', label: 'All types' },
    { value: 'asset', label: 'Assets' },
    { value: 'liability', label: 'Liabilities' },
    { value: 'equity', label: 'Equity' },
    { value: 'income', label: 'Income' },
    { value: 'expense', label: 'Expenses' },
];

function money(n) {
    return formatCurrency(n, 'MYR');
}

function moneyOrDash(n) {
    return Number(n) > 0 ? money(n) : '—';
}

function formatAsOf(iso) {
    if (! iso) return '';
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function groupByType(rows) {
    const buckets = Object.fromEntries(TYPE_ORDER.map((type) => [type, []]));
    rows.forEach((row) => {
        const type = TYPE_ORDER.includes(row.type) ? row.type : 'asset';
        buckets[type].push(row);
    });
    return TYPE_ORDER
        .map((type) => {
            const accounts = buckets[type];
            const debit = accounts.reduce((sum, row) => sum + Number(row.debit || 0), 0);
            const credit = accounts.reduce((sum, row) => sum + Number(row.credit || 0), 0);
            return { type, label: TYPE_LABELS[type], accounts, debit, credit };
        })
        .filter((group) => group.accounts.length > 0);
}

export default function TrialBalance({
    auth,
    trialBalance = [],
    totals = {},
    compare_label = null,
    compare_as_of_date = null,
    compare_totals = null,
    filters = {},
}) {
    const asOfDate = filters.as_of_date || '';
    const preset = filters.preset || 'custom';
    const compare = filters.compare || 'previous';
    const comparisonOn = compare !== 'none';
    const changeCompare = (value) => router.get(route('trial-balance.index'), {
        preset,
        as_of_date: asOfDate,
        compare: value,
    }, { preserveScroll: true, preserveState: false });
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');

    const isBalanced = Number(totals.difference || 0) < 0.01;
    const accountCount = trialBalance.length;

    const filteredRows = useMemo(() => {
        const q = search.trim().toLowerCase();
        return trialBalance.filter((row) => {
            const matchesType = typeFilter === '' || row.type === typeFilter;
            const matchesSearch = ! q
                || (row.code || '').toLowerCase().includes(q)
                || (row.name || '').toLowerCase().includes(q);
            return matchesType && matchesSearch;
        });
    }, [trialBalance, search, typeFilter]);

    const groups = useMemo(() => groupByType(filteredRows), [filteredRows]);
    const hasFilters = Boolean(search || typeFilter);

    const ledgerUrl = (code) => route('general-ledger.report', {
        account_code: code,
        date_to: asOfDate,
        from: 'tb',
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-terracotta">Reports</p>
                            <h1 className="font-display text-xl lg:text-2xl font-medium text-ink tracking-tight">Trial balance</h1>
                        </div>
                        <p className="text-ink-muted text-sm mt-1 truncate">
                            Account balances as of {formatAsOf(asOfDate) || 'today'}. Click a line to open the ledger.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Trial Balance" />

            <div className="space-y-4 sm:space-y-5 min-w-0">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4 space-y-3">
                    <ReportPeriodChips
                        action={route('trial-balance.index')}
                        preset={preset}
                        mode="as_of"
                        asOf={asOfDate}
                        extraParams={{ compare }}
                    />
                    <div className="flex flex-wrap gap-1.5">
                        {[
                            ['previous', 'vs prior month'],
                            ['last_year', 'vs last year'],
                            ['none', 'Off'],
                        ].map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => changeCompare(value)}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                                    compare === value
                                        ? 'bg-forest text-white border-forest'
                                        : 'bg-surface text-ink border-border-warm hover:bg-cream'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    {comparisonOn && compare_as_of_date && (
                        <p className="text-xs text-ink-muted">{compare_label}: {formatAsOf(compare_as_of_date)}</p>
                    )}
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-border-warm">
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Accounts</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-ink text-lg">{accountCount}</p>
                            <p className="text-xs text-ink-muted mt-1">With a balance</p>
                        </div>
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Debits</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-ink text-sm sm:text-base">{money(totals.debit)}</p>
                        </div>
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Credits</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-ink text-sm sm:text-base">{money(totals.credit)}</p>
                        </div>
                        <div className="px-4 py-4 sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Status</p>
                            <p className={`mt-1 inline-flex items-center gap-1.5 text-sm font-semibold ${isBalanced ? 'text-forest' : 'text-terracotta'}`}>
                                {isBalanced ? <Icons.Check /> : <Icons.Warning />}
                                {isBalanced ? 'In balance' : `Out by ${money(totals.difference)}`}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-[220px] max-w-full sm:max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by code or name..."
                                className="w-full pl-9 pr-4 py-2.5 border border-border-warm rounded-xl text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                            />
                        </div>

                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[160px]"
                        >
                            {TYPE_OPTIONS.map((opt) => (
                                <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>

                        {hasFilters && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setTypeFilter('');
                                }}
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                            >
                                Clear
                            </button>
                        )}

                        <span className="text-ink-muted text-sm font-medium ml-auto whitespace-nowrap">
                            {filteredRows.length} of {accountCount}
                        </span>
                    </div>

                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3 w-28">Code</th>
                                    <th className="px-4 sm:px-6 py-3">Account</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Debit</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Credit</th>
                                    {comparisonOn && (
                                        <>
                                            <th className="px-4 sm:px-6 py-3 text-right">Cmp Dr</th>
                                            <th className="px-4 sm:px-6 py-3 text-right">Cmp Cr</th>
                                        </>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {groups.length > 0 ? groups.map((group) => (
                                    <React.Fragment key={group.type}>
                                        <tr className="bg-cream/70">
                                            <td colSpan={4} className="px-4 sm:px-6 py-2.5 text-[10px] font-semibold uppercase tracking-widest text-ink-muted">
                                                {group.label}
                                            </td>
                                        </tr>
                                        {group.accounts.map((item) => (
                                            <tr key={item.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                                <td className="px-4 sm:px-6 py-3 font-mono text-ink font-semibold whitespace-nowrap">
                                                    <Link href={ledgerUrl(item.code)} className="hover:text-terracotta">
                                                        {item.code}
                                                    </Link>
                                                </td>
                                                <td className="px-4 sm:px-6 py-3">
                                                    <Link href={ledgerUrl(item.code)} className="font-medium text-ink hover:text-terracotta">
                                                        {item.name}
                                                    </Link>
                                                </td>
                                                <td className="px-4 sm:px-6 py-3 text-right font-mono tabular-nums text-ink whitespace-nowrap">
                                                    {moneyOrDash(item.debit)}
                                                </td>
                                                <td className="px-4 sm:px-6 py-3 text-right font-mono tabular-nums text-ink whitespace-nowrap">
                                                    {moneyOrDash(item.credit)}
                                                </td>
                                                {comparisonOn && (
                                                    <>
                                                        <td className="px-4 sm:px-6 py-3 text-right font-mono tabular-nums text-ink-muted whitespace-nowrap">
                                                            {moneyOrDash(item.compare_debit)}
                                                        </td>
                                                        <td className="px-4 sm:px-6 py-3 text-right font-mono tabular-nums text-ink-muted whitespace-nowrap">
                                                            {moneyOrDash(item.compare_credit)}
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        ))}
                                        <tr className="border-b border-border-warm bg-cream/30">
                                            <td colSpan={2} className="px-4 sm:px-6 py-2.5 text-right text-xs font-semibold text-ink-muted">
                                                Total {group.label.toLowerCase()}
                                            </td>
                                            <td className="px-4 sm:px-6 py-2.5 text-right font-mono tabular-nums text-sm font-semibold text-ink whitespace-nowrap">
                                                {money(group.debit)}
                                            </td>
                                            <td className="px-4 sm:px-6 py-2.5 text-right font-mono tabular-nums text-sm font-semibold text-ink whitespace-nowrap">
                                                {money(group.credit)}
                                            </td>
                                            {comparisonOn && <td colSpan={2} />}
                                        </tr>
                                    </React.Fragment>
                                )) : (
                                    <tr>
                                        <td colSpan={comparisonOn ? 6 : 4} className="px-4 py-16 text-center">
                                            <p className="text-ink font-semibold mb-1">
                                                {hasFilters ? 'No accounts match your filters.' : 'No balances as of this date.'}
                                            </p>
                                            <p className="text-ink-muted text-sm">
                                                {hasFilters
                                                    ? 'Try a different search or clear filters.'
                                                    : 'Post invoices, bills, or a journal to populate this report.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            {accountCount > 0 && (
                                <tfoot>
                                    <tr className="border-t-2 border-border-warm bg-cream/80">
                                        <td colSpan={2} className="px-4 sm:px-6 py-4 text-right text-xs font-semibold uppercase tracking-widest text-ink">
                                            Grand totals
                                        </td>
                                        <td className="px-4 sm:px-6 py-4 text-right font-mono tabular-nums font-bold text-ink whitespace-nowrap">
                                            {money(totals.debit)}
                                        </td>
                                        <td className="px-4 sm:px-6 py-4 text-right font-mono tabular-nums font-bold text-ink whitespace-nowrap">
                                            {money(totals.credit)}
                                        </td>
                                        {comparisonOn && compare_totals && (
                                            <>
                                                <td className="px-4 sm:px-6 py-4 text-right font-mono tabular-nums font-bold text-ink-muted whitespace-nowrap">
                                                    {money(compare_totals.debit)}
                                                </td>
                                                <td className="px-4 sm:px-6 py-4 text-right font-mono tabular-nums font-bold text-ink-muted whitespace-nowrap">
                                                    {money(compare_totals.credit)}
                                                </td>
                                            </>
                                        )}
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {groups.length > 0 ? groups.map((group) => (
                            <div key={group.type}>
                                <div className="px-4 py-2.5 bg-cream/70 text-[10px] font-semibold uppercase tracking-widest text-ink-muted">
                                    {group.label}
                                </div>
                                {group.accounts.map((item) => (
                                    <Link key={item.id} href={ledgerUrl(item.code)} className="block p-4 hover:bg-cream/50">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-mono text-xs font-semibold text-ink">{item.code}</p>
                                                <p className="mt-0.5 font-medium text-ink">{item.name}</p>
                                            </div>
                                            <div className="text-right shrink-0 font-mono tabular-nums text-sm">
                                                {item.debit > 0 && <p className="font-semibold text-ink">{money(item.debit)} Dr</p>}
                                                {item.credit > 0 && <p className="font-semibold text-ink">{money(item.credit)} Cr</p>}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                                <div className="px-4 py-2.5 bg-cream/30 flex justify-between text-xs font-semibold text-ink-muted">
                                    <span>Total {group.label.toLowerCase()}</span>
                                    <span className="font-mono tabular-nums text-ink">
                                        {money(group.debit)} · {money(group.credit)}
                                    </span>
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center">
                                <p className="text-ink font-semibold mb-1">
                                    {hasFilters ? 'No accounts match your filters.' : 'No balances as of this date.'}
                                </p>
                                <p className="text-ink-muted text-sm">
                                    {hasFilters
                                        ? 'Try a different search or clear filters.'
                                        : 'Post invoices, bills, or a journal to populate this report.'}
                                </p>
                            </div>
                        )}
                        {accountCount > 0 && (
                            <div className="px-4 py-4 bg-cream/80 flex justify-between gap-3">
                                <span className="text-xs font-semibold uppercase tracking-widest text-ink">Grand totals</span>
                                <span className="font-mono tabular-nums font-bold text-sm text-ink">
                                    {money(totals.debit)} · {money(totals.credit)}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                <p className="text-xs text-ink-muted">
                    Each line is the net balance on its normal side. Cleared accounts (zero balance) are hidden. Grand totals always use the full report, even if you filter the list.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
