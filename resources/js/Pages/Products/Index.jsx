import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Box: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14L12 11m0 0L4 7m8 4v10" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Pause: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

const STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function Index({
    auth,
    products,
    filters = {},
    totalCount = 0,
    activeCount = 0,
    inactiveCount = 0,
}) {
    const items = products?.data || [];
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 10 } = filters;
    const [searchInput, setSearchInput] = useState(search);
    const from = products?.from || 0;
    const to = products?.to || 0;
    const total = products?.total || 0;
    const currentPage = products?.current_page || 1;
    const lastPage = products?.last_page || 1;
    const canCreate = auth.permissions.includes('products.create');
    const canEdit = auth.permissions.includes('products.edit');
    const canDelete = auth.permissions.includes('products.delete');

    const applyFilters = (overrides = {}) => {
        router.get(route('products.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const handleDelete = async (product) => {
        const ok = await confirm({
            title: 'Remove from catalogue?',
            text: `Remove "${product.name}" from the product list? Existing invoices that used it stay intact.`,
            confirmText: 'Remove',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('products.destroy', product.id));
    };

    const emptyMessage = totalCount === 0
        ? 'No products yet. Create your first reusable line item to speed up invoicing.'
        : 'No products match your filters.';

    const Actions = ({ product }) => (
        <RowActionsMenu items={[
            { label: 'Edit', href: route('products.edit', product.id), icon: <ActionIcons.Pencil />, show: canEdit },
            { label: 'Remove', icon: <ActionIcons.Trash />, danger: true, show: canDelete, onClick: () => handleDelete(product) },
        ]} />
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Products & Services</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Reusable line items for invoices and bills</p>
                    </div>
                    {canCreate && (
                        <Link
                            href={route('products.create')}
                            className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200"
                        >
                            <Icons.Plus /> New product
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Products & Services" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Catalogue</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Box /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{totalCount}</p>
                        <p className="text-xs text-terracotta mt-1">Active · Inactive</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Active</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-forest font-mono tabular-nums">{activeCount}</p>
                        <p className="text-xs text-ink-muted mt-1">Shown on new invoices</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Inactive</span>
                            <span className="p-2 rounded-xl bg-surface-alt text-ink-muted"><Icons.Pause /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-ink font-mono tabular-nums">{inactiveCount}</p>
                        <p className="text-xs text-ink-muted mt-1">Hidden from pickers</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search by name, code, or description..."
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
                                    <th className="px-4 sm:px-6 py-3">Product</th>
                                    <th className="px-4 sm:px-6 py-3">Account</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Price</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length > 0 ? items.map((product) => (
                                    <tr key={product.id} className={`border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors ${product.is_active ? '' : 'opacity-60'}`}>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <Link href={canEdit ? route('products.edit', product.id) : '#'} className="block group/link">
                                                <span className="font-semibold text-ink group-hover/link:text-terracotta">{product.name}</span>
                                                <p className="text-xs text-ink-muted mt-0.5 font-mono">{product.code || product.description || '—'}</p>
                                            </Link>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <div className="font-mono text-sm text-ink">{product.account_code || '—'}</div>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${product.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                                {product.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <div className="font-mono text-sm font-semibold text-ink">{formatCurrency(product.unit_price, 'MYR')}</div>
                                            {Number(product.tax_rate) > 0 && (
                                                <p className="text-xs text-ink-muted tabular-nums">{Number(product.tax_rate).toFixed(2)}% tax</p>
                                            )}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <Actions product={product} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-16 text-center text-ink-muted text-sm">{emptyMessage}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {items.length > 0 ? items.map((product) => (
                            <div key={product.id} className={`p-4 ${product.is_active ? '' : 'opacity-60'}`}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link href={canEdit ? route('products.edit', product.id) : '#'} className="font-semibold text-ink hover:text-terracotta">{product.name}</Link>
                                        <p className="text-xs text-ink-muted mt-0.5 font-mono">{product.code || product.account_code || '—'}</p>
                                        <p className="text-sm font-mono font-semibold text-ink mt-1">{formatCurrency(product.unit_price, 'MYR')}</p>
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${product.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                            {product.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    <Actions product={product} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">{emptyMessage}</div>
                        )}
                    </div>

                    <IndexPagination
                        currentPage={currentPage}
                        lastPage={lastPage}
                        onPage={(page) => applyFilters({ page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
