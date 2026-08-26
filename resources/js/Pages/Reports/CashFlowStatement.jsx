import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';
import ReportPeriodChips from '@/Components/ReportPeriodChips';

const Icons = {
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function SectionBlock({ title, children, totalLabel, total }) {
    return (
        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">{title}</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <tbody>
                        {children}
                        <tr className="border-t border-border-warm bg-cream/50 font-semibold">
                            <td className="px-6 py-4 text-ink">{totalLabel}</td>
                            <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">RM {formatMoney(total)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function LineRow({ label, amount, indent = false }) {
    return (
        <tr className="border-b border-border-warm last:border-0">
            <td className={`px-6 py-3 text-ink ${indent ? 'pl-10' : ''}`}>{label}</td>
            <td className="px-6 py-3 text-right font-mono tabular-nums text-ink">RM {formatMoney(amount)}</td>
        </tr>
    );
}

export default function CashFlowStatement({
    auth,
    net_profit = 0,
    operating_adjustments = [],
    net_cash_operating = 0,
    investing_lines = [],
    net_cash_investing = 0,
    financing_lines = [],
    net_cash_financing = 0,
    net_change_in_cash = 0,
    opening_cash = 0,
    closing_cash = 0,
    actual_change_in_cash = 0,
    reconciled = true,
    filters = {},
}) {
    const { preset = 'custom', date_from = '', date_to = '' } = filters;
    const exportParams = new URLSearchParams({ preset, date_from: date_from || '', date_to: date_to || '' });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Cash Flow Statement</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            IAS 7 indirect method — reconcile profit to cash movement.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Cash Flow Statement" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-end gap-3 bg-cream/50">
                        <ReportPeriodChips
                            action={route('cash-flow-statement.index')}
                            preset={preset}
                            dateFrom={date_from}
                            dateTo={date_to}
                        />
                        <div className="flex flex-wrap items-center gap-3 ml-auto">
                            <a
                                href={`${route('cash-flow-statement.export.csv')}?${exportParams}`}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                            >
                                <Icons.ArrowDownTray /> Download CSV
                            </a>
                            <a
                                href={auth.planPermissions?.['reports.export.full'] ? `${route('cash-flow-statement.export.pdf')}?${exportParams}` : '#'}
                                onClick={(e) => {
                                    if (!auth.planPermissions?.['reports.export.full']) {
                                        e.preventDefault();
                                        alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                    }
                                }}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-forest hover:bg-forest/90 transition-colors"
                            >
                                <Icons.DocumentArrowDown /> Download PDF
                            </a>
                        </div>
                    </div>
                </div>

                <SectionBlock title="Operating activities" totalLabel="Net cash from operating activities" total={net_cash_operating}>
                    <LineRow label="Net profit for the period" amount={net_profit} />
                    {operating_adjustments.map((line) => (
                        <LineRow
                            key={line.code}
                            label={`Change in ${line.name}`}
                            amount={line.amount}
                            indent
                        />
                    ))}
                </SectionBlock>

                <SectionBlock title="Investing activities" totalLabel="Net cash from investing activities" total={net_cash_investing}>
                    {investing_lines.length === 0 ? (
                        <LineRow label="No investing cash flows in this period" amount={0} />
                    ) : investing_lines.map((line) => (
                        <LineRow key={line.code} label={`Change in ${line.name}`} amount={line.amount} />
                    ))}
                </SectionBlock>

                <SectionBlock title="Financing activities" totalLabel="Net cash from financing activities" total={net_cash_financing}>
                    {financing_lines.length === 0 ? (
                        <LineRow label="No financing cash flows in this period" amount={0} />
                    ) : financing_lines.map((line) => (
                        <LineRow key={line.code} label={`Change in ${line.name}`} amount={line.amount} />
                    ))}
                </SectionBlock>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Cash reconciliation</h3>
                    </div>
                    <table className="w-full text-sm">
                        <tbody>
                            <LineRow label="Net increase/(decrease) in cash" amount={net_change_in_cash} />
                            <LineRow label="Cash at beginning of period" amount={opening_cash} />
                            <LineRow label="Cash at end of period" amount={closing_cash} />
                            <tr className="border-t border-border-warm bg-cream/50">
                                <td className="px-6 py-4 text-ink-muted text-xs">
                                    Actual bank/cash movement: RM {formatMoney(actual_change_in_cash)}
                                    {!reconciled && ' — rounding difference; review working-capital classifications.'}
                                </td>
                                <td />
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
