import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const Icons = {
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>,
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Sales({ auth, sales = [], sales_by_product = [], total_sales = 0, filters = {} }) {
    const { start_date = '', end_date = '' } = filters;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Sales Reports</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">
                        Summary of sales revenue by customer for the selected period.
                    </p>
                </div>
            }
        >
            <Head title="Sales Reports" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-end gap-3 bg-cream/50">
                        <form method="get" action={route('reports.sales.index')} className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">From</label>
                                <input
                                    type="date"
                                    name="start_date"
                                    defaultValue={start_date}
                                    className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">To</label>
                                <input
                                    type="date"
                                    name="end_date"
                                    defaultValue={end_date}
                                    className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                                />
                            </div>
                            <button
                                type="submit"
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta transition-colors"
                            >
                                Update report
                            </button>
                        </form>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total sales</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.ChartBar /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_sales)}</p>
                    </div>
                    <div className="bg-surface border border-border-warm rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">Active customers</span>
                            <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Users /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums text-ink">{sales.length}</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Sales by Customer</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4 text-center">Invoices</th>
                                    <th className="px-6 py-4 text-right">Total sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sales.length > 0 ? (
                                    sales.map((item, idx) => (
                                        <tr key={idx} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                            <td className="px-6 py-4 font-medium text-ink">{item.customer_name}</td>
                                            <td className="px-6 py-4 text-center text-ink">{item.invoice_count}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-terracotta font-semibold">
                                                RM {formatMoney(item.total_sales)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-8 text-center text-ink-muted text-sm">
                                            No sales recorded for this period.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-border-warm bg-cream/80 font-semibold text-ink">
                                    <td colSpan={2} className="px-6 py-4">Total</td>
                                    <td className="px-6 py-4 text-right font-mono tabular-nums">RM {formatMoney(total_sales)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Sales by Product</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Product / service</th>
                                    <th className="px-6 py-4 text-center">Qty</th>
                                    <th className="px-6 py-4 text-center">Invoices</th>
                                    <th className="px-6 py-4 text-right">Total sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sales_by_product.length > 0 ? (
                                    sales_by_product.map((item, idx) => (
                                        <tr key={idx} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                            <td className="px-6 py-4 font-medium text-ink">{item.product_name}</td>
                                            <td className="px-6 py-4 text-center text-ink">{item.quantity}</td>
                                            <td className="px-6 py-4 text-center text-ink">{item.invoice_count}</td>
                                            <td className="px-6 py-4 text-right font-mono tabular-nums text-terracotta font-semibold">
                                                RM {formatMoney(item.total_sales)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-8 text-center text-ink-muted text-sm">
                                            No product sales for this period. Link products on invoice lines to see this breakdown.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
