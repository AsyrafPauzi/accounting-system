import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function AccountingPeriods({ auth, periods = [], canLock, canReopen }) {
    const closePeriod = (id) => {
        if (! canLock || ! window.confirm('Close this period? Posting and payments into it will be blocked.')) return;
        router.post(route('settings.accounting-periods.close', id));
    };

    const reopenPeriod = (id) => {
        if (! canReopen || ! window.confirm('Reopen this period? Posting and payments will be allowed again.')) return;
        router.post(route('settings.accounting-periods.reopen', id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Settings</p>
                    <h1 className="font-display text-2xl font-medium text-ink">Accounting periods</h1>
                    <p className="text-sm text-ink-muted mt-1">
                        Close a month when books are reviewed. Reopen only when corrections are needed.
                    </p>
                </div>
            }
        >
            <Head title="Accounting periods" />

            <div className="max-w-4xl space-y-3">
                {periods.map((period) => (
                    <div
                        key={period.id}
                        className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-border-warm bg-white p-4"
                    >
                        <div>
                            <div className="font-medium text-ink">{period.label}</div>
                            <div className="text-sm text-ink-muted">
                                {period.start_date} → {period.end_date}
                            </div>
                            <div className={`text-xs font-semibold uppercase mt-1 ${period.status === 'closed' ? 'text-terracotta' : 'text-teal'}`}>
                                {period.status}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {period.status === 'open' && canLock && (
                                <button
                                    type="button"
                                    onClick={() => closePeriod(period.id)}
                                    className="px-4 py-2 rounded-xl text-sm font-semibold bg-terracotta text-white hover:bg-terracotta-dark"
                                >
                                    Close period
                                </button>
                            )}
                            {period.status === 'closed' && canReopen && (
                                <button
                                    type="button"
                                    onClick={() => reopenPeriod(period.id)}
                                    className="px-4 py-2 rounded-xl text-sm font-semibold border border-border-warm text-ink hover:bg-surface-alt"
                                >
                                    Reopen
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
