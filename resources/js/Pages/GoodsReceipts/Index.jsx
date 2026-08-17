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
    { value: 'received', label: 'Received' },
    { value: 'billed', label: 'Billed' },
    { value: 'cancelled', label: 'Cancelled' },
];

function statusBadge(status) {
    const styles = {
        received: 'bg-mustard/15 text-mustard-dark',
        billed: 'bg-forest/10 text-forest',
        cancelled: 'bg-surface-alt text-ink-muted',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, orders, filters = {} }) {
    const rows = orders?.data || [];
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 25 } = filters;
    const [searchInput, setSearchInput] = useState(search);

    const applyFilters = (overrides = {}) => {
        router.get(route('goods-receipts.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const convert = async (id) => {
        const ok = await confirm({ title: 'Convert to bill?', text: 'Creates a draft bill from this goods receipt.', confirmText: 'Convert', icon: 'question' });
        if (ok) router.post(route('goods-receipts.bill', id), {});
    };

    const returnReceipt = async (id) => {
        const ok = await confirm({ title: 'Return this goods receipt?', text: 'Marks the receipt as returned.', confirmText: 'Return', confirmColor: '#dc2626', icon: 'warning' });
        if (ok) router.post(route('goods-receipts.return', id));
    };

    const currentPage = orders?.current_page || 1;
    const lastPage = orders?.last_page || 1;
    const from = orders?.from || 0;
    const to = orders?.to || 0;
    const total = orders?.total || 0;

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div>
                <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Goods Receipts</h2>
                <p className="text-ink-muted text-sm font-medium mt-1">Created from a purchase order. Open a receipt to return it or convert to a bill.</p>
            </div>
        }>
            <Head title="Goods Receipts" />
            <div className="space-y-4 min-w-0">
                <div className="bg-terracotta text-white rounded-2xl p-4 shadow-lg max-w-sm">
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Receipts</span>
                        <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                    </div>
                    <p className="text-xl font-bold tabular-nums">{total}</p>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search GRN # or supplier..."
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
                                    <th className="px-4 sm:px-6 py-3">GRN</th>
                                    <th className="px-4 sm:px-6 py-3">Supplier</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length > 0 ? rows.map((d) => (
                                    <tr key={d.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                        <td className="px-4 sm:px-6 py-3">
                                            <Link href={route('goods-receipts.show', d.id)} className="font-semibold text-ink hover:text-terracotta">{d.grn_number}</Link>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 font-medium">{d.supplier?.name || '—'}</td>
                                        <td className="px-4 sm:px-6 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${statusBadge(d.status)}`}>{d.status}</span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 text-right">
                                            <RowActionsMenu items={[
                                                { label: 'Open', href: route('goods-receipts.show', d.id), icon: <ActionIcons.Open /> },
                                                { label: 'Download PDF', href: route('goods-receipts.pdf', d.id), external: true, icon: <ActionIcons.Pdf /> },
                                                { label: 'Email', icon: <ActionIcons.Mail />, onClick: () => router.post(route('goods-receipts.email', d.id)) },
                                                { label: 'Convert to bill', icon: <ActionIcons.Bill />, show: d.status !== 'billed' && d.status !== 'cancelled', onClick: () => convert(d.id) },
                                                { label: 'Return', icon: <ActionIcons.Return />, danger: true, show: d.status === 'received', onClick: () => returnReceipt(d.id) },
                                            ]} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {search || statusFilter ? 'No goods receipts match.' : 'No goods receipts yet. Create one from a purchase order.'}
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
