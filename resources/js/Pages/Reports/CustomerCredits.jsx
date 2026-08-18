import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import ReportPeriodChips from '@/Components/ReportPeriodChips';

function formatDate(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

const statusBadge = {
    open:    'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    sent:    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    applied: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    draft:   'bg-surface-alt text-ink',
};

export default function CustomerCredits({ auth, filters = {}, rows = [], totals = {}, details = [], base_currency = 'MYR' }) {
    const { preset = 'custom', as_of_date = '' } = filters;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-terracotta">Reports</p>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Customer Credits</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Outstanding credit notes and deposits held on customer accounts as of {formatDate(as_of_date) || 'today'}.</p>
                </div>
            }
        >
            <Head title="Customer Credits" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <ReportPeriodChips
                            action={route('reports.customer-credits.index')}
                            preset={preset}
                            mode="as_of"
                            asOfKey="as_of_date"
                            asOf={as_of_date}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Customers with credit</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">{totals.customer_count || 0}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Open credits issued</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">{formatCurrency(totals.total_issued || 0, base_currency)}</div>
                        <div className="text-xs text-ink-muted mt-1">{totals.note_count || 0} credit note{totals.note_count === 1 ? '' : 's'}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Applied</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-emerald-600">{formatCurrency(totals.applied_amount || 0, base_currency)}</div>
                    </div>
                    <div className="bg-ink dark:bg-surface-alt rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-cream/70 dark:text-ink-muted uppercase tracking-widest">Open balance</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-terracotta-light dark:text-terracotta">{formatCurrency(totals.open_amount || 0, base_currency)}</div>
                        <div className="text-[11px] text-cream/70 dark:text-ink-muted mt-1">credit owed back to customers</div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm">
                        <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Open balance per customer</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Customer</th>
                                    <th className="px-6 py-3 text-right">Notes</th>
                                    <th className="px-6 py-3 text-right">Total issued</th>
                                    <th className="px-6 py-3 text-right">Applied</th>
                                    <th className="px-6 py-3 text-right">Open</th>
                                    <th className="px-6 py-3 text-right">Last issued</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {rows.length > 0 ? rows.map((r) => (
                                    <tr key={r.customer_id || r.customer_name} className="hover:bg-cream/30">
                                        <td className="px-6 py-3">
                                            <p className="font-semibold text-ink">{r.customer_name}</p>
                                            {r.customer_email && <p className="text-[11px] text-ink-muted">{r.customer_email}</p>}
                                        </td>
                                        <td className="px-6 py-3 text-right tabular-nums">{r.note_count}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums">{formatCurrency(r.total_issued, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-emerald-600">{formatCurrency(r.applied_amount, base_currency)}</td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums font-semibold text-terracotta">{formatCurrency(r.open_amount, base_currency)}</td>
                                        <td className="px-6 py-3 text-right text-xs text-ink-muted">{formatDate(r.last_issued_at)}</td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan={6} className="px-6 py-12 text-center text-ink-muted italic text-sm">No credit notes on file.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {details.length > 0 && (
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm">
                            <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Open credit notes</h3>
                            <p className="text-xs text-ink-muted mt-0.5">{details.length === 500 ? 'First 500' : `${details.length}`} open note{details.length === 1 ? '' : 's'} as of {formatDate(as_of_date) || 'today'}, newest first</p>
                        </div>
                        <div className="overflow-x-auto max-h-[480px]">
                            <table className="w-full text-left">
                                <thead className="sticky top-0">
                                    <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                        <th className="px-6 py-3">Date</th>
                                        <th className="px-6 py-3">Reference</th>
                                        <th className="px-6 py-3">Customer</th>
                                        <th className="px-6 py-3">Reason</th>
                                        <th className="px-6 py-3">Status</th>
                                        <th className="px-6 py-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border-warm">
                                    {details.map((d) => (
                                        <tr key={d.id} className="hover:bg-cream/30 text-sm">
                                            <td className="px-6 py-2.5 text-xs">{formatDate(d.issue_date)}</td>
                                            <td className="px-6 py-2.5">
                                                <span className="font-semibold text-ink">{d.cn_number}</span>
                                                {d.invoice_number && <p className="text-[11px] text-ink-muted">vs {d.invoice_number}</p>}
                                            </td>
                                            <td className="px-6 py-2.5">{d.customer_name}</td>
                                            <td className="px-6 py-2.5 text-xs text-ink-muted">{d.reason || '—'}</td>
                                            <td className="px-6 py-2.5">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold ${statusBadge[d.status] || 'bg-surface-alt text-ink'}`}>
                                                    {d.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-2.5 text-right font-mono tabular-nums font-semibold">{formatCurrency(d.amount, base_currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
