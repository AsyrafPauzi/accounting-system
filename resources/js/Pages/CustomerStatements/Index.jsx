import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Statement: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2a4 4 0 014-4h4a4 4 0 014 4v2M3 7h2m0 0h2M5 7v2m0-2V5m9 4a2 2 0 11-4 0 2 2 0 014 0zM7 13H4a1 1 0 00-1 1v6a1 1 0 001 1h3" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

const STATUSES = [
    { value: 'outstanding', label: 'Outstanding' },
    { value: 'settled', label: 'Settled' },
];

export default function Index({
    auth,
    customers,
    filters = {},
    base_currency = 'MYR',
    totalCount = 0,
    outstandingCount = 0,
    settledCount = 0,
    outstandingTotal = 0,
}) {
    const items = customers?.data || [];
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 10 } = filters;
    const [searchInput, setSearchInput] = useState(search);
    const from = customers?.from || 0;
    const to = customers?.to || 0;
    const total = customers?.total || 0;

    const applyFilters = (overrides = {}) => {
        router.get(route('customer-statements.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const emptyMessage = totalCount === 0
        ? 'No customers yet. Add customers under Sales, then come back here.'
        : 'No customers match your filters.';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Customer Statements</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Open a customer to see invoices and payments over a date range</p>
                </div>
            }
        >
            <Head title="Customer Statements" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Customers</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Statement /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{totalCount}</p>
                        <p className="text-xs text-terracotta mt-1">Outstanding · Settled</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Outstanding (AR)</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-terracotta font-mono tabular-nums">{formatCurrency(outstandingTotal, base_currency)}</p>
                        <p className="text-xs text-ink-muted mt-1">{outstandingCount} with open invoices</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Settled</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-forest font-mono tabular-nums">{settledCount}</p>
                        <p className="text-xs text-ink-muted mt-1">No open balance</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search by customer, email, or TIN..."
                        status={statusFilter}
                        statuses={STATUSES}
                        perPage={perPageFilter}
                        onApply={applyFilters}
                        from={from}
                        to={to}
                        total={total}
                    />

                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Outstanding</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length > 0 ? items.map((customer) => {
                                    const due = Number(customer.outstanding_amount || 0);
                                    const open = Number(customer.outstanding_invoices_count || 0);
                                    return (
                                        <tr key={customer.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                            <td className="px-4 sm:px-6 py-3 sm:py-4">
                                                <Link href={route('customer-statements.show', customer.id)} className="block group/link">
                                                    <span className="font-semibold text-ink group-hover/link:text-terracotta">{customer.name}</span>
                                                    <p className="text-xs text-ink-muted mt-0.5 truncate max-w-[220px]">{customer.email || 'No email'}</p>
                                                </Link>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 sm:py-4">
                                                <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${due > 0 ? 'bg-terracotta/10 text-terracotta' : 'bg-forest/10 text-forest'}`}>
                                                    {due > 0 ? 'Outstanding' : 'Settled'}
                                                </span>
                                                {open > 0 && <p className="text-[10px] text-ink-muted mt-1">{open} open invoice{open === 1 ? '' : 's'}</p>}
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                                <div className={`font-mono text-sm font-semibold tabular-nums ${due > 0 ? 'text-terracotta' : 'text-ink'}`}>
                                                    {formatCurrency(due, base_currency)}
                                                </div>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                                <RowActionsMenu items={[
                                                    { label: 'Open', href: route('customer-statements.show', customer.id), icon: <ActionIcons.Open /> },
                                                    { label: 'Preview PDF', href: route('customer-statements.preview', customer.id), icon: <ActionIcons.Pdf />, external: true },
                                                    { label: 'Download PDF', href: route('customer-statements.pdf', customer.id), icon: <ActionIcons.Pdf />, external: true },
                                                ]} />
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-16 text-center text-ink-muted text-sm">{emptyMessage}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {items.length > 0 ? items.map((customer) => {
                            const due = Number(customer.outstanding_amount || 0);
                            return (
                                <div key={customer.id} className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <Link href={route('customer-statements.show', customer.id)} className="font-semibold text-ink hover:text-terracotta">{customer.name}</Link>
                                            <p className="text-xs text-ink-muted mt-0.5">{customer.email || 'No email'}</p>
                                            <p className={`text-sm font-mono font-semibold mt-1 ${due > 0 ? 'text-terracotta' : 'text-ink'}`}>{formatCurrency(due, base_currency)}</p>
                                            <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${due > 0 ? 'bg-terracotta/10 text-terracotta' : 'bg-forest/10 text-forest'}`}>
                                                {due > 0 ? 'Outstanding' : 'Settled'}
                                            </span>
                                        </div>
                                        <RowActionsMenu items={[
                                            { label: 'Open', href: route('customer-statements.show', customer.id), icon: <ActionIcons.Open /> },
                                            { label: 'Preview PDF', href: route('customer-statements.preview', customer.id), icon: <ActionIcons.Pdf />, external: true },
                                            { label: 'Download PDF', href: route('customer-statements.pdf', customer.id), icon: <ActionIcons.Pdf />, external: true },
                                        ]} />
                                    </div>
                                </div>
                            );
                        }) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">{emptyMessage}</div>
                        )}
                    </div>

                    <IndexPagination
                        currentPage={customers?.current_page || 1}
                        lastPage={customers?.last_page || 1}
                        onPage={(page) => applyFilters({ page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
