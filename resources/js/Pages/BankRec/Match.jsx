import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import PrimaryButton from '@/Components/PrimaryButton';

function statusBadge(status) {
    const classes = {
        unmatched: 'bg-surface-alt text-ink-muted',
        suggested: 'bg-amber-100 text-amber-800',
        matched: 'bg-forest/10 text-forest',
        excluded: 'bg-ink/5 text-ink-muted',
    };

    return (
        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize ${classes[status] ?? classes.unmatched}`}>
            {status}
        </span>
    );
}

export default function BankRecMatch({ auth, statement, lines = [], summary, can_match, base_currency = 'MYR' }) {
    const suggest = () => router.post(route('bank-rec.suggest', statement.id));
    const confirm = (lineId, journalItemId) => router.post(route('bank-rec.lines.confirm', lineId), { journal_item_id: journalItemId });
    const reject = (lineId) => router.post(route('bank-rec.lines.reject', lineId));
    const exclude = (lineId) => router.post(route('bank-rec.lines.exclude', lineId));
    const reconcile = () => router.post(route('bank-rec.reconcile', statement.id));

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="font-display text-2xl font-medium text-ink">Match statement</h2>
                        <p className="text-sm text-ink-muted mt-1">{statement.account} · {statement.period_start} → {statement.period_end}</p>
                    </div>
                    <Link href={route('bank-rec.index')} className="text-sm text-ink-muted hover:text-ink">← Back to list</Link>
                </div>
            }
        >
            <Head title="Match bank statement" />

            <div className="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div className="rounded-2xl border border-border-warm bg-white p-4">
                        <p className="text-xs uppercase text-ink-muted">Opening</p>
                        <p className="font-mono text-lg">{formatCurrency(statement.opening_balance, base_currency)}</p>
                    </div>
                    <div className="rounded-2xl border border-border-warm bg-white p-4">
                        <p className="text-xs uppercase text-ink-muted">Statement closing</p>
                        <p className="font-mono text-lg">{formatCurrency(statement.closing_balance, base_currency)}</p>
                    </div>
                    <div className="rounded-2xl border border-border-warm bg-white p-4">
                        <p className="text-xs uppercase text-ink-muted">Book (matched)</p>
                        <p className="font-mono text-lg">{formatCurrency(summary.book_balance, base_currency)}</p>
                    </div>
                    <div className="rounded-2xl border border-border-warm bg-white p-4">
                        <p className="text-xs uppercase text-ink-muted">Difference</p>
                        <p className={`font-mono text-lg ${summary.difference !== 0 ? 'text-terracotta' : 'text-forest'}`}>
                            {formatCurrency(summary.difference, base_currency)}
                        </p>
                    </div>
                </div>

                {can_match && statement.status === 'open' && (
                    <div className="flex flex-wrap gap-3">
                        <PrimaryButton type="button" onClick={suggest}>Suggest matches</PrimaryButton>
                        <button
                            type="button"
                            onClick={reconcile}
                            className="inline-flex items-center px-4 py-2 rounded-xl border border-border-warm text-sm font-medium hover:bg-cream/60"
                        >
                            Mark reconciled
                        </button>
                    </div>
                )}

                <div className="rounded-2xl border border-border-warm bg-white overflow-hidden">
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-alt text-ink-muted uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left">Date</th>
                                <th className="px-4 py-3 text-left">Description</th>
                                <th className="px-4 py-3 text-right">Amount</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Suggestion</th>
                                {can_match && <th className="px-4 py-3 text-right">Actions</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {lines.map((line) => (
                                <tr key={line.id}>
                                    <td className="px-4 py-3 tabular-nums">{line.transaction_date}</td>
                                    <td className="px-4 py-3">
                                        <div>{line.description || '—'}</div>
                                        {line.reference && <div className="text-xs text-ink-muted">{line.reference}</div>}
                                    </td>
                                    <td className={`px-4 py-3 text-right font-mono tabular-nums ${line.amount >= 0 ? 'text-forest' : 'text-terracotta'}`}>
                                        {formatCurrency(line.amount, base_currency)}
                                    </td>
                                    <td className="px-4 py-3">{statusBadge(line.match_status)}</td>
                                    <td className="px-4 py-3 text-ink-muted">
                                        {line.suggestion ? (
                                            <div>
                                                <div>{line.suggestion.journal_description || 'Journal entry'}</div>
                                                <div className="text-xs">{line.suggestion.journal_date}{line.suggestion.reference_number ? ` · ${line.suggestion.reference_number}` : ''}</div>
                                                {line.match_confidence != null && (
                                                    <div className="text-xs">Confidence {Math.round(line.match_confidence * 100)}%</div>
                                                )}
                                            </div>
                                        ) : '—'}
                                    </td>
                                    {can_match && (
                                        <td className="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                            {line.match_status === 'suggested' && (
                                                <>
                                                    <button type="button" className="text-forest hover:underline" onClick={() => confirm(line.id, line.matched_journal_item_id)}>Confirm</button>
                                                    <button type="button" className="text-ink-muted hover:underline" onClick={() => reject(line.id)}>Reject</button>
                                                </>
                                            )}
                                            {['unmatched', 'suggested'].includes(line.match_status) && (
                                                <button type="button" className="text-ink-muted hover:underline" onClick={() => exclude(line.id)}>Exclude</button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
