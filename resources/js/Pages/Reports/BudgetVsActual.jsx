import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ReportPeriodChips from '@/Components/ReportPeriodChips';

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function varianceClass(value) {
    if (Number(value) > 0) return 'text-forest';
    if (Number(value) < 0) return 'text-terracotta';
    return 'text-ink-muted';
}

function VarianceTable({ title, rows, totalBudget, totalActual, totalVariance }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
            <div className="px-5 py-3 border-b border-border-warm bg-cream/50">
                <p className="text-sm font-semibold text-ink">{title}</p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted border-b border-border-warm">
                            <th className="px-4 py-3 text-left">Account</th>
                            <th className="px-4 py-3 text-right">Budget</th>
                            <th className="px-4 py-3 text-right">Actual</th>
                            <th className="px-4 py-3 text-right">Variance</th>
                            <th className="px-4 py-3 text-right">Var %</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.code} className="border-b border-border-warm/70 last:border-0">
                                <td className="px-4 py-2.5">
                                    <div className="font-medium text-ink">{row.name}</div>
                                    <div className="text-xs font-mono text-ink-muted">{row.code}</div>
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums">{formatMoney(row.budget)}</td>
                                <td className="px-4 py-2.5 text-right tabular-nums">{formatMoney(row.actual)}</td>
                                <td className={`px-4 py-2.5 text-right tabular-nums font-semibold ${varianceClass(row.variance)}`}>{formatMoney(row.variance)}</td>
                                <td className={`px-4 py-2.5 text-right tabular-nums ${varianceClass(row.variance)}`}>
                                    {row.variance_pct === null ? '—' : `${row.variance_pct}%`}
                                </td>
                            </tr>
                        ))}
                        <tr className="bg-cream/40 font-semibold">
                            <td className="px-4 py-3">Total</td>
                            <td className="px-4 py-3 text-right tabular-nums">{formatMoney(totalBudget)}</td>
                            <td className="px-4 py-3 text-right tabular-nums">{formatMoney(totalActual)}</td>
                            <td className={`px-4 py-3 text-right tabular-nums ${varianceClass(totalVariance)}`}>{formatMoney(totalVariance)}</td>
                            <td className="px-4 py-3" />
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function BudgetVsActual({
    auth,
    budget,
    revenue_rows = [],
    expense_rows = [],
    total_budget_revenue = 0,
    total_actual_revenue = 0,
    total_variance_revenue = 0,
    total_budget_expense = 0,
    total_actual_expense = 0,
    total_variance_expense = 0,
    filters = {},
    yearOptions = [],
}) {
    const { flash } = usePage().props;
    const { preset = 'this_month', date_from = '', date_to = '', fiscal_year = new Date().getFullYear() } = filters;

    const exportCsv = () => {
        window.location.href = route('reports.budget-vs-actual.export.csv', {
            preset,
            date_from,
            date_to,
            fiscal_year,
        });
    };

    const changeYear = (year) => {
        router.get(route('reports.budget-vs-actual.index'), {
            preset,
            date_from,
            date_to,
            fiscal_year: year,
        }, { preserveScroll: true, preserveState: false });
    };

    const hasRows = revenue_rows.length > 0 || expense_rows.length > 0;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Budget vs actual</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Compare budgeted P&amp;L to posted general ledger for the selected period.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('budgets.index', { fiscal_year })} className="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                            Edit budget
                        </Link>
                        {budget && hasRows && (
                            <button type="button" onClick={exportCsv} className="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-forest hover:bg-forest/90">
                                Export CSV
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Budget vs actual" />

            {flash?.success && <div className="mb-4 rounded-xl bg-forest/10 text-forest px-4 py-3 text-sm">{flash.success}</div>}

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-end gap-4 bg-cream/50">
                        <ReportPeriodChips
                            action={route('reports.budget-vs-actual.index')}
                            preset={preset}
                            dateFrom={date_from}
                            dateTo={date_to}
                            extraParams={{ fiscal_year }}
                        />
                        <div>
                            <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5">Budget year</label>
                            <select
                                className="h-10 border border-border-warm rounded-xl px-3 text-sm font-medium"
                                value={fiscal_year}
                                onChange={(e) => changeYear(Number(e.target.value))}
                            >
                                {yearOptions.map((year) => (
                                    <option key={year} value={year}>{year}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {! budget ? (
                    <div className="rounded-2xl border border-border-warm bg-surface px-6 py-10 text-center text-sm text-ink-muted">
                        No budget for {fiscal_year} yet.{' '}
                        <Link href={route('budgets.index', { fiscal_year })} className="text-terracotta font-semibold hover:underline">
                            Enter budget amounts
                        </Link>
                    </div>
                ) : ! hasRows ? (
                    <div className="rounded-2xl border border-border-warm bg-surface px-6 py-10 text-center text-sm text-ink-muted">
                        No budget or actual activity in this period. Enter budgets or post transactions, then refresh.
                    </div>
                ) : (
                    <>
                        <VarianceTable
                            title="Revenue"
                            rows={revenue_rows}
                            totalBudget={total_budget_revenue}
                            totalActual={total_actual_revenue}
                            totalVariance={total_variance_revenue}
                        />
                        <VarianceTable
                            title="Expenses"
                            rows={expense_rows}
                            totalBudget={total_budget_expense}
                            totalActual={total_actual_expense}
                            totalVariance={total_variance_expense}
                        />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
