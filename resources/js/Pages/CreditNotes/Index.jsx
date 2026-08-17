import React, { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';
import useClientIndexFilters from '@/hooks/useClientIndexFilters';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Currency: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

const SEARCH_KEYS = ['cn_number', 'customer_name'];

export default function Index({ auth, creditNotes = [] }) {
    const permissions = auth.permissions || [];
    const filters = useClientIndexFilters(creditNotes, { searchKeys: SEARCH_KEYS });
    const statuses = useMemo(() => {
        const seen = [...new Set(creditNotes.map((cn) => cn.status).filter(Boolean))];
        return seen.map((value) => ({ value, label: String(value).replace(/_/g, ' ') }));
    }, [creditNotes]);
    const totalValue = creditNotes.reduce((sum, cn) => sum + (parseFloat(cn.total_amount) || 0), 0);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Credit Notes</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Refunds and invoice adjustments</p>
                    </div>
                    {permissions.includes('credit-notes.create') && (
                        <Link href={route('credit-notes.create-standalone')} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-white bg-mustard hover:bg-mustard/90 text-sm">
                            Standalone credit
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Credit Notes" />

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="relative overflow-hidden bg-mustard text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total Credit Notes</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{creditNotes.length} issued</p>
                        <p className="text-xs text-mustard mt-1">Adjustments and refunds</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Total Value Credited</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Currency /></span>
                        </div>
                        <p className="text-xl font-bold text-terracotta font-mono tabular-nums">
                            RM {totalValue.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-ink-muted mt-1">Net credit issued</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={filters.searchInput}
                        onSearchChange={filters.setSearchInput}
                        searchPlaceholder="Search by CN # or customer..."
                        status={filters.status}
                        statuses={statuses}
                        perPage={filters.perPage}
                        onApply={filters.apply}
                        from={filters.from}
                        to={filters.to}
                        total={filters.total}
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Credit Note</th>
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4 hidden md:table-cell">Reason</th>
                                    <th className="px-6 py-4 text-right">Value</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filters.items.length > 0 ? filters.items.map((cn) => (
                                    <tr key={cn.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors group">
                                        <td className="px-6 py-4">
                                            <Link href={route('credit-notes.show', cn.id)} className="font-semibold text-ink hover:text-terracotta">{cn.cn_number}</Link>
                                            <p className="text-xs text-ink-muted mt-0.5">
                                                {cn.issue_date ? new Date(cn.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                            </p>
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-ink">{cn.customer_name || '—'}</td>
                                        <td className="px-6 py-4 hidden md:table-cell text-xs text-ink-muted">{cn.reason_code || '—'}</td>
                                        <td className="px-6 py-4 text-right">
                                            <span className="font-mono text-sm font-semibold text-terracotta tabular-nums">
                                                - RM {parseFloat(cn.total_amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-mustard/15 text-mustard">
                                                {cn.status || 'posted'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <RowActionsMenu items={[
                                                { label: 'Open', href: route('credit-notes.show', cn.id), icon: <ActionIcons.Open /> },
                                                { label: 'Download PDF', href: route('credit-notes.pdf', cn.id), external: true, icon: <ActionIcons.Pdf /> },
                                                { label: 'Email', icon: <ActionIcons.Mail />, show: permissions.includes('invoices.email'), onClick: () => router.post(route('credit-notes.email', cn.id)) },
                                                { label: 'Edit', href: route('credit-notes.edit', cn.id), icon: <ActionIcons.Pencil />, show: permissions.includes('credit-notes.create') && cn.status !== 'void' },
                                                { label: 'Void', icon: <ActionIcons.Trash />, danger: true, show: cn.status !== 'void', onClick: () => router.post(route('credit-notes.void', cn.id)) },
                                            ]} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center">
                                            <p className="text-ink-muted text-sm font-medium">
                                                {filters.total === 0 && !filters.searchInput && !filters.status ? 'No credit notes issued yet. Create one from an invoice.' : 'No credit notes match your filters.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <IndexPagination currentPage={filters.currentPage} lastPage={filters.lastPage} onPage={(page) => filters.apply({ page })} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
