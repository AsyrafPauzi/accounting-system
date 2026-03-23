import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

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

export default function ProfitAndLoss({ auth, revenue_accounts = [], expense_accounts = [], total_revenue = 0, total_expenses = 0, net_profit = 0, filters = {} }) {
    const { flash } = usePage().props;
    const { date_from = '', date_to = '' } = filters;
    const isProfit = net_profit >= 0;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Profit &amp; Loss</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Real-time report of Income vs Expenses from your general ledger.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Profit & Loss" />

            {(flash?.success || flash?.error) && (
                <div
                    className={`mb-4 rounded-xl border px-4 py-3 text-sm font-medium ${flash.error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
                >
                    {flash.success || flash.error}
                </div>
            )}

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-end gap-3 bg-slate-50/50">
                        <form method="get" action={route('profit-and-loss.index')} className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">From</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    defaultValue={date_from}
                                    className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">To</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    defaultValue={date_to}
                                    className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <button
                                type="submit"
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                            >
                                Update report
                            </button>
                            <a
                                href={`${route('profit-and-loss.export.csv')}?${new URLSearchParams({ date_from: date_from || '', date_to: date_to || '' })}`}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                            >
                                <Icons.ArrowDownTray /> Download CSV
                            </a>
                            <a
                                href={`${route('profit-and-loss.export.pdf')}?${new URLSearchParams({ date_from: date_from || '', date_to: date_to || '' })}`}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                            >
                                <Icons.DocumentArrowDown /> Download PDF
                            </a>
                        </form>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total revenue</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.ArrowUp /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_revenue)}</p>
                        <p className="text-xs text-emerald-100 mt-1">Income accounts</p>
                    </div>
                    <div className="relative overflow-hidden bg-gradient-to-br from-rose-600 to-red-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total expenses</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.ArrowDown /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_expenses)}</p>
                        <p className="text-xs text-rose-100 mt-1">Expense accounts</p>
                    </div>
                    <div className={`rounded-2xl p-6 shadow-lg border-2 ${isProfit ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'}`}>
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-600">Net {isProfit ? 'profit' : 'loss'}</span>
                            <span className={`p-2 rounded-xl ${isProfit ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}><Icons.Scale /></span>
                        </div>
                        <p className={`text-2xl font-bold tabular-nums ${isProfit ? 'text-emerald-700' : 'text-rose-700'}`}>
                            {isProfit ? '' : '-'}RM {formatMoney(Math.abs(net_profit))}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">Revenue − Expenses</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                            <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Revenue (Income)</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                        <th className="px-6 py-4">Account</th>
                                        <th className="px-6 py-4 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {revenue_accounts.length > 0 ? (
                                        revenue_accounts.map((acc) => (
                                            <tr key={acc.code} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80">
                                                <td className="px-6 py-4">
                                                    <span className="font-mono text-slate-600 text-xs">{acc.code}</span>
                                                    <span className="block font-medium text-slate-800">{acc.name}</span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-mono tabular-nums text-emerald-700 font-semibold">
                                                    RM {formatMoney(acc.amount)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={2} className="px-6 py-8 text-center text-slate-400 text-sm">
                                                No revenue in this period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-slate-200 bg-slate-50/80 font-semibold">
                                        <td className="px-6 py-4">Total revenue</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-emerald-700">RM {formatMoney(total_revenue)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                            <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Expenses</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                        <th className="px-6 py-4">Account</th>
                                        <th className="px-6 py-4 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {expense_accounts.length > 0 ? (
                                        expense_accounts.map((acc) => (
                                            <tr key={acc.code} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80">
                                                <td className="px-6 py-4">
                                                    <span className="font-mono text-slate-600 text-xs">{acc.code}</span>
                                                    <span className="block font-medium text-slate-800">{acc.name}</span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-mono tabular-nums text-rose-700 font-semibold">
                                                    RM {formatMoney(acc.amount)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={2} className="px-6 py-8 text-center text-slate-400 text-sm">
                                                No expenses in this period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-slate-200 bg-slate-50/80 font-semibold">
                                        <td className="px-6 py-4">Total expenses</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-rose-700">RM {formatMoney(total_expenses)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border-2 border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 flex items-center justify-between">
                        <span className="text-lg font-bold text-slate-800">Net {isProfit ? 'profit' : 'loss'}</span>
                        <span className={`text-2xl font-bold tabular-nums font-mono ${isProfit ? 'text-emerald-700' : 'text-rose-700'}`}>
                            {isProfit ? '' : '−'}RM {formatMoney(Math.abs(net_profit))}
                        </span>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
