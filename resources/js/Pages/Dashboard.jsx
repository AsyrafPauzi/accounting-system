import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';

const Icons = {
    Plus: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v12m6-6H6" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Receipt: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2a4 4 0 014-4h4a4 4 0 014 4v2M3 7h2m0 0h2M5 7v2m0-2V5m9 4a2 2 0 11-4 0 2 2 0 014 0zM7 13H4a1 1 0 00-1 1v6a1 1 0 001 1h3" /></svg>,
    Wallet: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>,
    ShoppingCart: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    CheckCircle: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ChartPie: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /></svg>,
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" /></svg>,
    TrendingUp: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>,
};

function fmt(n, opts = {}) {
    const { currency = false, compact = false } = opts;
    const num = Number(n) || 0;
    if (compact && currency) {
        const abs = Math.abs(num);
        if (abs >= 1_000_000_000) return 'RM ' + (num / 1_000_000_000).toFixed(2) + 'B';
        if (abs >= 1_000_000)     return 'RM ' + (num / 1_000_000).toFixed(2) + 'M';
        if (abs >= 1_000)         return 'RM ' + (num / 1_000).toFixed(1) + 'K';
    }
    if (currency) return 'RM ' + num.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return num.toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function fmtDate(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function greeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
}

const PIE_COLORS = ['#c2410c', '#0f766e', '#a16207', '#7c2d12', '#1e3a8a', '#7e22ce', '#374151'];

// Tooltip used by every chart so currency formatting and theme are consistent.
function ChartTooltip({ active, payload, label, prefix = '' }) {
    if (! active || ! payload?.length) return null;
    return (
        <div className="bg-surface border border-border-warm rounded-xl shadow-lg p-3 text-xs">
            <p className="font-semibold text-ink mb-1.5">{prefix}{label}</p>
            {payload.map((p, idx) => (
                <div key={idx} className="flex items-center justify-between gap-4 py-0.5">
                    <span className="flex items-center gap-1.5 text-ink-muted">
                        <span className="w-2 h-2 rounded-full" style={{ background: p.color }} />
                        {p.name}
                    </span>
                    <span className="font-mono tabular-nums font-semibold" style={{ color: p.color }}>
                        {fmt(p.value, { currency: true })}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function Dashboard({ auth, stats = {} }) {
    const customers = stats.customers || { total: 0, active: 0, outstanding: 0 };
    const invoices = stats.invoices || { total_outstanding: 0, total_invoiced: 0, total_collected: 0, overdue_count: 0 };
    const bills = stats.bills || { total_ap: 0, unpaid_count: 0, overdue_count: 0, total_billed: 0 };
    const period = stats.period || { sales_this_month: 0, expenses_this_month: 0, net_this_month: 0 };
    const cashFlow = stats.cash_flow || { series: [], total_in: 0, total_out: 0, total_net: 0 };
    const pnl = stats.profit_and_loss || { series: [], total_income: 0, total_expense: 0, total_net: 0 };
    const expensesBreakdown = stats.expenses_breakdown || { categories: [], total: 0 };
    const netCompare = stats.net_income_compare || { previous: { label: '', net: 0, income: 0, expense: 0 }, current: { label: '', net: 0, income: 0, expense: 0 } };
    const arAging = stats.ar_aging || { coming_due: 0, '1_30': 0, '31_60': 0, '61_90': 0, over_90: 0, total: 0 };
    const apAging = stats.ap_aging || { coming_due: 0, '1_30': 0, '31_60': 0, '61_90': 0, over_90: 0, total: 0 };
    const overdueInvoices = stats.overdue_invoices || [];
    const overdueBills = stats.overdue_bills || [];

    const collectionRate = invoices.total_invoiced > 0
        ? Math.round((invoices.total_collected / invoices.total_invoiced) * 100)
        : 0;
    const netMonth = period.net_this_month ?? 0;

    const planPermissions = auth?.planPermissions ?? {};
    const isBasic = planPermissions['dashboard.basic'];
    const isStandard = planPermissions['dashboard.standard'];
    const isAdvanced = planPermissions['dashboard.advanced'] || auth.user.role_name === 'super-admin';
    const showInsights = isStandard || isAdvanced;

    const netDelta = (netCompare.current.net ?? 0) - (netCompare.previous.net ?? 0);
    const netDeltaPct = netCompare.previous.net !== 0
        ? Math.round((netDelta / Math.abs(netCompare.previous.net)) * 100)
        : null;

    const agingRows = [
        { key: 'coming_due', label: 'Coming due',     ar: arAging.coming_due, ap: apAging.coming_due },
        { key: '1_30',       label: '1–30 days late', ar: arAging['1_30'],    ap: apAging['1_30']    },
        { key: '31_60',      label: '31–60 days',     ar: arAging['31_60'],   ap: apAging['31_60']   },
        { key: '61_90',      label: '61–90 days',     ar: arAging['61_90'],   ap: apAging['61_90']   },
        { key: 'over_90',    label: '> 90 days',      ar: arAging.over_90,    ap: apAging.over_90    },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Dashboard</p>
                    <h1 className="font-display text-2xl sm:text-3xl font-medium text-ink tracking-tight">
                        {greeting()}, {auth.user?.name?.split(' ')[0] || 'there'}.
                    </h1>
                    <p className="text-ink-muted text-sm">
                        {isBasic ? 'Your books at a glance.' : 'Insights for you — pick something to dive into.'}
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6 sm:space-y-8 pb-8 min-w-0 w-full">
                {/* ───────── Quick action buttons ───────── */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                    {planPermissions['estimates.create'] && (
                        <Link href={route('estimates.create')} className="group rounded-2xl border border-border-warm bg-surface hover:border-terracotta hover:shadow-md transition-all p-4 flex items-center gap-3">
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                <Icons.Receipt />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-display font-semibold text-ink truncate">Create estimate</p>
                                <p className="text-[11px] text-ink-muted truncate">Send a quotation</p>
                            </div>
                        </Link>
                    )}
                    {planPermissions['invoices.create'] && (
                        <Link href={route('invoices.create')} className="group rounded-2xl border border-border-warm bg-surface hover:border-terracotta hover:shadow-md transition-all p-4 flex items-center gap-3">
                            <span className="p-2 rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                <Icons.Document />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-display font-semibold text-ink truncate">Create invoice</p>
                                <p className="text-[11px] text-ink-muted truncate">Bill a customer</p>
                            </div>
                        </Link>
                    )}
                    {planPermissions['journal.create'] && (
                        <Link href={route('transactions.deposit.create')} className="group rounded-2xl border border-border-warm bg-surface hover:border-terracotta hover:shadow-md transition-all p-4 flex items-center gap-3">
                            <span className="p-2 rounded-xl bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                <Icons.Wallet />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-display font-semibold text-ink truncate">Add transaction</p>
                                <p className="text-[11px] text-ink-muted truncate">Deposit or withdrawal</p>
                            </div>
                        </Link>
                    )}
                    {planPermissions['bills.create'] && (
                        <Link href={route('bills.create')} className="group rounded-2xl border border-border-warm bg-surface hover:border-terracotta hover:shadow-md transition-all p-4 flex items-center gap-3">
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                                <Icons.ShoppingCart />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-display font-semibold text-ink truncate">Add bill</p>
                                <p className="text-[11px] text-ink-muted truncate">Record a purchase</p>
                            </div>
                        </Link>
                    )}
                </div>

                {/* ───────── Top KPIs (existing — preserved) ───────── */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 min-w-0">
                    <Link
                        href={planPermissions['reports.aged-reports'] ? route('aged-receivables.index') : route('invoices.index')}
                        className="rounded-2xl bg-terracotta text-white p-4 sm:p-5 shadow-lg flex flex-col min-h-[100px] sm:min-h-[112px] min-w-0 active:opacity-90"
                    >
                        <span className="text-[10px] sm:text-xs font-semibold uppercase tracking-wider opacity-90">Receivables</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold font-mono tabular-nums truncate min-w-0" title={fmt(invoices.total_outstanding, { currency: true })}>
                            {fmt(invoices.total_outstanding, { currency: true, compact: true })}
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs opacity-90">
                            {invoices.overdue_count > 0 ? <span className="text-mustard">{invoices.overdue_count} overdue</span> : 'To collect'}
                        </p>
                    </Link>

                    <Link
                        href={planPermissions['reports.aged-reports'] ? route('accounts-payable.index') : route('bills.index')}
                        className="rounded-2xl bg-surface border border-border-warm p-4 sm:p-5 shadow-sm flex flex-col min-h-[100px] sm:min-h-[112px] hover:shadow-md transition-all min-w-0 active:opacity-90"
                    >
                        <span className="text-[10px] sm:text-xs font-semibold text-ink-muted uppercase tracking-wider">Payables</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-terracotta font-mono tabular-nums truncate min-w-0" title={fmt(bills.total_ap, { currency: true })}>
                            {fmt(bills.total_ap, { currency: true, compact: true })}
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs text-ink-muted">
                            {bills.overdue_count > 0 ? <span className="text-terracotta">{bills.overdue_count} overdue</span> : 'To pay'}
                        </p>
                    </Link>

                    <div className="rounded-2xl bg-surface border border-border-warm p-4 sm:p-5 shadow-sm flex flex-col min-h-[100px] sm:min-h-[112px] min-w-0">
                        <span className="text-[10px] sm:text-xs font-semibold text-ink-muted uppercase tracking-wider">This month</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold font-mono tabular-nums truncate" title={fmt(netMonth, { currency: true })}>
                            <span className={netMonth >= 0 ? 'text-forest' : 'text-terracotta'}>
                                {fmt(netMonth, { currency: true, compact: true })}
                            </span>
                        </p>
                        <p className="mt-auto text-[10px] sm:text-xs text-ink-muted">Sales − expenses</p>
                    </div>

                    <div className="rounded-2xl bg-surface border border-border-warm p-4 sm:p-5 shadow-sm flex flex-col min-h-[100px] sm:min-h-[112px]">
                        <span className="text-[10px] sm:text-xs font-semibold text-ink-muted uppercase tracking-wider">Collection rate</span>
                        <p className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-forest tabular-nums">{collectionRate}%</p>
                        <p className="mt-auto text-[10px] sm:text-xs text-ink-muted">Invoiced → collected</p>
                    </div>
                </div>

                {/* ───────── Insights for you (Standard + Advanced plans) ───────── */}
                {showInsights && (
                    <>
                        <div className="flex items-center justify-between">
                            <h2 className="font-display text-lg sm:text-xl font-medium text-ink">Insights for you</h2>
                            {planPermissions['reports.view'] && (
                                <Link href={route('reports.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta-dark inline-flex items-center gap-1">
                                    All reports <Icons.ChevronRight />
                                </Link>
                            )}
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

                            {/* ───── Overdue invoices and bills ───── */}
                            <Card title="Overdue invoices and bills" icon={<Icons.Exclamation />} accent="terracotta">
                                <div className="space-y-4">
                                    <OverdueList
                                        title="Overdue invoices"
                                        count={overdueInvoices.length}
                                        items={overdueInvoices.map(i => ({
                                            id: i.id,
                                            href: route('invoices.show', i.id),
                                            title: i.customer_name,
                                            subtitle: `${i.invoice_number} · ${i.days_overdue} day${i.days_overdue === 1 ? '' : 's'} late`,
                                            amount: i.balance,
                                        }))}
                                        emptyText="You don't have any overdue invoices. Nice!"
                                    />
                                    <OverdueList
                                        title="Overdue bills"
                                        count={overdueBills.length}
                                        items={overdueBills.map(b => ({
                                            id: b.id,
                                            href: route('bills.show', b.id),
                                            title: b.supplier_name,
                                            subtitle: `${b.bill_number} · ${b.days_overdue} day${b.days_overdue === 1 ? '' : 's'} late`,
                                            amount: b.balance,
                                        }))}
                                        emptyText="You don't have any overdue bills. Nice!"
                                    />
                                </div>
                            </Card>

                            {/* ───── Cash flow ───── */}
                            <Card
                                title="Cash flow"
                                icon={<Icons.TrendingUp />}
                                accent="emerald"
                                action={planPermissions['reports.cashflow'] && (
                                    <Link href={route('cashflow-summary.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta-dark inline-flex items-center gap-1">
                                        View report <Icons.ChevronRight />
                                    </Link>
                                )}
                                subtitle="Last 12 months"
                            >
                                <div className="grid grid-cols-3 gap-2 mb-3 text-xs">
                                    <Stat label="Inflow" value={fmt(cashFlow.total_in, { currency: true, compact: true })} color="text-emerald-600" />
                                    <Stat label="Outflow" value={fmt(cashFlow.total_out, { currency: true, compact: true })} color="text-rose-600" />
                                    <Stat label="Net" value={fmt(cashFlow.total_net, { currency: true, compact: true })} color={cashFlow.total_net >= 0 ? 'text-forest' : 'text-terracotta'} />
                                </div>
                                <div className="h-56">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={cashFlow.series} margin={{ top: 8, right: 8, left: -8, bottom: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#e7e5e4" vertical={false} />
                                            <XAxis dataKey="short" tick={{ fontSize: 11 }} stroke="#a8a29e" />
                                            <YAxis tick={{ fontSize: 10 }} stroke="#a8a29e" tickFormatter={(v) => fmt(v, { currency: true, compact: true }).replace('RM ', '')} width={56} />
                                            <Tooltip content={<ChartTooltip />} />
                                            <Legend wrapperStyle={{ fontSize: 11 }} iconSize={10} />
                                            <Line type="monotone" dataKey="inflow" name="Inflow" stroke="#059669" strokeWidth={2} dot={{ r: 3 }} />
                                            <Line type="monotone" dataKey="outflow" name="Outflow" stroke="#dc2626" strokeWidth={2} dot={{ r: 3 }} />
                                            <Line type="monotone" dataKey="net" name="Net change" stroke="#0f172a" strokeWidth={2} strokeDasharray="4 4" dot={false} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </Card>

                            {/* ───── Profit and loss ───── */}
                            <Card
                                title="Profit and loss"
                                icon={<Icons.ChartBar />}
                                accent="forest"
                                action={planPermissions['reports.profit-loss'] && (
                                    <Link href={route('profit-and-loss.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta-dark inline-flex items-center gap-1">
                                        View report <Icons.ChevronRight />
                                    </Link>
                                )}
                                subtitle="Last 12 months"
                            >
                                <div className="grid grid-cols-3 gap-2 mb-3 text-xs">
                                    <Stat label="Income" value={fmt(pnl.total_income, { currency: true, compact: true })} color="text-emerald-600" />
                                    <Stat label="Expenses" value={fmt(pnl.total_expense, { currency: true, compact: true })} color="text-rose-600" />
                                    <Stat label="Net" value={fmt(pnl.total_net, { currency: true, compact: true })} color={pnl.total_net >= 0 ? 'text-forest' : 'text-terracotta'} />
                                </div>
                                <div className="h-56">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={pnl.series} margin={{ top: 8, right: 8, left: -8, bottom: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#e7e5e4" vertical={false} />
                                            <XAxis dataKey="short" tick={{ fontSize: 11 }} stroke="#a8a29e" />
                                            <YAxis tick={{ fontSize: 10 }} stroke="#a8a29e" tickFormatter={(v) => fmt(v, { currency: true, compact: true }).replace('RM ', '')} width={56} />
                                            <Tooltip content={<ChartTooltip />} />
                                            <Legend wrapperStyle={{ fontSize: 11 }} iconSize={10} />
                                            <Bar dataKey="income" name="Income" fill="#059669" radius={[4, 4, 0, 0]} />
                                            <Bar dataKey="expense" name="Expenses" fill="#dc2626" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </Card>

                            {/* ───── Expenses breakdown (donut) ───── */}
                            <Card
                                title="Expenses breakdown"
                                icon={<Icons.ChartPie />}
                                accent="mustard"
                                subtitle={`Year to date · ${fmt(expensesBreakdown.total, { currency: true })}`}
                            >
                                {expensesBreakdown.categories.length > 0 ? (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                                        <div className="h-56">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <PieChart>
                                                    <Pie
                                                        data={expensesBreakdown.categories}
                                                        dataKey="total"
                                                        nameKey="name"
                                                        cx="50%"
                                                        cy="50%"
                                                        innerRadius={50}
                                                        outerRadius={80}
                                                        paddingAngle={2}
                                                    >
                                                        {expensesBreakdown.categories.map((_, idx) => (
                                                            <Cell key={idx} fill={PIE_COLORS[idx % PIE_COLORS.length]} />
                                                        ))}
                                                    </Pie>
                                                    <Tooltip content={<ChartTooltip />} />
                                                </PieChart>
                                            </ResponsiveContainer>
                                        </div>
                                        <ul className="space-y-2">
                                            {expensesBreakdown.categories.map((c, idx) => (
                                                <li key={c.code} className="flex items-center gap-2 text-xs">
                                                    <span className="w-2.5 h-2.5 rounded-sm shrink-0" style={{ background: PIE_COLORS[idx % PIE_COLORS.length] }} />
                                                    <span className="flex-1 truncate text-ink" title={c.name}>{c.name}</span>
                                                    <span className="font-mono tabular-nums text-ink-muted">{c.percent}%</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : (
                                    <EmptyState text="No expenses recorded yet for this year." />
                                )}
                            </Card>

                            {/* ───── Net income (period comparison) ───── */}
                            <Card
                                title="Net income"
                                icon={<Icons.TrendingUp />}
                                accent="forest"
                                subtitle="Previous month vs current month"
                            >
                                <div className="grid grid-cols-2 gap-4 mb-4">
                                    <PeriodColumn data={netCompare.previous} label="Previous" />
                                    <PeriodColumn data={netCompare.current} label="Current" highlight />
                                </div>
                                {netDeltaPct !== null && (
                                    <div className={`rounded-xl p-3 text-xs flex items-center justify-between ${netDelta >= 0 ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-900/20 dark:text-emerald-200' : 'bg-rose-50 text-rose-900 dark:bg-rose-900/20 dark:text-rose-200'}`}>
                                        <span>Change vs last month</span>
                                        <span className="font-mono font-bold tabular-nums">
                                            {netDelta >= 0 ? '+' : ''}{fmt(netDelta, { currency: true })} ({netDeltaPct >= 0 ? '+' : ''}{netDeltaPct}%)
                                        </span>
                                    </div>
                                )}
                            </Card>

                            {/* ───── Payable and owing (aging) ───── */}
                            <Card
                                title="Payable and owing"
                                icon={<Icons.Wallet />}
                                accent="ink"
                                subtitle="Aging buckets"
                            >
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm">
                                                <th className="px-3 py-2 text-left">Bucket</th>
                                                <th className="px-3 py-2 text-right">Owed to you</th>
                                                <th className="px-3 py-2 text-right">You owe</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border-warm">
                                            {agingRows.map((r) => (
                                                <tr key={r.key}>
                                                    <td className={`px-3 py-2 ${r.key === 'over_90' ? 'font-semibold text-rose-600' : 'text-ink'}`}>{r.label}</td>
                                                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                        <span className={r.ar > 0 ? 'text-emerald-700 font-semibold' : 'text-ink-muted'}>{fmt(r.ar, { currency: true })}</span>
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                        <span className={r.ap > 0 ? 'text-rose-700 font-semibold' : 'text-ink-muted'}>{fmt(r.ap, { currency: true })}</span>
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-cream/60 dark:bg-surface-alt font-bold">
                                                <td className="px-3 py-2 text-ink uppercase tracking-wider text-[10px]">Total</td>
                                                <td className="px-3 py-2 text-right font-mono tabular-nums">{fmt(arAging.total, { currency: true })}</td>
                                                <td className="px-3 py-2 text-right font-mono tabular-nums">{fmt(apAging.total, { currency: true })}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div className="mt-3 flex items-center gap-3">
                                    {planPermissions['reports.aged-reports'] && (
                                        <>
                                            <Link href={route('aged-receivables.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta-dark inline-flex items-center gap-1">
                                                Aged receivables <Icons.ChevronRight />
                                            </Link>
                                            <span className="text-ink-muted">·</span>
                                            <Link href={route('accounts-payable.index')} className="text-xs font-semibold text-terracotta hover:text-terracotta-dark inline-flex items-center gap-1">
                                                Aged payables <Icons.ChevronRight />
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </Card>
                        </div>
                    </>
                )}

                {/* ───────── Compliance status (Advanced/Corporate plans only) ───────── */}
                {isAdvanced && stats.audit && (
                    <section className="rounded-2xl border border-border-warm bg-surface shadow-sm overflow-hidden">
                        <div className="px-4 sm:px-6 py-4 border-b border-border-warm bg-cream/30 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="p-1 bg-surface-alt rounded text-terracotta">
                                    <Icons.CheckCircle />
                                </span>
                                <h2 className="text-sm font-display font-medium text-ink uppercase tracking-wider">Compliance Status</h2>
                            </div>
                            <Link href={route('audit.index')} className="text-xs font-bold text-terracotta hover:text-terracotta-dark flex items-center gap-1">
                                Review compliance <Icons.ChevronRight />
                            </Link>
                        </div>
                        <div className="p-4 sm:p-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <KPI label="Total items" value={stats.audit.total} tone="ink" />
                            <KPI label="Verified" value={stats.audit.verified} tone="forest" />
                            <KPI label="Unaudited" value={stats.audit.unaudited} tone="terracotta" />
                            <KPI label="Flagged" value={stats.audit.flagged} tone="mustard" />
                        </div>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

// ─── Sub-components ────────────────────────────────────────────────────

function Card({ title, icon, accent = 'terracotta', subtitle = '', action = null, children }) {
    const accentClass = {
        terracotta: 'text-terracotta',
        forest:     'text-forest',
        emerald:    'text-emerald-600',
        mustard:    'text-mustard',
        ink:        'text-ink',
    }[accent] || 'text-terracotta';

    return (
        <section className="rounded-2xl border border-border-warm bg-surface shadow-sm overflow-hidden">
            <div className="px-4 sm:px-6 py-4 border-b border-border-warm flex items-start justify-between gap-2">
                <div className="flex items-start gap-3">
                    <span className={`p-2 rounded-xl bg-surface-alt ${accentClass}`}>
                        {icon}
                    </span>
                    <div>
                        <h3 className="text-sm font-display font-semibold text-ink">{title}</h3>
                        {subtitle && <p className="text-[11px] text-ink-muted mt-0.5">{subtitle}</p>}
                    </div>
                </div>
                {action}
            </div>
            <div className="p-4 sm:p-5">{children}</div>
        </section>
    );
}

function OverdueList({ title, count, items, emptyText }) {
    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <p className="text-xs font-display font-semibold text-ink uppercase tracking-wider">{title} {count > 0 && <span className="text-ink-muted">({count})</span>}</p>
            </div>
            {items.length > 0 ? (
                <ul className="divide-y divide-border-warm">
                    {items.map((item) => (
                        <li key={item.id}>
                            <Link href={item.href} className="flex items-center justify-between gap-3 py-2.5 hover:bg-cream/40 -mx-2 px-2 rounded-lg transition-colors">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-ink truncate">{item.title}</p>
                                    <p className="text-[11px] text-rose-600">{item.subtitle}</p>
                                </div>
                                <span className="font-mono tabular-nums font-bold text-ink shrink-0">{fmt(item.amount, { currency: true })}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-xs text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl px-3 py-2 inline-flex items-center gap-1.5">
                    <span className="text-emerald-500">✓</span> {emptyText}
                </p>
            )}
        </div>
    );
}

function Stat({ label, value, color = 'text-ink' }) {
    return (
        <div className="rounded-xl bg-surface-alt p-2.5">
            <p className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">{label}</p>
            <p className={`text-sm font-bold font-mono tabular-nums truncate ${color}`} title={value}>{value}</p>
        </div>
    );
}

function PeriodColumn({ data, label, highlight = false }) {
    const netColor = (data.net ?? 0) >= 0 ? 'text-forest' : 'text-terracotta';
    return (
        <div className={`rounded-xl border p-3 ${highlight ? 'border-terracotta/40 bg-terracotta/5' : 'border-border-warm bg-surface-alt/50'}`}>
            <p className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">{label}</p>
            <p className="text-xs font-semibold text-ink mt-0.5 mb-2">{data.label}</p>
            <div className="space-y-1 text-xs">
                <div className="flex justify-between"><span className="text-ink-muted">Income</span><span className="font-mono tabular-nums text-emerald-700">{fmt(data.income, { currency: true })}</span></div>
                <div className="flex justify-between"><span className="text-ink-muted">Expenses</span><span className="font-mono tabular-nums text-rose-700">{fmt(data.expense, { currency: true })}</span></div>
                <div className="flex justify-between pt-1 border-t border-border-warm/60"><span className="font-semibold text-ink">Net</span><span className={`font-mono tabular-nums font-bold ${netColor}`}>{fmt(data.net, { currency: true })}</span></div>
            </div>
        </div>
    );
}

function EmptyState({ text }) {
    return (
        <div className="text-center py-10 text-ink-muted text-xs italic">{text}</div>
    );
}

function KPI({ label, value, tone = 'ink' }) {
    const toneClass = {
        ink: 'border-border-warm bg-cream/50 text-ink',
        forest: 'border-forest/30 bg-forest/10 text-forest',
        terracotta: 'border-terracotta/30 bg-terracotta/10 text-terracotta',
        mustard: 'border-mustard/40 bg-mustard/15 text-mustard',
    }[tone] || 'border-border-warm bg-cream/50 text-ink';
    return (
        <div className={`p-3 rounded-xl border ${toneClass}`}>
            <p className="text-[10px] font-display font-medium uppercase tracking-widest mb-1 opacity-80">{label}</p>
            <p className="text-lg font-bold font-mono tabular-nums">{value}</p>
        </div>
    );
}
