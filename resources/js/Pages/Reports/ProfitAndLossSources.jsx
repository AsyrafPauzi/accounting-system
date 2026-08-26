import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ProfitAndLossSources({ auth, account, sources = [], filters = {} }) {
    const { date_from = '', date_to = '', basis = 'accrual' } = filters;
    const plUrl = route('profit-and-loss.index', {
        preset: filters.preset,
        date_from,
        date_to,
        basis,
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <Link href={plUrl} className="text-sm text-terracotta hover:underline mb-2 inline-block">← Back to P&amp;L</Link>
                    <h2 className="text-2xl font-display font-medium text-ink tracking-tight">Source documents</h2>
                    <p className="text-ink-muted text-sm mt-1">
                        {account.code} — {account.name} · {date_from} to {date_to}
                    </p>
                </div>
            }
        >
            <Head title={`P&L sources — ${account.code}`} />

            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                <th className="px-6 py-4">Date</th>
                                <th className="px-6 py-4">Source</th>
                                <th className="px-6 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sources.length > 0 ? sources.map((row) => (
                                <tr key={row.entry_id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                    <td className="px-6 py-4 font-mono text-xs text-ink-muted">{row.date}</td>
                                    <td className="px-6 py-4">
                                        <Link href={row.source_route} className="font-medium text-ink hover:text-terracotta">
                                            {row.label}
                                        </Link>
                                        {row.reference_type && (
                                            <p className="text-[10px] text-ink-muted mt-0.5 uppercase tracking-wide">{row.reference_type}</p>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right font-mono tabular-nums font-semibold text-ink">
                                        RM {formatMoney(row.amount)}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={3} className="px-6 py-16 text-center text-ink-muted">
                                        No source documents found for this account in the selected period.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
