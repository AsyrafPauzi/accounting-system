import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

function formatDate(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function SalesTax({ auth, filters = {}, output_tax = 0, input_tax = 0, net_tax = 0,
    invoice_count = 0, bill_count = 0, taxable_sales = 0, taxable_purchases = 0,
    by_rate = [], invoices = [], bills = [], base_currency = 'MYR' }) {

    const { start_date = '', end_date = '' } = filters;
    const netLabel = net_tax > 0 ? 'You owe' : net_tax < 0 ? 'Reclaimable / receivable' : 'Settled';
    const netColor = net_tax > 0 ? 'text-terracotta' : net_tax < 0 ? 'text-emerald-600' : 'text-ink';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-terracotta">Reports</p>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Sales Tax Report</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">SST/GST collected on sales vs paid on purchases.</p>
                </div>
            }
        >
            <Head title="Sales Tax Report" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <form method="get" action={route('reports.sales-tax.index')} className="flex flex-wrap items-end gap-3">
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

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-5">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Output tax (collected)</div>
                        <div className="mt-2 text-2xl font-bold tabular-nums text-ink">{formatCurrency(output_tax, base_currency)}</div>
                        <div className="text-xs text-ink-muted mt-1">on {invoice_count} invoice{invoice_count === 1 ? '' : 's'} ({formatCurrency(taxable_sales, base_currency)} taxable)</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-5">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Input tax (paid)</div>
                        <div className="mt-2 text-2xl font-bold tabular-nums text-ink">{formatCurrency(input_tax, base_currency)}</div>
                        <div className="text-xs text-ink-muted mt-1">on {bill_count} bill{bill_count === 1 ? '' : 's'} ({formatCurrency(taxable_purchases, base_currency)} taxable)</div>
                    </div>
                    <div className="bg-ink dark:bg-surface-alt rounded-2xl border border-border-warm shadow-sm p-5">
                        <div className="text-[10px] font-display font-medium text-cream/70 dark:text-ink-muted uppercase tracking-widest">Net tax payable</div>
                        <div className={`mt-2 text-2xl font-bold tabular-nums ${net_tax > 0 ? 'text-terracotta-light' : net_tax < 0 ? 'text-emerald-400' : 'text-cream dark:text-ink'}`}>
                            {formatCurrency(Math.abs(net_tax), base_currency)}
                        </div>
                        <div className="text-xs text-cream/70 dark:text-ink-muted mt-1">{netLabel}</div>
                    </div>
                </div>

                {by_rate.length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm">
                            <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Sales tax breakdown by rate</h3>
                        </div>
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Tax rate</th>
                                    <th className="px-6 py-3 text-right">Taxable sales</th>
                                    <th className="px-6 py-3 text-right">Tax collected</th>
                                    <th className="px-6 py-3 text-right">Invoices</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {by_rate.map((r, idx) => (
                                    <tr key={idx} className="hover:bg-cream/30">
                                        <td className="px-6 py-3 font-mono">{r.tax_rate.toFixed(2)}%</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums">{formatCurrency(r.taxable, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums font-semibold">{formatCurrency(r.tax_collected, base_currency)}</td>
                                        <td className="px-6 py-3 text-right">{r.invoice_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm">
                            <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Invoices with tax</h3>
                            <p className="text-xs text-ink-muted mt-0.5">{invoices.length === 500 ? 'First 500' : `${invoices.length}`} invoice{invoices.length === 1 ? '' : 's'}</p>
                        </div>
                        <div className="overflow-x-auto max-h-[480px]">
                            <table className="w-full text-left text-sm">
                                <thead className="sticky top-0">
                                    <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                        <th className="px-4 py-2.5">Date</th>
                                        <th className="px-4 py-2.5">Invoice</th>
                                        <th className="px-4 py-2.5 text-right">Tax</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border-warm">
                                    {invoices.map((i) => (
                                        <tr key={i.id} className="hover:bg-cream/30">
                                            <td className="px-4 py-2 text-xs">{formatDate(i.issue_date)}</td>
                                            <td className="px-4 py-2">
                                                <Link href={route('invoices.show', i.id)} className="font-semibold text-terracotta hover:underline">{i.invoice_number}</Link>
                                                <p className="text-[11px] text-ink-muted">{i.customer}</p>
                                            </td>
                                            <td className="px-4 py-2 text-right font-mono tabular-nums">{formatCurrency(i.tax, base_currency)}</td>
                                        </tr>
                                    ))}
                                    {invoices.length === 0 && (
                                        <tr><td colSpan={3} className="px-4 py-8 text-center text-ink-muted italic text-xs">No taxable invoices in this period.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm">
                            <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Bills with tax</h3>
                            <p className="text-xs text-ink-muted mt-0.5">{bills.length === 500 ? 'First 500' : `${bills.length}`} bill{bills.length === 1 ? '' : 's'}</p>
                        </div>
                        <div className="overflow-x-auto max-h-[480px]">
                            <table className="w-full text-left text-sm">
                                <thead className="sticky top-0">
                                    <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                        <th className="px-4 py-2.5">Date</th>
                                        <th className="px-4 py-2.5">Bill</th>
                                        <th className="px-4 py-2.5 text-right">Tax</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border-warm">
                                    {bills.map((b) => (
                                        <tr key={b.id} className="hover:bg-cream/30">
                                            <td className="px-4 py-2 text-xs">{formatDate(b.bill_date)}</td>
                                            <td className="px-4 py-2">
                                                <Link href={route('bills.edit', b.id)} className="font-semibold text-terracotta hover:underline">{b.bill_number}</Link>
                                                <p className="text-[11px] text-ink-muted">{b.supplier}</p>
                                            </td>
                                            <td className="px-4 py-2 text-right font-mono tabular-nums">{formatCurrency(b.tax, base_currency)}</td>
                                        </tr>
                                    ))}
                                    {bills.length === 0 && (
                                        <tr><td colSpan={3} className="px-4 py-8 text-center text-ink-muted italic text-xs">No taxable bills in this period.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
