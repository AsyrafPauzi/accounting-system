import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { alertUpgrade } from '@/utils/swal';
import ReportPeriodChips from '@/Components/ReportPeriodChips';


const Icons = {
    Scale: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    Briefcase: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>,
    Banknotes: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function AccountTable({ title, accounts, total, emptyMessage, ledgerUrl, amountClass = 'text-ink' }) {
    return (
        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">{title}</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                            <th className="px-6 py-4">Account</th>
                            <th className="px-6 py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {accounts.length > 0 ? (
                            accounts.map((acc) => (
                                <tr key={acc.code} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                    <td className="px-6 py-4">
                                        <Link href={ledgerUrl(acc.code)} className="group inline-block">
                                            <span className="font-mono text-ink text-xs group-hover:text-terracotta">{acc.code}</span>
                                            <span className="block font-medium text-ink group-hover:text-terracotta">{acc.name}</span>
                                        </Link>
                                    </td>
                                    <td className={`px-6 py-4 text-right font-mono tabular-nums font-semibold ${amountClass}`}>
                                        RM {formatMoney(acc.amount)}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={2} className="px-6 py-8 text-center text-ink-muted text-sm">
                                    {emptyMessage}
                                </td>
                            </tr>
                        )}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-border-warm bg-cream/80 font-semibold">
                            <td className="px-6 py-4">Total</td>
                            <td className={`px-6 py-4 text-right font-mono tabular-nums ${amountClass}`}>RM {formatMoney(total)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

export default function BalanceSheet({ auth, asset_accounts = [], liability_accounts = [], equity_accounts = [], total_assets = 0, total_liabilities = 0, total_equity = 0, total_liabilities_and_equity = 0, balanced = false, filters = {} }) {
    const { flash } = usePage().props;
    const { preset = 'custom', as_at_date = '' } = filters;
    const ledgerUrl = (code) => route('general-ledger.report', {
        account_code: code,
        date_to: filters.as_at_date,
        from: 'bs',
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Balance Sheet</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">
                            Real-time report of Assets vs Liabilities &amp; Equity as at a date.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Balance Sheet" />


            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 border-b border-border-warm bg-cream/50">
                        <div className="flex flex-wrap items-end gap-3">
                            <ReportPeriodChips
                                action={route('balance-sheet.index')}
                                preset={preset}
                                mode="as_of"
                                asOfKey="as_at_date"
                                asOf={as_at_date}
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <a
                                    href={`${route('balance-sheet.export.csv')}?${new URLSearchParams({ preset, as_at_date: as_at_date || '' })}`}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all active:scale-95"
                                >
                                    <Icons.ArrowDownTray /> Download CSV
                                </a>
                                <a
                                    href={auth.planPermissions['reports.export.full'] ? `${route('balance-sheet.export.pdf')}?${new URLSearchParams({ preset, as_at_date: as_at_date || '' })}` : '#'}
                                    onClick={(e) => {
                                        if (!auth.planPermissions['reports.export.full']) {
                                            e.preventDefault();
                                            alertUpgrade('Professional PDF reports are available on the Corporate plan.');
                                        }
                                    }}
                                    className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all active:scale-95 ${
                                        auth.planPermissions['reports.export.full']
                                            ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                            : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                                    }`}
                                >
                                    <Icons.DocumentArrowDown /> Download PDF
                                    {!auth.planPermissions['reports.export.full'] && (
                                        <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                                    )}
                                </a>
                            </div>
                        </div>
                        <p className="text-ink-muted text-[10px] font-medium mt-2 ml-1">Shows balances at end of this day.</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total assets</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Briefcase /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_assets)}</p>
                        <p className="text-xs text-terracotta mt-1">As at date</p>
                    </div>
                    <div className="relative overflow-hidden bg-mustard text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total liabilities</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Scale /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_liabilities)}</p>
                        <p className="text-xs text-mustard mt-1">As at date</p>
                    </div>
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total equity</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Banknotes /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">RM {formatMoney(total_equity)}</p>
                        <p className="text-xs text-terracotta mt-1">As at date</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <AccountTable
                        title="Assets"
                        accounts={asset_accounts}
                        total={total_assets}
                        emptyMessage="No asset balances as at this date."
                        ledgerUrl={ledgerUrl}
                        amountClass="text-terracotta"
                    />
                    <div className="space-y-6">
                        <AccountTable
                            title="Liabilities"
                            accounts={liability_accounts}
                            total={total_liabilities}
                            emptyMessage="No liability balances as at this date."
                            ledgerUrl={ledgerUrl}
                            amountClass="text-mustard"
                        />
                        <AccountTable
                            title="Equity"
                            accounts={equity_accounts}
                            total={total_equity}
                            emptyMessage="No equity balances as at this date."
                            ledgerUrl={ledgerUrl}
                            amountClass="text-terracotta"
                        />
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border-2 border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span className="text-lg font-display font-medium text-ink">Assets = Liabilities + Equity</span>
                            {balanced && (
                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-forest/10 text-forest">
                                    <Icons.Check /> Balanced
                                </span>
                            )}
                        </div>
                        <div className="flex flex-wrap items-baseline gap-6 text-sm">
                            <span className="font-mono font-semibold text-terracotta">RM {formatMoney(total_assets)}</span>
                            <span className="text-ink-muted">=</span>
                            <span className="font-mono font-semibold text-mustard">RM {formatMoney(total_liabilities)}</span>
                            <span className="text-ink-muted">+</span>
                            <span className="font-mono font-semibold text-terracotta">RM {formatMoney(total_equity)}</span>
                            <span className="text-ink-muted">=</span>
                            <span className="font-mono font-display font-medium text-ink">RM {formatMoney(total_liabilities_and_equity)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
