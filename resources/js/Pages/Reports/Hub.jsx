import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    ChevronRight: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

export default function Hub({ auth, snapshot = {}, sections = [], base_currency = 'MYR' }) {
    const planPermissions = auth?.planPermissions ?? {};
    const userPermissions = auth?.permissions ?? [];
    const isSuperAdmin = auth.user.role_name === 'super-admin';
    const canView = (report) => {
        const userPermission = report.user_permission || report.permission;

        return isSuperAdmin
            || (Boolean(planPermissions[report.permission]) && userPermissions.includes(userPermission));
    };
    const visibleSections = sections
        .map((section) => ({
            ...section,
            reports: section.reports.filter(canView),
        }))
        .filter((section) => section.reports.length > 0);
    const stats = [
        snapshot.net_profit !== null && {
            label: `Net profit · ${snapshot.month_label}`,
            value: formatCurrency(snapshot.net_profit, base_currency),
            tone: snapshot.net_profit >= 0 ? 'text-forest' : 'text-terracotta',
        },
        snapshot.tax_owing !== null && {
            label: 'Tax owing · SST period',
            value: formatCurrency(snapshot.tax_owing, base_currency),
            tone: 'text-ink',
        },
        snapshot.overdue_ar_amount !== null && {
            label: 'Overdue receivables',
            value: formatCurrency(snapshot.overdue_ar_amount, base_currency),
            detail: `${snapshot.overdue_ar_count || 0} overdue invoice${snapshot.overdue_ar_count === 1 ? '' : 's'}`,
            tone: 'text-terracotta',
        },
        snapshot.cash !== null && {
            label: 'Cash and bank',
            value: formatCurrency(snapshot.cash, base_currency),
            detail: 'Balance as of today',
            tone: 'text-ink',
        },
    ].filter(Boolean);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Reports</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Reports hub</h1>
                    <p className="text-ink-muted text-sm">A current snapshot, with detailed reports when you need them.</p>
                </div>
            }
        >
            <Head title="Reports" />

            <div className="space-y-8">
                {stats.length > 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-4 rounded-2xl border border-border-warm bg-surface overflow-hidden shadow-sm">
                            {stats.map((stat, index) => (
                                <div
                                    key={stat.label}
                                    className={`px-5 py-4 ${index > 0 ? 'border-t md:border-t-0 md:border-l border-border-warm' : ''}`}
                                >
                                    <p className="text-[10px] font-semibold uppercase tracking-widest text-ink-muted">{stat.label}</p>
                                    <p className={`mt-1.5 text-xl font-bold tabular-nums ${stat.tone}`}>{stat.value}</p>
                                    {stat.detail && <p className="mt-1 text-xs text-ink-muted">{stat.detail}</p>}
                                </div>
                            ))}
                        </div>
                )}

                {visibleSections.length > 0 ? (
                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-x-8 gap-y-7">
                        {visibleSections.map((section) => (
                            <section key={section.title}>
                                <h2 className="mb-2 text-[11px] font-semibold uppercase tracking-widest text-ink-muted">{section.title}</h2>
                                <div className="rounded-2xl border border-border-warm bg-surface divide-y divide-border-warm overflow-hidden shadow-sm">
                                    {section.reports.map((report) => (
                                        <Link
                                            key={report.route_name}
                                            href={route(report.route_name)}
                                            className="group flex items-center gap-4 px-5 py-3.5 hover:bg-surface-alt transition-colors"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <h3 className="text-sm font-semibold text-ink">{report.title}</h3>
                                                <p className="mt-0.5 text-xs text-ink-muted truncate">{report.description}</p>
                                            </div>
                                            <span className="text-ink-muted group-hover:text-terracotta transition-colors">
                                                <Icons.ChevronRight />
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                ) : (
                    <div className="bg-surface rounded-2xl border border-border-warm p-12 text-center">
                    <h3 className="font-display text-lg font-medium text-ink">No reports on this plan</h3>
                    <p className="text-ink-muted max-w-sm mx-auto mt-2">
                        Your current plan doesn’t include financial reports yet. Upgrade to SME or Corporate to open them.
                    </p>
                    <Link
                        href={route('subscription.index')}
                        className="inline-block mt-6 px-6 py-3 bg-terracotta text-white font-semibold rounded-xl hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors"
                    >
                        View plans
                    </Link>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
