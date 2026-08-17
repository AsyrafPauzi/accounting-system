import PracticeLayout from '@/Layouts/PracticeLayout';
import { Head, Link } from '@inertiajs/react';

const formatRMExact = (n) => {
    const num = Number(n) || 0;
    return 'RM ' + num.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const BUCKETS = [
    { key: 'current', label: 'Current' },
    { key: 'days_1_30', label: '1–30' },
    { key: 'days_31_60', label: '31–60' },
    { key: 'days_61_90', label: '61–90' },
    { key: 'days_90_plus', label: '90+' },
];

export default function ArAging({ firm, aggregates = {}, clients = [] }) {
    const aging = aggregates.ar_aging ?? {};
    const rows = [...clients].sort((a, b) => (b.ar_outstanding || 0) - (a.ar_outstanding || 0));

    return (
        <PracticeLayout
            header={
                <div>
                    <h1 className="text-2xl font-display font-medium text-ink">AR aging · {firm?.name}</h1>
                    <p className="text-sm text-ink-muted mt-1">Outstanding receivables across every client company.</p>
                </div>
            }
        >
            <Head title="Practice AR aging" />
            <div className="space-y-6">
                <div className="grid grid-cols-2 lg:grid-cols-6 gap-3">
                    <div className="bg-surface rounded-2xl border border-border-warm p-4">
                        <div className="text-[10px] uppercase tracking-widest text-ink-muted">Total AR</div>
                        <div className="mt-1 font-semibold tabular-nums">{formatRMExact(aggregates.total_ar_outstanding)}</div>
                    </div>
                    {BUCKETS.map((b) => (
                        <div key={b.key} className="bg-surface rounded-2xl border border-border-warm p-4">
                            <div className="text-[10px] uppercase tracking-widest text-ink-muted">{b.label}</div>
                            <div className="mt-1 font-semibold tabular-nums">{formatRMExact(aging[b.key] || 0)}</div>
                        </div>
                    ))}
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/80 text-[10px] uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Client</th>
                                <th className="px-3 py-3 text-right">Outstanding</th>
                                {BUCKETS.map((b) => <th key={b.key} className="px-3 py-3 text-right">{b.label}</th>)}
                                <th className="px-3 py-3 text-right">Overdue #</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((c) => (
                                <tr key={c.tenant_id} className="border-t border-border-warm">
                                    <td className="px-4 py-3 font-semibold">{c.name}</td>
                                    <td className="px-3 py-3 text-right font-mono">{formatRMExact(c.ar_outstanding)}</td>
                                    {BUCKETS.map((b) => (
                                        <td key={b.key} className="px-3 py-3 text-right font-mono text-ink-muted">{formatRMExact(c.ar_aging?.[b.key] || 0)}</td>
                                    ))}
                                    <td className="px-3 py-3 text-right">{c.overdue_count || 0}</td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center text-ink-muted">No client companies yet.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Link href={route('practice.dashboard')} className="text-sm text-terracotta">← Practice dashboard</Link>
            </div>
        </PracticeLayout>
    );
}
