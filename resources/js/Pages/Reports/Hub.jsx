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
        title: 'General Ledger Report',
        description: 'Every debit and credit line — filter by date, type, or account.',
        routeName: 'general-ledger.report',
        Icon: Icons.Document,
        color: 'from-slate-600 to-slate-700',
        iconBg: 'bg-white/10',
    },
    {
        title: 'Profit & Loss',
        description: 'Income vs expenses from your ledger for a chosen period.',
        routeName: 'profit-and-loss.index',
        Icon: Icons.ChartPie,
        color: 'from-emerald-600 to-teal-600',
        iconBg: 'bg-white/10',
    },
    {
        title: 'Balance Sheet',
        description: 'Assets, liabilities, and equity as at a specific date.',
        routeName: 'balance-sheet.index',
        Icon: Icons.Scale,
        color: 'from-blue-600 to-indigo-600',
        iconBg: 'bg-white/10',
    },
    {
        title: 'Cashflow Summary',
        description: 'Total sales vs total expenses with a monthly graph.',
        routeName: 'cashflow-summary.index',
        Icon: Icons.ChartBar,
        color: 'from-amber-500 to-orange-600',
        iconBg: 'bg-white/10',
    },
    {
        title: 'Aged Receivables',
        description: 'Who hasn’t paid — 30, 60, or 90+ days overdue.',
        routeName: 'aged-receivables.index',
        Icon: Icons.Users,
        color: 'from-indigo-600 to-purple-600',
        iconBg: 'bg-white/10',
    },
];

export default function Hub({ auth }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Reports</h2>
                    <p className="text-slate-500 text-sm font-medium mt-1">Choose a report to view or export</p>
                </div>
            }
        >
            <Head title="Reports" />

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {reportCards.map(({ title, description, routeName, Icon, color, iconBg }) => (
                    <Link
                        key={routeName}
                        href={route(routeName)}
                        className="group block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-lg hover:border-slate-300 transition-all duration-200 overflow-hidden"
                    >
                        <div className={`p-6 bg-gradient-to-br ${color} text-white`}>
                            <div className={`inline-flex p-3 rounded-xl ${iconBg} mb-4`}>
                                <Icon />
                            </div>
                            <h3 className="text-lg font-bold text-white">{title}</h3>
                        </div>
                        <div className="p-5 border-t border-slate-100">
                            <p className="text-sm text-slate-600 mb-3">{description}</p>
                            <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 group-hover:text-blue-700">
                                Open report <Icons.ChevronRight />
                            </span>
                        </div>
                    </Link>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
