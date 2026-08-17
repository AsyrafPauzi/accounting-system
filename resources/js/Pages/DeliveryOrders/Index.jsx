import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const STATUSES = [
    { value: 'draft', label: 'Draft' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'invoiced', label: 'Invoiced' },
    { value: 'cancelled', label: 'Cancelled' },
];

function statusBadge(status) {
    const styles = {
        draft: 'bg-surface-alt text-ink',
        delivered: 'bg-mustard/15 text-mustard-dark',
        invoiced: 'bg-forest/10 text-forest',
        cancelled: 'bg-surface-alt text-ink-muted',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, orders, filters = {} }) {
    const rows = orders?.data || [];
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 25 } = filters;
    const [searchInput, setSearchInput] = useState(search);
    const permissions = auth.permissions || [];

    const applyFilters = (overrides = {}) => {
        router.get(route('delivery-orders.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const convert = async (id) => {
        const ok = await confirm({ title: 'Convert to invoice?', text: 'Creates a draft invoice from this delivery.', confirmText: 'Convert', icon: 'question' });
        if (ok) router.post(route('delivery-orders.invoice', id), {});
    };

    const returnOrder = async (id) => {
        const ok = await confirm({ title: 'Return this delivery?', text: 'Marks the delivery as returned.', confirmText: 'Return', confirmColor: '#dc2626', icon: 'warning' });
        if (ok) router.post(route('delivery-orders.return', id));
    };

    const currentPage = orders?.current_page || 1;
    const lastPage = orders?.last_page || 1;
    const from = orders?.from || 0;
    const to = orders?.to || 0;
    const total = orders?.total || 0;

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div>
                <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Delivery Orders</h2>
                <p className="text-ink-muted text-sm font-medium mt-1">Created from a sales order. Open a DO to print the packing note or convert it to an invoice.</p>
            </div>
        }>
            <Head title="Delivery Orders" />
            <div className="space-y-4 min-w-0">
                <div className="bg-terracotta text-white rounded-2xl p-4 shadow-lg max-w-sm">
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Deliveries</span>
                        <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                    </div>
                    <p className="text-xl font-bold tabular-nums">{total}</p>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search DO # or customer..."
                        status={statusFilter}
                        statuses={STATUSES}
                        perPage={perPageFilter}
                        onApply={applyFilters}
                        from={from}
                        to={to}
                        total={total}
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Delivery order</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3">Sales order</th>
                                    <th className="px-4 sm:px-6 py-3">Invoice</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length > 0 ? rows.map((d) => {
                                    const so = d.sales_order || d.salesOrder;
                                    const invoices = d.invoices || [];
                                    return (
                                        <tr key={d.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                            <td className="px-4 sm:px-6 py-3">
                                                <Link href={route('delivery-orders.show', d.id)} className="font-semibold text-ink hover:text-terracotta">{d.do_number}</Link>
                                                <p className="text-xs text-ink-muted mt-0.5">
                                                    {d.issue_date ? new Date(d.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                                </p>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 font-medium">{d.customer?.name || '—'}</td>
                                            <td className="px-4 sm:px-6 py-3">
                                                <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${statusBadge(d.status)}`}>{d.status}</span>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3">
                                                {so ? (
                                                    <Link href={route('sales-orders.show', so.id)} className="text-sm text-terracotta hover:underline">{so.so_number}</Link>
                                                ) : <span className="text-xs text-ink-muted">—</span>}
                                            </td>
                                            <td className="px-4 sm:px-6 py-3">
                                                {invoices.length > 0 ? invoices.map((inv) => (
                                                    <Link key={inv.id} href={route('invoices.show', inv.id)} className="block text-sm text-terracotta hover:underline">{inv.invoice_number}</Link>
                                                )) : <span className="text-xs text-ink-muted">Not invoiced</span>}
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 text-right">
                                                <RowActionsMenu items={[
                                                    { label: 'Open', href: route('delivery-orders.show', d.id), icon: <ActionIcons.Open /> },
                                                    { label: 'Download PDF', href: route('delivery-orders.pdf', d.id), external: true, icon: <ActionIcons.Pdf /> },
                                                    { label: 'Email', icon: <ActionIcons.Mail />, show: permissions.includes('invoices.email'), onClick: () => router.post(route('delivery-orders.email', d.id)) },
                                                    { label: 'Edit', href: route('delivery-orders.edit', d.id), icon: <ActionIcons.Pencil />, show: permissions.includes('delivery-orders.edit') && d.status === 'delivered' },
                                                    { label: 'Convert to invoice', icon: <ActionIcons.Invoice />, show: d.status !== 'invoiced' && d.status !== 'cancelled' && permissions.includes('invoices.create'), onClick: () => convert(d.id) },
                                                    { label: 'Return', icon: <ActionIcons.Return />, danger: true, show: permissions.includes('delivery-orders.edit') && d.status === 'delivered', onClick: () => returnOrder(d.id) },
                                                ]} />
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {search || statusFilter
                                                ? 'No delivery orders match.'
                                                : (
                                                    <>
                                                        No delivery orders yet.{' '}
                                                        {permissions.includes('sales-orders.view') && (
                                                            <Link href={route('sales-orders.index')} className="text-terracotta font-semibold hover:underline">Open a sales order</Link>
                                                        )}
                                                        {' '}and use <span className="font-semibold text-ink">Create delivery order</span>.
                                                    </>
                                                )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <IndexPagination currentPage={currentPage} lastPage={lastPage} onPage={(page) => applyFilters({ page })} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
