import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    Document: () => <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChartPie: () => <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /></svg>,
    Scale: () => <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    ChartBar: () => <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" /></svg>,
    Users: () => <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    ChevronRight: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

const reportCards = [
    {
        title: 'Profit & Loss',
        description: 'Income vs expenses from your ledger for a chosen period.',
        routeName: 'profit-and-loss.index',
        permission: 'reports.profit-loss',
        Icon: Icons.ChartPie,
        accent: 'forest',
    },
    {
        title: 'Sales Reports',
        description: 'Summary of sales revenue by customer and product.',
        routeName: 'reports.sales.index',
        permission: 'reports.sales',
        Icon: Icons.ChartBar,
        accent: 'terracotta',
    },
    {
        title: 'Balance Sheet',
        description: 'Assets, liabilities, and equity as at a specific date.',
        routeName: 'balance-sheet.index',
        permission: 'reports.balance-sheet',
        Icon: Icons.Scale,
        accent: 'ink',
    },
    {
        title: 'Cashflow Summary',
        description: 'Total sales vs total expenses with a monthly graph.',
        routeName: 'cashflow-summary.index',
        permission: 'reports.cashflow',
        Icon: Icons.ChartBar,
        accent: 'mustard',
    },
    {
        title: 'Aged Reports (AP/AR)',
        description: 'Overdue invoices and bills — 30, 60, or 90+ days.',
        routeName: 'aged-receivables.index',
        permission: 'reports.aged-reports',
        Icon: Icons.Users,
        accent: 'terracotta',
    },
];

const accentClass = {
    forest:     'bg-forest text-white',
    terracotta: 'bg-terracotta text-white',
    ink:        'bg-ink text-cream',
    mustard:    'bg-mustard text-ink',
};

export default function Hub({ auth }) {
    const planPermissions = auth?.planPermissions ?? {};
    const isSuperAdmin = auth.user.role_name === 'super-admin';

    const visibleCards = reportCards.filter(card =>
        planPermissions[card.permission] || isSuperAdmin
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Reports</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Reports hub</h1>
                    <p className="text-ink-muted text-sm">Open a report, refine the period, export when you’re done.</p>
                </div>
            }
        >
            <Head title="Reports" />

            {visibleCards.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {visibleCards.map(({ title, description, routeName, Icon, accent }) => (
                        <Link
                            key={routeName}
                            href={route(routeName)}
                            className="group block bg-surface rounded-2xl border border-border-warm hover:border-ink-muted/40 transition-colors overflow-hidden"
                        >
                            <div className={`p-6 ${accentClass[accent]}`}>
                                <div className="inline-flex p-3 rounded-xl bg-white/15 mb-4">
                                    <Icon />
                                </div>
                                <h3 className="font-display text-lg font-medium">{title}</h3>
                            </div>
                            <div className="p-5 border-t border-border-warm">
                                <p className="text-sm text-ink-muted mb-3">{description}</p>
                                <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-terracotta group-hover:text-terracotta-dark dark:group-hover:text-terracotta-light">
                                    Open report <Icons.ChevronRight />
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            ) : (
                <div className="bg-surface rounded-2xl border border-border-warm p-12 text-center">
                    <div className="inline-flex p-4 rounded-full bg-surface-alt text-ink-muted mb-4">
                        <Icons.Document />
                    </div>
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
        </AuthenticatedLayout>
    );
}
