import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import PrimaryButton from '@/Components/PrimaryButton';

export default function BankRecIndex({ auth, statements, can_match, base_currency = 'MYR' }) {
    const rows = statements?.data ?? [];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="font-display text-2xl font-medium text-ink">Bank reconciliation</h2>
                        <p className="text-sm text-ink-muted mt-1">Import statements and match them to posted bank transactions.</p>
                    </div>
                    {can_match && (
                        <Link href={route('bank-rec.import')}>
                            <PrimaryButton>Import statement</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Bank reconciliation" />

            <div className="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="rounded-2xl border border-border-warm bg-white overflow-hidden">
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-alt text-ink-muted uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left">Account</th>
                                <th className="px-4 py-3 text-left">Period</th>
                                <th className="px-4 py-3 text-right">Closing</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Progress</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-ink-muted">
                                        No statements imported yet.
                                    </td>
                                </tr>
                            )}
                            {rows.map((row) => (
                                <tr key={row.id} className="hover:bg-cream/40">
                                    <td className="px-4 py-3">{row.account}</td>
                                    <td className="px-4 py-3 tabular-nums">{row.period_start} → {row.period_end}</td>
                                    <td className="px-4 py-3 text-right font-mono tabular-nums">{formatCurrency(row.closing_balance, base_currency)}</td>
                                    <td className="px-4 py-3 capitalize">{row.status}</td>
                                    <td className="px-4 py-3">
                                        {row.matched_lines_count}/{row.line_count} matched
                                        {row.unmatched_lines_count > 0 && (
                                            <span className="text-terracotta ml-1">({row.unmatched_lines_count} open)</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('bank-rec.match', row.id)} className="text-terracotta hover:underline font-medium">
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
