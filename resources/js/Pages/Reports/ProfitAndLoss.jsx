import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';
import ReportPeriodChips from '@/Components/ReportPeriodChips';


const Icons = {
    ChartPie: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /></svg>,
    ArrowUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>,
    ArrowDown: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" /></svg>,
    Scale: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function varianceClass(value) {
    if (Number(value) > 0) return 'text-forest';
    if (Number(value) < 0) return 'text-terracotta';
    return 'text-ink-muted';
}

export default function ProfitAndLoss({
    auth,
    revenue_accounts = [],
    expense_accounts = [],
    total_revenue = 0,
    total_expenses = 0,
    net_profit = 0,
    compare_revenue = null,
    compare_expenses = null,
    compare_net_profit = null,
    revenue_variance = null,
    expenses_variance = null,
    net_profit_variance = null,
    compare_label = null,
    filters = {},
}) {
    const { flash } = usePage().props;
    const { preset = 'custom', date_from = '', date_to = '', compare = 'previous', basis = 'accrual' } = filters;
    const comparisonOn = compare !== 'none';
    const isProfit = net_profit >= 0;
    const changeCompare = (value) => router.get(route('profit-and-loss.index'), {
        preset,
        date_from,
        date_to,
        compare: value,
        basis,
    }, { preserveScroll: true, preserveState: false });
    const changeBasis = (value) => router.get(route('profit-and-loss.index'), {
        preset,
        date_from,
        date_to,
        compare,
        basis: value,
    }, { preserveScroll: true, preserveState: false });
    const ledgerUrl = (code) => route('general-ledger.report', {
        account_code: code,
        date_from: filters.date_from,
        date_to: filters.date_to,
        from: 'pl',
    });
    const sourcesUrl = (code) => route('profit-and-loss.sources', {
        account_code: code,
        preset: filters.preset,
        date_from: filters.date_from,
        date_to: filters.date_to,
        basis,
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Profit &amp; Loss</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            {basis === 'cash'
                                ? 'Cash basis — income when collected, expenses when paid.'
                                : 'Accrual basis — income and expenses from your general ledger.'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Profit & Loss" />


            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-end gap-3 bg-cream/50">
                        <ReportPeriodChips
                            action={route('profit-and-loss.index')}
                            preset={preset}
                            dateFrom={date_from}
                            dateTo={date_to}
                            extraParams={{ compare, basis }}
                        />
                        <div className="flex flex-wrap gap-1.5">
                            {[
                                ['accrual', 'Accrual'],
                                ['cash', 'Cash'],
                            ].map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => changeBasis(value)}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                                        basis === value
                                            ? 'bg-terracotta text-white border-terracotta'
                                            : 'bg-surface text-ink border-border-warm hover:bg-cream'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {[
                                ['previous', 'vs previous'],
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
                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href={`${route('profit-and-loss.export.csv')}?${new URLSearchParams({ preset, date_from: date_from || '', date_to: date_to || '', compare, basis })}`}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                            >
                                <Icons.ArrowDownTray /> Download CSV
                            </a>
                            <a
                                href={auth.planPermissions?.['reports.export.full'] ? `${route('profit-and-loss.export.pdf')}?${new URLSearchParams({ preset, date_from: date_from || '', date_to: date_to || '', compare, basis })}` : '#'}
                                onClick={(e) => {
                                    if (!auth.planPermissions?.['reports.export.full']) {
                                        e.preventDefault();
                                        alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                    }
                                }}
                                className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors ${
                                    auth.planPermissions?.['reports.export.full']
                                        ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                        : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                                }`}
                            >
                                <Icons.DocumentArrowDown /> Download PDF
                                {!auth.planPermissions?.['reports.export.full'] && (
                                    <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                                )}
                            </a>
                        </div>
                        {comparisonOn && compare_label && (
                            <p className="w-full text-xs text-ink-muted">Comparing with {compare_label.toLowerCase()}.</p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-forest text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total revenue</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.ArrowUp /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_revenue)}</p>
                        <p className="text-xs text-forest mt-1">Income accounts</p>
                    </div>
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total expenses</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.ArrowDown /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_expenses)}</p>
                        <p className="text-xs text-terracotta mt-1">Expense accounts</p>
                    </div>
                    <div className={`rounded-2xl p-6 shadow-lg border-2 ${isProfit ? 'bg-forest/10 border-forest/30' : 'bg-terracotta/10 border-terracotta/30'}`}>
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-ink">Net {isProfit ? 'profit' : 'loss'}</span>
                            <span className={`p-2 rounded-xl ${isProfit ? 'bg-forest/10 text-forest' : 'bg-terracotta/10 text-terracotta'}`}><Icons.Scale /></span>
                        </div>
                        <p className={`text-2xl font-bold tabular-nums ${isProfit ? 'text-forest' : 'text-terracotta'}`}>
                            {isProfit ? '' : '-'}RM {formatMoney(Math.abs(net_profit))}
                        </p>
                        <p className="text-xs text-ink-muted mt-1">Revenue − Expenses</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Revenue (Income)</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                        <th className="px-6 py-4">Account</th>
                                        <th className="px-6 py-4 text-right">This period</th>
                                        {comparisonOn && <th className="px-6 py-4 text-right">Compare</th>}
                                        {comparisonOn && <th className="px-6 py-4 text-right">Variance</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {revenue_accounts.length > 0 ? (
                                        revenue_accounts.map((acc) => (
                                            <tr key={acc.code} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                                <td className="px-6 py-4">
                                                    <Link href={ledgerUrl(acc.code)} className="group inline-block">
                                                        <span className="font-mono text-ink text-xs group-hover:text-terracotta">{acc.code}</span>
                                                        <span className="block font-medium text-ink group-hover:text-terracotta">{acc.name}</span>
                                                    </Link>
                                                    {basis === 'accrual' && (
                                                        <Link href={sourcesUrl(acc.code)} className="text-[10px] text-terracotta hover:underline mt-1 inline-block">
                                                            Source documents
                                                        </Link>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-right font-mono tabular-nums text-forest font-semibold">
                                                    RM {formatMoney(acc.amount)}
                                                </td>
                                                {comparisonOn && (
                                                    <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                                        RM {formatMoney(acc.compare_amount)}
                                                    </td>
                                                )}
                                                {comparisonOn && (
                                                    <td className={`px-6 py-4 text-right font-mono tabular-nums font-semibold ${varianceClass(acc.variance)}`}>
                                                        RM {formatMoney(acc.variance)}
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={comparisonOn ? 4 : 2} className="px-6 py-8 text-center text-ink-muted text-sm">
                                                No revenue in this period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-border-warm bg-cream/80 font-semibold">
                                        <td className="px-6 py-4">Total revenue</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-forest">RM {formatMoney(total_revenue)}</td>
                                        {comparisonOn && <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(compare_revenue)}</td>}
                                        {comparisonOn && <td className={`px-6 py-4 text-right font-mono tabular-nums ${varianceClass(revenue_variance)}`}>RM {formatMoney(revenue_variance)}</td>}
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                            <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Expenses</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                        <th className="px-6 py-4">Account</th>
                                        <th className="px-6 py-4 text-right">This period</th>
                                        {comparisonOn && <th className="px-6 py-4 text-right">Compare</th>}
                                        {comparisonOn && <th className="px-6 py-4 text-right">Variance</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {expense_accounts.length > 0 ? (
                                        expense_accounts.map((acc) => (
                                            <tr key={acc.code} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                                <td className="px-6 py-4">
                                                    <Link href={ledgerUrl(acc.code)} className="group inline-block">
                                                        <span className="font-mono text-ink text-xs group-hover:text-terracotta">{acc.code}</span>
                                                        <span className="block font-medium text-ink group-hover:text-terracotta">{acc.name}</span>
                                                    </Link>
                                                    {basis === 'accrual' && (
                                                        <Link href={sourcesUrl(acc.code)} className="text-[10px] text-terracotta hover:underline mt-1 inline-block">
                                                            Source documents
                                                        </Link>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-right font-mono tabular-nums text-terracotta font-semibold">
                                                    RM {formatMoney(acc.amount)}
                                                </td>
                                                {comparisonOn && (
                                                    <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                                        RM {formatMoney(acc.compare_amount)}
                                                    </td>
                                                )}
                                                {comparisonOn && (
                                                    <td className={`px-6 py-4 text-right font-mono tabular-nums font-semibold ${varianceClass(acc.variance)}`}>
                                                        RM {formatMoney(acc.variance)}
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={comparisonOn ? 4 : 2} className="px-6 py-8 text-center text-ink-muted text-sm">
                                                No expenses in this period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-border-warm bg-cream/80 font-semibold">
                                        <td className="px-6 py-4">Total expenses</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-terracotta">RM {formatMoney(total_expenses)}</td>
                                        {comparisonOn && <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(compare_expenses)}</td>}
                                        {comparisonOn && <td className={`px-6 py-4 text-right font-mono tabular-nums ${varianceClass(expenses_variance)}`}>RM {formatMoney(expenses_variance)}</td>}
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border-2 border-border-warm/80 shadow-sm overflow-hidden">
                    <div className={`px-6 py-5 grid items-center gap-4 ${comparisonOn ? 'grid-cols-1 sm:grid-cols-4' : 'grid-cols-2'}`}>
                        <span className="text-lg font-display font-medium text-ink">Net {isProfit ? 'profit' : 'loss'}</span>
                        <span className={`text-2xl font-bold tabular-nums font-mono text-right ${isProfit ? 'text-forest' : 'text-terracotta'}`}>
                            {isProfit ? '' : '−'}RM {formatMoney(Math.abs(net_profit))}
                        </span>
                        {comparisonOn && (
                            <span className="text-right font-mono tabular-nums text-ink">RM {formatMoney(compare_net_profit)}</span>
                        )}
                        {comparisonOn && (
                            <span className={`text-right font-mono tabular-nums font-semibold ${varianceClass(net_profit_variance)}`}>
                                RM {formatMoney(net_profit_variance)}
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
