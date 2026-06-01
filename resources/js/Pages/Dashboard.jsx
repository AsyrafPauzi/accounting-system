import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    Currency: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ArrowTrendingUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 011.414-1.414l2.25-2.25M3 75h13.5A2.25 2.25 0 0019 72.75V60m-12-12V60m12 0V72.75" /></svg>,
    ArrowTrendingDown: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7-7-7M12 3v18" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    BuildingOffice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    ShoppingCart: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>,
    ReceiptRefund: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" /></svg>,
};

function fmt(n, opts = {}) {
    const { currency = false } = opts;
    const num = Number(n) || 0;
    if (currency) return 'RM ' + num.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return num.toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export default function Dashboard({ auth, stats = {} }) {
    const customers = stats.customers || { total: 0, active: 0, outstanding: 0 };
    const invoices = stats.invoices || { total_outstanding: 0, total_invoiced: 0, total_collected: 0, overdue_count: 0, unpaid: 0, partially_paid: 0, paid: 0, draft: 0 };
    const creditNotes = stats.credit_notes || { count: 0, value: 0 };
    const suppliers = stats.suppliers || { total: 0, active: 0 };
    const bills = stats.bills || { total_ap: 0, unpaid_count: 0, overdue_count: 0, total_billed: 0 };
    const period = stats.period || { sales_this_month: 0, expenses_this_month: 0, net_this_month: 0 };

    const collectionRate = invoices.total_invoiced > 0
        ? Math.round((invoices.total_collected / invoices.total_invoiced) * 100)
        : 0;
    const netMonth = period.net_this_month ?? 0;

    const planPermissions = auth?.planPermissions ?? {};
    const isBasic = planPermissions['dashboard.basic'];
    const isStandard = planPermissions['dashboard.standard'];
    const isAdvanced = planPermissions['dashboard.advanced'] || auth.user.role_name === 'super-admin';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Dashboard</p>
                    <h1 className="font-display text-2xl sm:text-3xl font-medium text-ink tracking-tight">
                        Welcome back, {auth.user?.name?.split(' ')[0] || 'there'}.
                    </h1>
                    <p className="text-ink-muted text-sm">
                        {isBasic ? 'Your books at a glance.' : 'Today’s books, today’s decisions.'}
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6 sm:space-y-8 pb-8 min-w-0 w-full">
                {/* Top 4 KPIs — Always shown for all levels */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 min-w-0">
                    <Link
                        href={planPermissions['reports.aged-reports'] ? route('aged-receivables.index') : route('invoices.index')}
                        className="rounded-2xl bg-terracotta text-white p-4 sm:p-5 shadow-lg  flex flex-col min-h-[100px] sm:min-h-[112px] min-w-0 active:opacity-90"
                    >
                        <span className="text-[10px] sm:text-xs font-semibold uppercase tracking-wider opacity-90">Receivables</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold font-mono tabular-nums truncate min-w-0" title={fmt(invoices.total_outstanding, { currency: true })}>
                            {fmt(invoices.total_outstanding, { currency: true })}
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs opacity-90">
                            {invoices.overdue_count > 0 ? (
                                <span className="text-mustard">{invoices.overdue_count} overdue</span>
                            ) : (
                                'To collect'
                            )}
                        </p>
                    </Link>

                    <Link
                        href={planPermissions['reports.aged-reports'] ? route('accounts-payable.index') : route('bills.index')}
                        className="rounded-2xl bg-surface border border-border-warm p-4 sm:p-5 shadow-sm flex flex-col min-h-[100px] sm:min-h-[112px] hover:border-border-warm hover:shadow-md transition-all min-w-0 active:opacity-90"
                    >
                        <span className="text-[10px] sm:text-xs font-semibold text-ink-muted uppercase tracking-wider">Payables</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-terracotta font-mono tabular-nums truncate min-w-0">
                            {fmt(bills.total_ap, { currency: true })}
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs text-ink-muted">
                            {bills.overdue_count > 0 ? (
                                <span className="text-terracotta">{bills.overdue_count} overdue</span>
                            ) : (
                                'To pay'
                            )}
                        </p>
                    </Link>

                    <div className="rounded-2xl bg-surface border border-border-warm p-4 sm:p-5 shadow-sm flex flex-col min-h-[100px] sm:min-h-[112px]">
                        <span className="text-[10px] sm:text-xs font-semibold text-ink-muted uppercase tracking-wider">This month</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold font-mono tabular-nums">
                            <span className={netMonth >= 0 ? 'text-forest' : 'text-terracotta'}>
                                {fmt(netMonth, { currency: true })}
                            </span>
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs text-ink-muted">
                            Sales − expenses
                        </p>
                    </div>

                    <div className="rounded-2xl bg-surface border border-border-warm p-4 sm:p-5 shadow-sm flex flex-col min-h-[100px] sm:min-h-[112px]">
                        <span className="text-[10px] sm:text-xs font-semibold text-ink-muted uppercase tracking-wider">Collection rate</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-forest tabular-nums">
                            {collectionRate}%
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs text-ink-muted">
                            Invoiced → collected
                        </p>
                    </div>
                </div>

                {/* Revenue & Payables — Shown for Standard (SME) and Advanced (Corporate) */}
                {(isStandard || isAdvanced) && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        <section className="rounded-2xl border border-border-warm bg-surface shadow-sm overflow-hidden">
                            <div className="px-4 sm:px-6 py-4 border-b border-border-warm flex items-center justify-between">
                                <h2 className="text-sm font-display font-medium text-ink uppercase tracking-wider">Revenue Analysis</h2>
                                <Link href={route('invoices.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta inline-flex items-center gap-1">
                                    Invoices <Icons.ChevronRight />
                                </Link>
                            </div>
                            <div className="p-4 sm:p-6">
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Invoiced</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-ink mt-0.5 whitespace-nowrap" title={fmt(invoices.total_invoiced, { currency: true })}>{fmt(invoices.total_invoiced, { currency: true })}</p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Collected</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-forest dark:text-forest-light mt-0.5 whitespace-nowrap" title={fmt(invoices.total_collected, { currency: true })}>{fmt(invoices.total_collected, { currency: true })}</p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Outstanding</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-terracotta mt-0.5 whitespace-nowrap" title={fmt(invoices.total_outstanding, { currency: true })}>{fmt(invoices.total_outstanding, { currency: true })}</p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Overdue</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-ink mt-0.5 whitespace-nowrap">{invoices.overdue_count}</p>
                                    </div>
                                </div>
                                {planPermissions['reports.aged-reports'] && (
                                    <Link
                                        href={route('aged-receivables.index')}
                                        className="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-terracotta hover:text-terracotta"
                                    >
                                        Aged receivables <Icons.ChevronRight />
                                    </Link>
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-border-warm bg-surface shadow-sm overflow-hidden">
                            <div className="px-4 sm:px-6 py-4 border-b border-border-warm flex items-center justify-between">
                                <h2 className="text-sm font-display font-medium text-ink uppercase tracking-wider">Payables Analysis</h2>
                                <Link href={route('bills.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta inline-flex items-center gap-1">
                                    Bills <Icons.ChevronRight />
                                </Link>
                            </div>
                            <div className="p-4 sm:p-6">
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Suppliers</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-ink mt-0.5 whitespace-nowrap">
                                            {suppliers.total} <span className="text-xs font-normal text-ink-muted">({suppliers.active} active)</span>
                                        </p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Outstanding</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-terracotta mt-0.5 whitespace-nowrap" title={fmt(bills.total_ap, { currency: true })}>{fmt(bills.total_ap, { currency: true })}</p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Unpaid bills</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-ink mt-0.5 whitespace-nowrap">{bills.unpaid_count}</p>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-eyebrow font-semibold text-ink-muted uppercase">Overdue</p>
                                        <p className="text-sm sm:text-base font-mono font-tabular font-semibold text-ink mt-0.5 whitespace-nowrap">{bills.overdue_count}</p>
                                    </div>
                                </div>
                                {planPermissions['reports.aged-reports'] && (
                                    <Link
                                        href={route('accounts-payable.index')}
                                        className="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-terracotta hover:text-terracotta"
                                    >
                                        Accounts payable <Icons.ChevronRight />
                                    </Link>
                                )}
                            </div>
                        </section>
                    </div>
                )}
                
                {/* Compliance Analysis — Advanced (Corporate) Only */}
                {isAdvanced && stats.audit && (
                    <section className="rounded-2xl border border-border-warm bg-surface shadow-sm overflow-hidden">
                        <div className="px-4 sm:px-6 py-4 border-b border-border-warm bg-cream/30 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <div className="p-1 bg-surface-alt rounded text-terracotta">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <h2 className="text-sm font-display font-medium text-ink uppercase tracking-wider">Compliance Status</h2>
                            </div>
                            <Link href={route('audit.index')} className="text-xs font-bold text-terracotta hover:text-terracotta flex items-center gap-1">
                                Review Compliance <Icons.ChevronRight />
                            </Link>
                        </div>
                        <div className="p-4 sm:p-6">
                            <div className="flex flex-col md:flex-row items-center gap-8">
                                <div className="relative w-20 h-20 flex-shrink-0">
                                    <svg className="w-full h-full -rotate-90" viewBox="0 0 36 36">
                                        <circle cx="18" cy="18" r="16" fill="none" className="stroke-slate-100" strokeWidth="4" />
                                        <circle 
                                            cx="18" cy="18" r="16" fill="none" 
                                            className="stroke-indigo-600 transition-all duration-1000" 
                                            strokeWidth="4" 
                                            strokeDasharray={`${Math.max(5, ((stats.audit.verified + stats.audit.flagged) / (stats.audit.total || 1)) * 100)}, 100`} 
                                            strokeLinecap="round" 
                                        />
                                    </svg>
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <span className="text-sm font-display font-semibold text-ink">
                                            {Math.round(((stats.audit.verified + stats.audit.flagged) / (stats.audit.total || 1)) * 100)}%
                                        </span>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
                                    <div className="p-3 rounded-xl border border-border-warm bg-cream/50">
                                        <p className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest mb-1">Total Items</p>
                                        <p className="text-lg font-display font-medium text-ink font-mono">{stats.audit.total}</p>
                                    </div>
                                    <div className="p-3 rounded-xl border border-forest/30 bg-forest/10/30">
                                        <p className="text-[10px] font-bold text-forest/70 uppercase tracking-widest mb-1">Verified</p>
                                        <p className="text-lg font-bold text-forest font-mono">{stats.audit.verified}</p>
                                    </div>
                                    <div className="p-3 rounded-xl border border-terracotta/30 bg-terracotta/10/30">
                                        <p className="text-[10px] font-bold text-terracotta/70 uppercase tracking-widest mb-1">Unaudited</p>
                                        <p className="text-lg font-bold text-terracotta font-mono">{stats.audit.unaudited}</p>
                                    </div>
                                    <div className="p-3 rounded-xl border border-mustard/40 bg-mustard/15/30">
                                        <p className="text-[10px] font-bold text-mustard/70 uppercase tracking-widest mb-1">Flagged</p>
                                        <p className="text-lg font-bold text-mustard font-mono">{stats.audit.flagged}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {/* Quick Links Row — Standard & Advanced */}
                {(isStandard || isAdvanced) && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                        {planPermissions['reports.cashflow'] && (
                            <Link
                                href={route('cashflow-summary.index')}
                                className="rounded-2xl border border-border-warm bg-surface p-4 sm:p-5 shadow-sm hover:border-border-warm hover:shadow-md transition-all flex items-center gap-4"
                            >
                                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-mustard/15 text-mustard flex items-center justify-center">
                                    <Icons.ChartBar />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-display font-medium text-ink">Cashflow</p>
                                    <p className="text-xs text-ink-muted mt-0.5">Sales vs expenses</p>
                                </div>
                                <Icons.ChevronRight className="flex-shrink-0 text-ink-muted w-5 h-5" />
                            </Link>
                        )}

                        {planPermissions['reports.view'] && (
                            <Link
                                href={route('reports.index')}
                                className="rounded-2xl border border-border-warm bg-surface p-4 sm:p-5 shadow-sm hover:border-border-warm hover:shadow-md transition-all flex items-center gap-4"
                            >
                                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-surface-alt text-terracotta flex items-center justify-center">
                                    <Icons.Document />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-display font-medium text-ink">Reports Hub</p>
                                    <p className="text-xs text-ink-muted mt-0.5">P&L, Sales & more</p>
                                </div>
                                <Icons.ChevronRight className="flex-shrink-0 text-ink-muted w-5 h-5" />
                            </Link>
                        )}

                        {planPermissions['credit-notes.view'] && (
                            <Link
                                href={route('credit-notes.index')}
                                className="rounded-2xl border border-border-warm bg-surface p-4 sm:p-5 shadow-sm hover:border-border-warm hover:shadow-md transition-all flex items-center gap-4"
                            >
                                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-surface-alt text-ink flex items-center justify-center">
                                    <Icons.ReceiptRefund />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-display font-medium text-ink">Credit notes</p>
                                    <p className="text-xs text-ink-muted mt-0.5">{creditNotes.count} issued</p>
                                </div>
                                <Icons.ChevronRight className="flex-shrink-0 text-ink-muted w-5 h-5" />
                            </Link>
                        )}

                        {/* Corporate Feature: Audit Logs */}
                        {isAdvanced && (auth.permissions.includes('audit-logs.view') || auth.user.role_name === 'super-admin') && (
                            <Link 
                                href={route('audit-logs.index')}
                                className="rounded-2xl border border-border-warm bg-surface p-4 sm:p-5 shadow-sm flex items-center gap-4 group hover:border-border-warm hover:shadow-md transition-all active:scale-[0.98] duration-200"
                            >
                                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-forest/10 text-forest flex items-center justify-center group-hover:bg-forest group-hover:text-white transition-all duration-300">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-display font-medium text-ink">Audit Logs</p>
                                    <p className="text-xs text-ink-muted mt-0.5">Track all system changes</p>
                                </div>
                                <Icons.ChevronRight className="text-ink-muted group-hover:text-terracotta transition-colors flex-shrink-0 w-5 h-5" />
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
