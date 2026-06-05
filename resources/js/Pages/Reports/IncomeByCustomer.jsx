import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

export default function IncomeByCustomer({ auth, filters = {}, rows = [], totals = {}, base_currency = 'MYR' }) {
    const { start_date = '', end_date = '' } = filters;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-terracotta">Reports</p>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Income by Customer</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Paid and unpaid revenue per customer for the selected period.</p>
                </div>
            }
        >
            <Head title="Income by Customer" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <form method="get" action={route('reports.income-by-customer.index')} className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">From</label>
                                <input type="date" name="start_date" defaultValue={start_date} className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta" />
                            </div>
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">To</label>
                                <input type="date" name="end_date" defaultValue={end_date} className="border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta" />
                            </div>
                            <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark transition-colors">Update report</button>
                        </form>
                    </div>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Customers</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">{totals.customer_count || 0}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Total invoiced</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">{formatCurrency(totals.total_invoiced || 0, base_currency)}</div>
                        <div className="text-xs text-ink-muted mt-1">{totals.invoice_count || 0} invoice{totals.invoice_count === 1 ? '' : 's'}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Paid</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-emerald-600">{formatCurrency(totals.total_paid || 0, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Unpaid</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-terracotta">{formatCurrency(totals.total_unpaid || 0, base_currency)}</div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Customer</th>
                                    <th className="px-6 py-3 text-right">Invoices</th>
                                    <th className="px-6 py-3 text-right">Total invoiced</th>
                                    <th className="px-6 py-3 text-right">Paid</th>
                                    <th className="px-6 py-3 text-right">Unpaid</th>
                                    <th className="px-6 py-3 text-right">Statement</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {rows.length > 0 ? rows.map((r) => (
                                    <tr key={r.customer_id || r.customer_name} className="hover:bg-cream/30">
                                        <td className="px-6 py-3">
                                            <p className="font-semibold text-ink">{r.customer_name}</p>
                                            {r.customer_email && <p className="text-[11px] text-ink-muted">{r.customer_email}</p>}
                                        </td>
                                        <td className="px-6 py-3 text-right text-sm tabular-nums">{r.invoice_count}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums font-semibold">{formatCurrency(r.total_invoiced, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-emerald-600">{formatCurrency(r.total_paid, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-terracotta">{formatCurrency(r.total_unpaid, base_currency)}</td>
                                        <td className="px-6 py-3 text-right">
                                            {r.customer_id && (
                                                <Link
                                                    href={route('customer-statements.show', r.customer_id)}
                                                    className="text-xs font-semibold text-terracotta hover:underline"
                                                >
                                                    Open →
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan={6} className="px-6 py-12 text-center text-ink-muted italic text-sm">No invoices in this period.</td></tr>
                                )}
                            </tbody>
                            {rows.length > 0 && (
                                <tfoot>
                                    <tr className="bg-cream/60 border-t-2 border-border-warm font-bold">
                                        <td className="px-6 py-3 text-ink uppercase tracking-wider text-xs">Totals</td>
                                        <td className="px-6 py-3 text-right tabular-nums">{totals.invoice_count || 0}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums">{formatCurrency(totals.total_invoiced || 0, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-emerald-600">{formatCurrency(totals.total_paid || 0, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-terracotta">{formatCurrency(totals.total_unpaid || 0, base_currency)}</td>
                                        <td className="px-6 py-3"></td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
