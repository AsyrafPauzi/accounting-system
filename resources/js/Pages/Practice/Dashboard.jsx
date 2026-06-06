import PracticeLayout from '@/Layouts/PracticeLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
} from 'recharts';

// ─── Formatters ────────────────────────────────────────────────────────

const formatRMCompact = (n) => {
    const num = Number(n) || 0;
    const abs = Math.abs(num);
    if (abs >= 1_000_000) return 'RM ' + (num / 1_000_000).toFixed(1) + 'M';
    if (abs >= 10_000)    return 'RM ' + (num / 1_000).toFixed(0) + 'K';
    return 'RM ' + num.toLocaleString('en-MY', { maximumFractionDigits: 0 });
};

const formatRMExact = (n) => {
    const num = Number(n) || 0;
    return 'RM ' + num.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatRelative = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    const days = Math.round((Date.now() - d.getTime()) / 86_400_000);
    if (days <= 0) return 'today';
    if (days === 1) return 'yesterday';
    if (days < 7) return `${days}d ago`;
    if (days < 30) return `${Math.round(days / 7)}w ago`;
    if (days < 365) return `${Math.round(days / 30)}mo ago`;
    return `${Math.round(days / 365)}y ago`;
};

const formatDueIn = (days) => {
    if (days === 0) return 'today';
    if (days === 1) return 'tomorrow';
    if (days < 7) return `${days} days`;
    if (days < 30) return `${Math.round(days / 7)} weeks`;
    return `${Math.round(days / 30)} months`;
};

// ─── Colour palette (matches the SME dashboard) ────────────────────────

const HEALTH_COLORS = { good: '#0f766e', watch: '#a16207', risk: '#c2410c' };
const AGING_COLORS  = ['#0f766e', '#65a30d', '#a16207', '#dc2626', '#7f1d1d']; // current → 90+
const TERRACOTTA    = '#c1502c';

// ─── Inline chart tooltip — same look as the SME Dashboard ─────────────

function ChartTooltip({ active, payload, label, currency = false }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="bg-surface border border-border-warm rounded-xl shadow-lg p-3 text-xs">
            {label && <p className="font-semibold text-ink mb-1.5">{label}</p>}
            {payload.map((p, idx) => (
                <div key={idx} className="flex items-center justify-between gap-4 py-0.5">
                    <span className="flex items-center gap-1.5 text-ink-muted">
                        <span className="w-2 h-2 rounded-full" style={{ background: p.color || p.payload?.fill }} />
                        {p.name}
                    </span>
                    <span className="font-semibold text-ink">
                        {currency ? formatRMExact(p.value) : p.value}
                    </span>
                </div>
            ))}
        </div>
    );
}

// ─── Page ──────────────────────────────────────────────────────────────

export default function Dashboard({ firm, aggregates, clients, attention = [], deadlines = [] }) {
    const { flash = {}, auth } = usePage().props;
    const canUnlink = Array.isArray(auth?.permissions)
        && auth.permissions.includes('practice.clients.unlink');

    const [unlinkTarget, setUnlinkTarget] = useState(null);
    const [unlinking, setUnlinking] = useState(false);

    const enterClient = (tenantId) => {
        router.post(route('practice.switch', tenantId));
    };

    const confirmUnlink = () => {
        if (!unlinkTarget) return;
        setUnlinking(true);
        router.delete(route('practice.clients.unlink', unlinkTarget.tenant_id), {
            preserveScroll: true,
            onFinish: () => {
                setUnlinking(false);
                setUnlinkTarget(null);
            },
        });
    };

    // ── Derived chart datasets ─────────────────────────────────────────

    const agingData = useMemo(() => {
        const a = aggregates?.ar_aging ?? {};
        return [
            { key: 'current',      label: 'Current',     value: a.current      ?? 0 },
            { key: 'days_1_30',    label: '1–30 days',   value: a.days_1_30    ?? 0 },
            { key: 'days_31_60',   label: '31–60 days',  value: a.days_31_60   ?? 0 },
            { key: 'days_61_90',   label: '61–90 days',  value: a.days_61_90   ?? 0 },
            { key: 'days_90_plus', label: '90+ days',    value: a.days_90_plus ?? 0 },
        ];
    }, [aggregates]);

    const agingNonZero = agingData.filter((b) => Number(b.value) > 0);

    const trendData = useMemo(() => aggregates?.revenue_trend ?? [], [aggregates]);

    const topClients = useMemo(() => aggregates?.top_clients_by_revenue ?? [], [aggregates]);

    const healthDist = aggregates?.health_distribution ?? { good: 0, watch: 0, risk: 0 };

    const cap = firm.client_cap;
    const count = firm.client_count ?? 0;
    const unlimited = cap === null || cap === undefined;
    const utilization = unlimited ? null : Math.min(100, Math.round((count / Math.max(1, cap)) * 100));

    return (
        <PracticeLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">{firm.name}</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Practice console
                        </h1>
                        <p className="text-ink-muted text-sm mt-1">
                            Live numbers across every client, plus the deadlines and red flags you need to act on.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="bg-surface border border-border-warm rounded-2xl px-4 py-2 text-sm">
                            <p className="text-eyebrow font-semibold uppercase text-ink-muted">Clients</p>
                            <p className="font-display text-base font-medium text-ink mt-0.5">
                                {count}
                                {unlimited ? (
                                    <span className="text-ink-muted text-sm"> / unlimited</span>
                                ) : (
                                    <span className="text-ink-muted text-sm"> / {cap}</span>
                                )}
                            </p>
                        </div>
                        <Link
                            href={route('practice.clients.create')}
                            className="px-4 py-2.5 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark transition-colors"
                        >
                            + Add client
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Practice console" />

            {(flash.success || flash.error || flash.info) && (
                <div className="mb-6 space-y-2">
                    {flash.success && (
                        <div className="bg-forest/10 border border-forest/30 rounded-2xl px-5 py-3 text-sm text-forest-dark">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-5 py-3 text-sm text-terracotta-dark">
                            {flash.error}
                        </div>
                    )}
                    {flash.info && (
                        <div className="bg-mustard/10 border border-mustard/40 rounded-2xl px-5 py-3 text-sm text-ink">
                            {flash.info}
                        </div>
                    )}
                </div>
            )}

            {/* ─── KPI cards ──────────────────────────────────────────── */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-8">
                <Stat
                    label="Active clients"
                    value={aggregates.total_clients}
                    hint={unlimited ? 'unlimited cap' : `${utilization}% of plan`}
                />
                <Stat
                    label="Revenue MTD"
                    value={formatRMCompact(aggregates.total_revenue_mtd)}
                    hint="paid invoices, this month"
                    accent="forest"
                />
                <Stat
                    label="AR outstanding"
                    value={formatRMCompact(aggregates.total_ar_outstanding)}
                    hint="unpaid across portfolio"
                    accent="mustard"
                />
                <Stat
                    label="Overdue invoices"
                    value={aggregates.total_overdue_count}
                    hint="past due date"
                    accent={aggregates.total_overdue_count > 0 ? 'terracotta' : 'default'}
                />
                <Stat
                    label="Cash on hand"
                    value={formatRMCompact(aggregates.total_cash_balance)}
                    hint="bank balances combined"
                />
            </div>

            {/* ─── Charts row 1: revenue trend (wide) + portfolio health (narrow) ─── */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <Card title="Portfolio revenue (last 6 months)" subtitle="Paid invoices summed across every active client" className="lg:col-span-2">
                    {trendData.length === 0 ? (
                        <Empty>No revenue data yet.</Empty>
                    ) : (
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={trendData} margin={{ top: 10, right: 12, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e7e2d9" vertical={false} />
                                    <XAxis dataKey="month" stroke="#6b6256" tick={{ fontSize: 12 }} axisLine={false} tickLine={false} />
                                    <YAxis stroke="#6b6256" tick={{ fontSize: 12 }} tickFormatter={formatRMCompact} axisLine={false} tickLine={false} />
                                    <Tooltip content={<ChartTooltip currency />} />
                                    <Line type="monotone" dataKey="revenue" name="Revenue" stroke={TERRACOTTA} strokeWidth={2.5} dot={{ r: 4, fill: TERRACOTTA }} activeDot={{ r: 6 }} />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </Card>

                <Card title="Portfolio health" subtitle="Per-client risk based on overdue invoices and aging">
                    {(healthDist.good + healthDist.watch + healthDist.risk) === 0 ? (
                        <Empty>Add a client to see health.</Empty>
                    ) : (
                        <div className="h-64 flex items-center">
                            <div className="w-full grid grid-cols-2 gap-3 items-center">
                                <div className="h-48">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={[
                                                    { name: 'Good',  value: healthDist.good,  fill: HEALTH_COLORS.good },
                                                    { name: 'Watch', value: healthDist.watch, fill: HEALTH_COLORS.watch },
                                                    { name: 'Risk',  value: healthDist.risk,  fill: HEALTH_COLORS.risk },
                                                ].filter((d) => d.value > 0)}
                                                dataKey="value"
                                                nameKey="name"
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={45}
                                                outerRadius={75}
                                                paddingAngle={2}
                                            />
                                            <Tooltip content={<ChartTooltip />} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                                <div className="space-y-2 text-xs">
                                    <HealthLegend label="Good"  count={healthDist.good}  color={HEALTH_COLORS.good}  />
                                    <HealthLegend label="Watch" count={healthDist.watch} color={HEALTH_COLORS.watch} />
                                    <HealthLegend label="Risk"  count={healthDist.risk}  color={HEALTH_COLORS.risk}  />
                                </div>
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {/* ─── Charts row 2: AR aging donut + top clients by revenue ─── */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <Card title="AR aging across portfolio" subtitle="Outstanding receivables bucketed by days past due">
                    {agingNonZero.length === 0 ? (
                        <Empty>No outstanding receivables.</Empty>
                    ) : (
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={agingNonZero}
                                        dataKey="value"
                                        nameKey="label"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={45}
                                        outerRadius={85}
                                        paddingAngle={2}
                                        label={({ percent }) => `${Math.round(percent * 100)}%`}
                                        labelLine={false}
                                        fontSize={11}
                                    >
                                        {agingNonZero.map((entry, i) => (
                                            <Cell key={entry.key} fill={AGING_COLORS[agingData.findIndex((b) => b.key === entry.key)]} />
                                        ))}
                                    </Pie>
                                    <Tooltip content={<ChartTooltip currency />} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                    <div className="mt-3 grid grid-cols-5 gap-1 text-[10px] text-ink-muted">
                        {agingData.map((b, i) => (
                            <div key={b.key} className="flex flex-col items-center text-center">
                                <span className="w-3 h-3 rounded-full mb-1" style={{ background: AGING_COLORS[i] }} />
                                <span className="font-semibold">{b.label}</span>
                                <span>{formatRMCompact(b.value)}</span>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card title="Top clients by revenue (MTD)" subtitle="Eight largest billers this month" className="lg:col-span-2">
                    {topClients.length === 0 ? (
                        <Empty>No revenue this month yet.</Empty>
                    ) : (
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={topClients} margin={{ top: 10, right: 12, left: 0, bottom: 30 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e7e2d9" vertical={false} />
                                    <XAxis dataKey="name" stroke="#6b6256" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} interval={0} angle={-20} textAnchor="end" height={60} />
                                    <YAxis stroke="#6b6256" tick={{ fontSize: 12 }} tickFormatter={formatRMCompact} axisLine={false} tickLine={false} />
                                    <Tooltip content={<ChartTooltip currency />} />
                                    <Bar dataKey="revenue_mtd" name="Revenue MTD" fill={TERRACOTTA} radius={[6, 6, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </Card>
            </div>

            {/* ─── Side-by-side: Needs attention + Upcoming deadlines ─── */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <Card title="Needs attention" subtitle="Clients with overdue invoices or aging AR — sorted most urgent first">
                    {attention.length === 0 ? (
                        <Empty positive>All clients are healthy. Nice work.</Empty>
                    ) : (
                        <ul className="divide-y divide-border-warm -mx-2">
                            {attention.map((c) => (
                                <li key={c.tenant_id} className="px-2 py-3 flex items-start gap-3">
                                    <HealthDot tag={c.health} />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-baseline justify-between gap-2">
                                            <p className="font-semibold text-ink truncate">{c.name}</p>
                                            <p className="text-xs text-ink-muted shrink-0">
                                                {c.overdue_count} overdue • {formatRMCompact(c.ar_outstanding)} AR
                                            </p>
                                        </div>
                                        {(c.ar_aging?.days_90_plus ?? 0) > 0 && (
                                            <p className="text-xs text-terracotta mt-0.5">
                                                {formatRMExact(c.ar_aging.days_90_plus)} stuck 90+ days
                                            </p>
                                        )}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => enterClient(c.tenant_id)}
                                        className="text-xs font-semibold text-terracotta hover:text-terracotta-dark whitespace-nowrap"
                                    >
                                        Open →
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Upcoming compliance deadlines" subtitle="Year-end • LHDN Form C • CP204 — next 90 days">
                    {deadlines.length === 0 ? (
                        <Empty>No deadlines in the next 90 days.</Empty>
                    ) : (
                        <ul className="divide-y divide-border-warm -mx-2">
                            {deadlines.slice(0, 8).map((d, idx) => (
                                <li key={`${d.tenant_id}-${d.kind}-${idx}`} className="px-2 py-3 flex items-start gap-3">
                                    <DeadlineBadge daysAway={d.days_away} />
                                    <div className="flex-1 min-w-0">
                                        <p className="font-semibold text-ink text-sm">{d.label}</p>
                                        <p className="text-xs text-ink-muted truncate">
                                            {d.client} • due {d.due}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => enterClient(d.tenant_id)}
                                        className="text-xs font-semibold text-terracotta hover:text-terracotta-dark whitespace-nowrap"
                                    >
                                        Open →
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            {/* ─── Full client table ───────────────────────────────────── */}
            <section className="bg-surface border border-border-warm rounded-3xl overflow-hidden">
                <div className="px-6 py-4 border-b border-border-warm flex items-center justify-between">
                    <div>
                        <h2 className="font-display text-lg font-medium text-ink">All clients</h2>
                        <p className="text-xs text-ink-muted">Click a row to enter the client's books, or use the action menu to manage the link.</p>
                    </div>
                </div>

                {clients.length === 0 ? (
                    <div className="p-10 text-center">
                        <p className="text-ink-muted text-sm">
                            No clients yet. Add one to get started — you can create a brand-new client or invite an existing SME by email.
                        </p>
                        <Link
                            href={route('practice.clients.create')}
                            className="inline-block mt-4 px-4 py-2.5 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark transition-colors"
                        >
                            + Add your first client
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-eyebrow uppercase font-semibold text-ink-muted bg-cream/50">
                                    <th className="px-6 py-3">Client</th>
                                    <th className="px-6 py-3">Plan</th>
                                    <th className="px-6 py-3 text-right">Revenue MTD</th>
                                    <th className="px-6 py-3 text-right">AR</th>
                                    <th className="px-6 py-3 text-right">Overdue</th>
                                    <th className="px-6 py-3 text-right">Cash</th>
                                    <th className="px-6 py-3">Last activity</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {clients.map((c) => (
                                    <tr key={c.tenant_id} className="hover:bg-cream/30 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2.5">
                                                <HealthDot tag={c.health} />
                                                <div className="min-w-0">
                                                    <div className="font-semibold text-ink truncate">{c.name}</div>
                                                    <div className="text-xs text-ink-muted">{c.permission_level}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-ink-muted">
                                            {c.plan ?? <span className="italic">—</span>}
                                            {c.plan_status && c.plan_status !== 'active' && (
                                                <span className="ml-1.5 text-terracotta text-xs uppercase">({c.plan_status})</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right font-tabular">{formatRMExact(c.revenue_mtd)}</td>
                                        <td className="px-6 py-4 text-right font-tabular">{formatRMExact(c.ar_outstanding)}</td>
                                        <td className={`px-6 py-4 text-right font-tabular ${c.overdue_count > 0 ? 'text-terracotta font-semibold' : 'text-ink-muted'}`}>
                                            {c.overdue_count}
                                        </td>
                                        <td className="px-6 py-4 text-right font-tabular text-ink-muted">{formatRMCompact(c.cash_balance)}</td>
                                        <td className="px-6 py-4 text-ink-muted">{formatRelative(c.last_activity_at)}</td>
                                        <td className="px-6 py-4 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() => enterClient(c.tenant_id)}
                                                className="text-sm font-semibold text-terracotta hover:text-terracotta-dark dark:hover:text-terracotta-light"
                                            >
                                                Enter →
                                            </button>
                                            {canUnlink && (
                                                <button
                                                    type="button"
                                                    onClick={() => setUnlinkTarget({ tenant_id: c.tenant_id, name: c.name })}
                                                    className="ml-4 text-xs font-semibold text-ink-muted hover:text-terracotta dark:hover:text-terracotta-light underline-offset-2 hover:underline"
                                                    title="Remove this client from your firm. Their books are kept intact."
                                                >
                                                    Unlink
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {/* ─── Unlink confirmation modal ──────────────────────────── */}
            {unlinkTarget && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="unlink-modal-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
                    onClick={(e) => {
                        if (e.target === e.currentTarget && !unlinking) setUnlinkTarget(null);
                    }}
                >
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-xl max-w-md w-full p-6">
                        <h2 id="unlink-modal-title" className="font-display text-lg font-medium text-ink">
                            Unlink {unlinkTarget.name}?
                        </h2>
                        <p className="mt-3 text-sm text-ink-muted">
                            Their books, users and data stay completely intact &mdash; the only thing that
                            changes is that <b>your firm loses access</b>. They&apos;ll continue with their
                            own admin login. You can re-invite them later if needed.
                        </p>
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setUnlinkTarget(null)}
                                disabled={unlinking}
                                className="px-4 py-2 rounded-xl text-sm font-semibold text-ink-muted hover:bg-cream/50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmUnlink}
                                disabled={unlinking}
                                className="px-4 py-2 rounded-xl text-sm font-semibold bg-terracotta text-white hover:bg-terracotta-dark disabled:opacity-60"
                            >
                                {unlinking ? 'Unlinking…' : 'Unlink client'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PracticeLayout>
    );
}

// ─── Reusable widgets ────────────────────────────────────────────────

function Stat({ label, value, hint, accent = 'default' }) {
    const accentClass = {
        default:    'text-ink',
        forest:     'text-forest-dark dark:text-forest-light',
        mustard:    'text-mustard',
        terracotta: 'text-terracotta',
    }[accent] || 'text-ink';

    return (
        <div className="bg-surface border border-border-warm rounded-2xl p-4">
            <p className="text-eyebrow font-semibold uppercase text-ink-muted text-[10px] tracking-wider">{label}</p>
            <p className={`mt-1.5 font-display text-xl lg:text-2xl font-medium tracking-tight ${accentClass}`}>{value}</p>
            {hint && <p className="text-[11px] text-ink-muted mt-1">{hint}</p>}
        </div>
    );
}

function Card({ title, subtitle, className = '', children }) {
    return (
        <div className={`bg-surface border border-border-warm rounded-3xl p-5 sm:p-6 ${className}`}>
            <div className="mb-3">
                <h2 className="font-display text-base font-medium text-ink">{title}</h2>
                {subtitle && <p className="text-xs text-ink-muted mt-0.5">{subtitle}</p>}
            </div>
            {children}
        </div>
    );
}

function Empty({ children, positive = false }) {
    return (
        <div className={`h-48 flex items-center justify-center text-sm text-center ${positive ? 'text-forest-dark dark:text-forest-light' : 'text-ink-muted'}`}>
            {children}
        </div>
    );
}

function HealthDot({ tag = 'good' }) {
    const colour = HEALTH_COLORS[tag] ?? HEALTH_COLORS.good;
    const ring = {
        good:  'ring-forest/30',
        watch: 'ring-mustard/40',
        risk:  'ring-terracotta/40',
    }[tag] || 'ring-forest/30';
    return (
        <span
            aria-label={`Health: ${tag}`}
            className={`shrink-0 w-2.5 h-2.5 rounded-full ring-4 ${ring}`}
            style={{ background: colour }}
        />
    );
}

function HealthLegend({ label, count, color }) {
    return (
        <div className="flex items-center justify-between gap-2 text-ink">
            <span className="flex items-center gap-2">
                <span className="w-2.5 h-2.5 rounded-full" style={{ background: color }} />
                {label}
            </span>
            <span className="font-semibold text-ink">{count}</span>
        </div>
    );
}

function DeadlineBadge({ daysAway }) {
    let tone = 'bg-forest/15 text-forest-dark border-forest/30';
    if (daysAway <= 7)  tone = 'bg-terracotta/15 text-terracotta-dark border-terracotta/40';
    else if (daysAway <= 30) tone = 'bg-mustard/15 text-ink border-mustard/40';

    return (
        <span className={`shrink-0 inline-flex flex-col items-center justify-center w-12 h-12 rounded-xl border ${tone} text-[10px] font-semibold uppercase tracking-wide`}>
            <span className="text-base leading-none font-display">{daysAway}</span>
            <span className="leading-none mt-0.5">{daysAway === 1 ? 'day' : 'days'}</span>
        </span>
    );
}
