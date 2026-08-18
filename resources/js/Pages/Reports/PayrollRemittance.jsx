import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ReportPeriodChips from '@/Components/ReportPeriodChips';
import { formatCurrency } from '@/utils/currency';
import { Head, Link } from '@inertiajs/react';

function money(value) {
    return formatCurrency(value, 'MYR');
}

function formatAsOf(iso) {
    if (! iso) return '';

    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function PayrollRemittance({
    auth,
    remittances = [],
    total = 0,
    filters = {},
}) {
    const asOf = filters.as_of_date || '';
    const preset = filters.preset || 'custom';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-terracotta">Reports</p>
                        <h1 className="font-display text-xl lg:text-2xl font-medium text-ink tracking-tight">Payroll remittance due</h1>
                    </div>
                    <p className="mt-1 text-sm text-ink-muted">
                        Statutory payables outstanding as of {formatAsOf(asOf) || 'today'}.
                    </p>
                </div>
            }
        >
            <Head title="Payroll Remittance Due" />

            <div className="space-y-4 sm:space-y-5">
                <div className="rounded-2xl border border-border-warm bg-surface p-4 shadow-sm">
                    <ReportPeriodChips
                        action={route('reports.payroll-remittance')}
                        preset={preset}
                        mode="as_of"
                        asOfKey="as_of_date"
                        asOf={asOf}
                    />
                </div>

                <div className="rounded-2xl border border-border-warm bg-mustard/10 px-4 py-3 text-sm text-ink">
                    Statutory still in payables — EPF, SOCSO, EIS, PCB, HRD. This is the unpaid balance, not this month’s run.
                </div>

                <div className="overflow-hidden rounded-2xl border border-border-warm bg-surface shadow-sm">
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border-warm bg-cream/80 text-[10px] font-medium uppercase tracking-widest text-ink-muted">
                                    <th className="w-32 px-6 py-3">Code</th>
                                    <th className="px-6 py-3">Payable</th>
                                    <th className="px-6 py-3 text-right">Balance due</th>
                                </tr>
                            </thead>
                            <tbody>
                                {remittances.length > 0 ? remittances.map((row) => (
                                    <tr key={row.code} className="border-b border-border-warm last:border-0 hover:bg-cream/60">
                                        <td className="px-6 py-4 font-mono font-semibold">
                                            <Link href={row.ledger_url} className="text-ink hover:text-terracotta">
                                                {row.code}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4">
                                            <Link href={row.ledger_url} className="font-medium text-ink hover:text-terracotta">
                                                {row.name}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono font-semibold tabular-nums text-ink">
                                            {money(row.credit_balance)}
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-14 text-center">
                                            <p className="font-semibold text-ink">No statutory remittance is due.</p>
                                            <p className="mt-1 text-sm text-ink-muted">Cleared and zero-balance payables are hidden.</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-border-warm bg-cream/80">
                                    <td colSpan={2} className="px-6 py-4 text-right text-xs font-semibold uppercase tracking-widest text-ink">
                                        Total due
                                    </td>
                                    <td className="px-6 py-4 text-right font-mono font-bold tabular-nums text-ink">
                                        {money(total)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div className="divide-y divide-border-warm md:hidden">
                        {remittances.length > 0 ? remittances.map((row) => (
                            <Link key={row.code} href={row.ledger_url} className="flex items-start justify-between gap-4 p-4 hover:bg-cream/60">
                                <div>
                                    <p className="font-mono text-xs font-semibold text-ink">{row.code}</p>
                                    <p className="mt-0.5 font-medium text-ink">{row.name}</p>
                                </div>
                                <p className="shrink-0 font-mono text-sm font-semibold tabular-nums text-ink">
                                    {money(row.credit_balance)}
                                </p>
                            </Link>
                        )) : (
                            <div className="px-4 py-12 text-center">
                                <p className="font-semibold text-ink">No statutory remittance is due.</p>
                                <p className="mt-1 text-sm text-ink-muted">Cleared and zero-balance payables are hidden.</p>
                            </div>
                        )}
                        <div className="flex items-center justify-between gap-4 bg-cream/80 px-4 py-4">
                            <span className="text-xs font-semibold uppercase tracking-widest text-ink">Total due</span>
                            <span className="font-mono text-sm font-bold tabular-nums text-ink">{money(total)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
