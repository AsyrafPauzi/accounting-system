import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    BookOpen: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Show({ auth, entry = {}, items = [], accountsMap = {} }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Journal entry</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            {entry.date} — {entry.description}
                        </p>
                    </div>
                    <Link
                        href={route('general-ledger.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                    >
                        Back to General Ledger
                    </Link>
                </div>
            }
        >
            <Head title={`Ledger entry ${entry.date}`} />

            <div className="space-y-6">
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <h3 className="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Summary</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Date</p>
                            <p className="font-mono text-ink">{entry.date}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Reference</p>
                            <p className="text-ink">{entry.reference_type}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Balanced</p>
                            <p className={entry.balanced ? 'text-forest font-semibold' : 'text-ink'}>
                                {entry.balanced ? (
                                    <span className="inline-flex items-center gap-1"><Icons.Check /> Yes</span>
                                ) : (
                                    'No'
                                )}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Source document</p>
                            {entry.source_route ? (
                                <a href={entry.source_route} className="text-terracotta hover:text-terracotta font-semibold text-sm">
                                    {entry.source_label || entry.reference_type}
                                </a>
                            ) : (
                                <span className="text-ink-muted">—</span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Line items</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Account code</th>
                                    <th className="px-6 py-4">Account name</th>
                                    <th className="px-6 py-4 text-right">Debit</th>
                                    <th className="px-6 py-4 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((line) => (
                                    <tr key={line.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                        <td className="px-6 py-4 font-mono font-semibold text-ink">
                                            <Link
                                                href={route('general-ledger.report', { account_code: line.account_code, from: 'gl' })}
                                                className="text-terracotta hover:text-terracotta"
                                            >
                                                {line.account_code}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-ink">{accountsMap[line.account_code] || '—'}</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                            {line.debit > 0 ? `RM ${formatMoney(line.debit)}` : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                            {line.credit > 0 ? `RM ${formatMoney(line.credit)}` : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-border-warm bg-cream/80 font-semibold">
                                    <td className="px-6 py-4" colSpan={2}>Total</td>
                                    <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">RM {formatMoney(entry.total_debit)}</td>
                                    <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">RM {formatMoney(entry.total_credit)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
