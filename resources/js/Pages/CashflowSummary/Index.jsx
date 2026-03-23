import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';

const Icons = {
    ArrowTrendingUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 011.414-1.414l2.25-2.25M3 75h13.5A2.25 2.25 0 0019 72.75V60m-12-12V60m12 0V72.75" /></svg>,
    ArrowTrendingDown: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181" /></svg>,
    Banknotes: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>,
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const SALES_COLOR = '#2563eb';
const EXPENSES_COLOR = '#dc2626';

export default function Index({ auth, summary = {}, chartData = [], filters = {} }) {
    const { total_sales = 0, total_expenses = 0, net_cashflow = 0 } = summary;
    const { date_from = '', date_to = '' } = filters;

    const handleFilterSubmit = (e) => {
        e.preventDefault();
        const form = e.target;
        const dateFrom = form.date_from?.value || '';
        const dateTo = form.date_to?.value || '';
        router.get(route('cashflow-summary.index'), { date_from: dateFrom, date_to: dateTo }, { preserveState: false });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Cashflow Summary</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Total Sales vs Total Expenses — see how money moves</p>
                    </div>
                    <Link
                        href={route('invoices.index')}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 shadow-sm"
                    >
                        View invoices
                    </Link>
                </div>
            }
        >
            <Head title="Cashflow Summary" />

            <div className="space-y-6">
                {/* Date filter */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4">
                    <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-end gap-4">
                        <div>
                            <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">From</label>
                            <input
                                type="date"
                                name="date_from"
                                defaultValue={date_from}
                                className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">To</label>
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
                            Update period
                        </button>
                    </form>
                </div>

                {/* KPI cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total Sales</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.ArrowTrendingUp /></span>
                        </div>
                        <p className="text-2xl font-bold font-mono tabular-nums">RM {formatMoney(total_sales)}</p>
                        <p className="text-xs text-blue-100 mt-1">Posted invoices in period</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total Expenses</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.ArrowTrendingDown /></span>
                        </div>
                        <p className="text-2xl font-bold text-rose-600 font-mono tabular-nums">RM {formatMoney(total_expenses)}</p>
                        <p className="text-xs text-slate-500 mt-1">Posted bills in period</p>
                    </div>
                    <div className={`rounded-2xl p-6 border shadow-sm ${net_cashflow >= 0 ? 'bg-white border-slate-100' : 'bg-rose-50/50 border-rose-100'}`}>
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Net Cashflow</span>
                            <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Banknotes /></span>
                        </div>
                        <p className={`text-2xl font-bold font-mono tabular-nums ${net_cashflow >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                            RM {formatMoney(net_cashflow)}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">Sales minus expenses</p>
                    </div>
                </div>

                {/* Chart */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden p-6">
                    <div className="flex items-center gap-2 mb-4">
                        <Icons.ChartBar />
                        <h3 className="text-sm font-bold text-slate-800">Sales vs Expenses by month</h3>
                    </div>
                    {chartData.length > 0 ? (
                        <div className="h-[380px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    data={chartData}
                                    margin={{ top: 12, right: 12, left: 0, bottom: 12 }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                    <XAxis
                                        dataKey="month_label"
                                        tick={{ fontSize: 11, fill: '#64748b' }}
                                        stroke="#94a3b8"
                                    />
                                    <YAxis
                                        tick={{ fontSize: 11, fill: '#64748b' }}
                                        stroke="#94a3b8"
                                        tickFormatter={(v) => `RM ${(v / 1000).toFixed(0)}k`}
                                    />
                                    <Tooltip
                                        formatter={(value) => ['RM ' + formatMoney(value)]}
                                        labelFormatter={(_, payload) => payload?.[0]?.payload?.month_label}
                                        contentStyle={{ borderRadius: '12px', border: '1px solid #e2e8f0' }}
                                    />
                                    <Legend />
                                    <Bar dataKey="sales" name="Sales" fill={SALES_COLOR} radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="expenses" name="Expenses" fill={EXPENSES_COLOR} radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    ) : (
                        <div className="h-[280px] flex items-center justify-center text-slate-400 text-sm font-medium">
                            No data in this period. Post invoices and bills to see sales and expenses.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
