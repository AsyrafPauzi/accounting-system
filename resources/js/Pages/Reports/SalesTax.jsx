import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import ReportPeriodChips from '@/Components/ReportPeriodChips';
import { alertUpgrade } from '@/utils/swal';

function formatDate(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function SalesTax({ auth, filters = {}, output_tax = 0, input_tax = 0, net_tax = 0,
    invoice_count = 0, bill_count = 0, taxable_sales = 0, taxable_purchases = 0,
    pack = {}, by_rate = [], invoices = [], bills = [], myinvois_gaps = [],
    gap_counts = { missing: 0, pending: 0, rejected: 0 }, base_currency = 'MYR' }) {

    const { preset = 'custom', start_date = '', end_date = '' } = filters;
    const netLabel = net_tax > 0 ? 'You owe' : net_tax < 0 ? 'Reclaimable / receivable' : 'Settled';
    const exportQuery = new URLSearchParams({ preset, start_date: start_date || '', end_date: end_date || '' });
    const gapTotal = Number(gap_counts.missing || 0) + Number(gap_counts.pending || 0) + Number(gap_counts.rejected || 0);
    const canExportPdf = auth.planPermissions?.['reports.export.full'];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-terracotta">Reports</p>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Sales Tax Report</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">SST period pack — figures for your return, not a filed form.</p>
                </div>
            }
        >
            <Head title="Sales Tax Report" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <ReportPeriodChips
                            action={route('reports.sales-tax.index')}
                            preset={preset}
                            fromKey="start_date"
                            toKey="end_date"
                            dateFrom={start_date}
                            dateTo={end_date}
                            extraChips={[{ id: 'this_sst_period', label: 'This SST period' }]}
                        />
                    </div>
                    <div className="px-6 py-4 flex flex-wrap items-center gap-3">
                        <a
                            href={`${route('reports.sales-tax.export.csv')}?${exportQuery}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                        >
                            Download CSV
                        </a>
                        <a
                            href={canExportPdf ? `${route('reports.sales-tax.export.pdf')}?${exportQuery}` : '#'}
                            onClick={(event) => {
                                if (! canExportPdf) {
                                    event.preventDefault();
                                    alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                }
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors ${
                                canExportPdf
                                    ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                    : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                            }`}
                        >
                            Download PDF
                            {! canExportPdf && (
                                <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2h2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                            )}
                        </a>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
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
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-5">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Taxable sales</div>
                        <div className="mt-2 text-2xl font-bold tabular-nums text-ink">{formatCurrency(pack.taxable_sales ?? taxable_sales, base_currency)}</div>
                        <div className="text-xs text-ink-muted mt-1">{formatDate(pack.period_from)} – {formatDate(pack.period_to)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-5">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Exempt sales</div>
                        <div className="mt-2 text-2xl font-bold tabular-nums text-ink">{formatCurrency(pack.exempt_sales ?? 0, base_currency)}</div>
                        <div className="text-xs text-ink-muted mt-1">0% invoice lines</div>
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
                                        <td className="px-6 py-3 font-mono">{r.label ?? `${Number(r.tax_rate).toFixed(2)}%`}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums">{formatCurrency(r.taxable, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums font-semibold">{formatCurrency(r.tax_collected, base_currency)}</td>
                                        <td className="px-6 py-3 text-right">{r.invoice_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">MyInvois submission gaps</h3>
                            <p className="text-xs text-ink-muted mt-0.5">
                                Missing {gap_counts.missing || 0} · Pending {gap_counts.pending || 0} · Rejected/invalid {gap_counts.rejected || 0}
                                {gapTotal > myinvois_gaps.length ? ' · First 200 shown' : ''}
                            </p>
                        </div>
                    </div>
                    <div className="overflow-x-auto max-h-[480px]">
                        <table className="w-full text-left text-sm">
                            <thead className="sticky top-0">
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-4 py-2.5">Date</th>
                                    <th className="px-4 py-2.5">Invoice</th>
                                    <th className="px-4 py-2.5">Customer</th>
                                    <th className="px-4 py-2.5">Gap</th>
                                    <th className="px-4 py-2.5 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {myinvois_gaps.map((invoice) => (
                                    <tr key={invoice.id} className="hover:bg-cream/30">
                                        <td className="px-4 py-2 text-xs">{formatDate(invoice.issue_date)}</td>
                                        <td className="px-4 py-2">
                                            <Link href={route('invoices.show', invoice.id)} className="font-semibold text-terracotta hover:underline">{invoice.invoice_number}</Link>
                                        </td>
                                        <td className="px-4 py-2">{invoice.customer}</td>
                                        <td className="px-4 py-2">
                                            <span className="inline-flex rounded-full bg-terracotta/10 px-2 py-1 text-xs font-semibold text-terracotta">{invoice.reason}</span>
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono tabular-nums">{formatCurrency(invoice.total, base_currency)}</td>
                                    </tr>
                                ))}
                                {myinvois_gaps.length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-8 text-center text-ink-muted italic text-xs">No MyInvois gaps in this period.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

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
