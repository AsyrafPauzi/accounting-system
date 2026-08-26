import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';

export default function Index({ auth, products, filters = {} }) {
    const items = products?.data || [];
    const { search = '', per_page: perPageFilter = 25 } = filters;
    const [searchInput, setSearchInput] = useState(search);

    const applyFilters = (overrides = {}) => {
        router.get(route('inventory.index'), {
            search: overrides.search ?? searchInput,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Stock on hand</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Weighted-average cost for products that track inventory</p>
                    </div>
                    <Link
                        href={route('products.index')}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-ink bg-white border border-ink/10 rounded-lg hover:bg-ink/5"
                    >
                        Manage products
                    </Link>
                </div>
            }
        >
            <Head title="Inventory" />

            <div className="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <IndexFilterBar
                    searchInput={searchInput}
                    onSearchInputChange={setSearchInput}
                    onSearch={() => applyFilters()}
                    searchPlaceholder="Search by name or code…"
                    perPage={perPageFilter}
                    onPerPageChange={(per_page) => applyFilters({ per_page })}
                />

                <div className="bg-white border border-ink/10 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-ink/10">
                            <thead className="bg-ink/5">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase tracking-wider">Product</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase tracking-wider">Code</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase tracking-wider">Qty on hand</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase tracking-wider">Avg cost</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase tracking-wider">Stock value</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-ink/10">
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-12 text-center text-sm text-ink-muted">
                                            No inventory-tracked products yet. Enable &quot;Track inventory&quot; on a product to see it here.
                                        </td>
                                    </tr>
                                ) : items.map((product) => {
                                    const qty = Number(product.qty_on_hand || 0);
                                    const avg = Number(product.avg_cost || 0);
                                    const value = qty * avg;

                                    return (
                                        <tr key={product.id} className="hover:bg-ink/[0.02]">
                                            <td className="px-4 py-3 text-sm font-medium text-ink">{product.name}</td>
                                            <td className="px-4 py-3 text-sm text-ink-muted">{product.code || '—'}</td>
                                            <td className="px-4 py-3 text-sm text-ink text-right tabular-nums">{qty.toLocaleString(undefined, { maximumFractionDigits: 4 })}</td>
                                            <td className="px-4 py-3 text-sm text-ink text-right tabular-nums">{formatCurrency(avg)}</td>
                                            <td className="px-4 py-3 text-sm font-medium text-ink text-right tabular-nums">{formatCurrency(value)}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <IndexPagination
                        from={products?.from}
                        to={products?.to}
                        total={products?.total}
                        currentPage={products?.current_page}
                        lastPage={products?.last_page}
                        onPageChange={(page) => applyFilters({ page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
