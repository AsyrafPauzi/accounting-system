import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const inputClass = 'w-full h-10 border border-border-warm rounded-xl px-3 text-right font-mono text-sm text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

const MONTHS = [
    { value: 1, label: 'Jan' }, { value: 2, label: 'Feb' }, { value: 3, label: 'Mar' },
    { value: 4, label: 'Apr' }, { value: 5, label: 'May' }, { value: 6, label: 'Jun' },
    { value: 7, label: 'Jul' }, { value: 8, label: 'Aug' }, { value: 9, label: 'Sep' },
    { value: 10, label: 'Oct' }, { value: 11, label: 'Nov' }, { value: 12, label: 'Dec' },
];

export default function Index({
    auth,
    budget,
    accounts = [],
    amounts = {},
    fiscalYear,
    selectedMonth,
    yearOptions = [],
}) {
    const { flash } = usePage().props;
    const initialLines = useMemo(() => accounts.map((account) => ({
        account_code: account.code,
        amount: amounts[account.code]?.toString() || '',
    })), [accounts, amounts]);

    const { data, setData, processing, errors } = useForm({
        month: selectedMonth,
        lines: initialLines,
    });

    const [localAmounts, setLocalAmounts] = useState(() => {
        const map = {};
        accounts.forEach((account) => {
            map[account.code] = amounts[account.code]?.toString() || '';
        });
        return map;
    });

    const changeYear = (year) => {
        router.get(route('budgets.index'), { fiscal_year: year, month: selectedMonth }, { preserveState: false });
    };

    const changeMonth = (month) => {
        router.get(route('budgets.index'), { fiscal_year: fiscalYear, month }, { preserveState: false });
    };

    const setAmount = (code, value) => {
        setLocalAmounts((prev) => {
            const next = { ...prev, [code]: value };
            setData('lines', accounts.map((account) => ({
                account_code: account.code,
                amount: next[account.code] || '',
            })));
            return next;
        });
    };

    const submit = (e) => {
        e.preventDefault();
        router.patch(route('budgets.update', budget.id), {
            month: selectedMonth,
            lines: accounts.map((account) => ({
                account_code: account.code,
                amount: localAmounts[account.code] || '',
            })),
        }, { preserveScroll: true });
    };

    const revenueAccounts = accounts.filter((a) => a.type === 'income');
    const expenseAccounts = accounts.filter((a) => a.type === 'expense');

    const renderSection = (title, rows) => (
        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-border-warm bg-cream/50">
                <p className="text-sm font-semibold text-ink">{title}</p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted border-b border-border-warm">
                            <th className="px-4 py-3 text-left">Account</th>
                            <th className="px-4 py-3 text-right w-40">Budget (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((account) => (
                            <tr key={account.code} className="border-b border-border-warm/70 last:border-0">
                                <td className="px-4 py-2.5">
                                    <div className="font-medium text-ink">{account.name}</div>
                                    <div className="text-xs font-mono text-ink-muted">{account.code}</div>
                                </td>
                                <td className="px-4 py-2.5">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className={inputClass}
                                        value={localAmounts[account.code] || ''}
                                        onChange={(e) => setAmount(account.code, e.target.value)}
                                        placeholder="0.00"
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Budgets</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Monthly budget by P&amp;L account — compare against actuals in the variance report.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('reports.budget-vs-actual.index', { fiscal_year: fiscalYear })} className="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-forest hover:bg-forest/90">
                            Budget vs actual
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Budgets" />

            {flash?.success && <div className="mb-4 rounded-xl bg-forest/10 text-forest px-4 py-3 text-sm">{flash.success}</div>}
            {errors.lines && <div className="mb-4 rounded-xl bg-terracotta/10 text-terracotta px-4 py-3 text-sm">Check the amounts and try again.</div>}

            <form onSubmit={submit} className="space-y-5">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-end gap-4">
                    <div>
                        <label className={labelClass}>Fiscal year</label>
                        <select className="h-11 border border-border-warm rounded-xl px-4 text-sm font-medium" value={fiscalYear} onChange={(e) => changeYear(Number(e.target.value))}>
                            {yearOptions.map((year) => (
                                <option key={year} value={year}>{year}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex-1">
                        <label className={labelClass}>Month</label>
                        <div className="flex flex-wrap gap-1.5 mt-0.5">
                            {MONTHS.map((month) => (
                                <button
                                    key={month.value}
                                    type="button"
                                    onClick={() => changeMonth(month.value)}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${selectedMonth === month.value ? 'bg-terracotta text-white border-terracotta' : 'bg-surface text-ink border-border-warm hover:bg-cream'}`}
                                >
                                    {month.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    <button type="submit" disabled={processing} className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50">
                        {processing ? 'Saving…' : 'Save month'}
                    </button>
                </div>

                {renderSection('Revenue accounts', revenueAccounts)}
                {renderSection('Expense accounts', expenseAccounts)}
            </form>
        </AuthenticatedLayout>
    );
}
