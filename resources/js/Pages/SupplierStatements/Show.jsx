import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

const Icons = {
    Statement: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2a4 4 0 014-4h4a4 4 0 014 4v2M3 7h2m0 0h2M5 7v2m0-2V5m9 4a2 2 0 11-4 0 2 2 0 014 0zM7 13H4a1 1 0 00-1 1v6a1 1 0 001 1h3" /></svg>,
    Back: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>,
};

export default function Show({ auth, supplier, statement, base_currency = 'MYR' }) {
    const [from, setFrom] = useState(statement.from);
    const [to, setTo] = useState(statement.to);

    const apply = () => {
        router.get(route('supplier-statements.show', supplier.id), { from, to }, { preserveState: false });
    };

    const closingClass = statement.closing_balance > 0 ? 'text-terracotta' : statement.closing_balance < 0 ? 'text-emerald-600' : 'text-ink';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('supplier-statements.index')}
                        className="p-2 rounded-xl bg-surface-alt text-ink-muted hover:text-ink"
                        aria-label="Back to supplier statements"
                    >
                        <Icons.Back />
                    </Link>
                    <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                        <Icons.Statement />
                    </span>
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">
                            Statement · {supplier.name}
                        </h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            {formatDate(statement.from)} &mdash; {formatDate(statement.to)}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Statement · ${supplier.name}`} />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4 sm:p-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-ink-muted mb-1">From</label>
                            <input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="px-3 py-2 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold uppercase tracking-wider text-ink-muted mb-1">To</label>
                            <input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="px-3 py-2 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={apply}
                            className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Opening</div>
                        <div className="mt-2 text-xl font-bold tabular-nums">{formatCurrency(statement.opening_balance, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Charges</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">+ {formatCurrency(statement.total_charges, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Closing</div>
                        <div className={`mt-2 text-xl font-bold tabular-nums ${closingClass}`}>{formatCurrency(statement.closing_balance, base_currency)}</div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm">
                        <h3 className="text-sm font-display font-semibold text-ink uppercase tracking-wider">Activity</h3>
                        <p className="text-xs text-ink-muted mt-0.5">Chronological list of bills, payments and credits</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Date</th>
                                    <th className="px-6 py-3">Reference / Description</th>
                                    <th className="px-6 py-3 text-right">Charge</th>
                                    <th className="px-6 py-3 text-right">Payment</th>
                                    <th className="px-6 py-3 text-right">Credit</th>
                                    <th className="px-6 py-3 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {(statement.activity || []).length > 0 ? statement.activity.map((event, idx) => (
                                    <tr key={idx} className="hover:bg-cream/30">
                                        <td className="px-6 py-3 text-xs text-ink">{formatDate(event.date)}</td>
                                        <td className="px-6 py-3">
                                            <span className="text-sm font-semibold text-ink">{event.reference}</span>
                                            <p className="text-xs text-ink-muted mt-0.5">{event.description}</p>
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-sm">
                                            {event.charge > 0 ? formatCurrency(event.charge, base_currency) : <span className="text-ink-muted">—</span>}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-sm text-emerald-600">
                                            {event.payment > 0 ? formatCurrency(event.payment, base_currency) : <span className="text-ink-muted">—</span>}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-sm text-emerald-600">
                                            {event.credit > 0 ? formatCurrency(event.credit, base_currency) : <span className="text-ink-muted">—</span>}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums text-sm font-semibold">
                                            {formatCurrency(event.running_balance, base_currency)}
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-sm text-ink-muted italic">
                                            No activity in this period.
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
