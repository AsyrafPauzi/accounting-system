import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    Statement: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2a4 4 0 014-4h4a4 4 0 014 4v2M3 7h2m0 0h2M5 7v2m0-2V5m9 4a2 2 0 11-4 0 2 2 0 014 0zM7 13H4a1 1 0 00-1 1v6a1 1 0 001 1h3" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    ArrowRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>,
};

export default function Index({ auth, suppliers, filters = {}, base_currency = 'MYR' }) {
    const items = suppliers?.data || [];
    const [search, setSearch] = useState(filters.search || '');
    const filteredItems = items.filter((supplier) =>
        (supplier.name || '').toLowerCase().includes(search.toLowerCase())
    );

    const apply = () => {
        router.get(route('supplier-statements.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center gap-3">
                    <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                        <Icons.Statement />
                    </span>
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Supplier Statements</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Pick a supplier to see bills and payments over a date range</p>
                    </div>
                </div>
            }
        >
            <Head title="Supplier Statements" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="p-4 sm:p-6 border-b border-border-warm">
                        <div className="max-w-md relative">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && apply()}
                                onBlur={apply}
                                placeholder="Search by supplier name"
                                className="w-full pl-10 pr-4 py-2.5 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Supplier</th>
                                    <th className="px-6 py-3 text-right">Outstanding</th>
                                    <th className="px-6 py-3 text-right">Statement</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {filteredItems.length > 0 ? filteredItems.map((supplier) => (
                                    <tr key={supplier.id} className="hover:bg-cream/40 transition-colors">
                                        <td className="px-6 py-4">
                                            <p className="font-semibold text-ink">{supplier.name}</p>
                                            <div className="flex items-center gap-2 mt-0.5 text-[11px] text-ink-muted">
                                                {supplier.email && <span>{supplier.email}</span>}
                                                {supplier.tin && <span className="font-mono">· TIN {supplier.tin}</span>}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums">
                                            <span className={Number(supplier.outstanding_amount || 0) > 0 ? 'text-terracotta font-semibold' : 'text-ink-muted'}>
                                                {formatCurrency(supplier.outstanding_amount || 0, base_currency)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link
                                                href={route('supplier-statements.show', supplier.id)}
                                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-terracotta hover:bg-terracotta-dark transition-colors"
                                            >
                                                View statement <Icons.ArrowRight />
                                            </Link>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="3" className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3 text-ink-muted">
                                                <span className="p-4 bg-surface-alt rounded-xl text-terracotta">
                                                    <Icons.Statement />
                                                </span>
                                                <div>
                                                    <p className="font-semibold text-ink">No suppliers found</p>
                                                    <p className="text-sm mt-1">Add suppliers under Purchases → Suppliers, then come back here.</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {suppliers?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between text-xs text-ink-muted">
                            <span>Showing {suppliers.from || 0}–{suppliers.to || 0} of {suppliers.total}</span>
                            <div className="flex items-center gap-2">
                                {suppliers.links?.filter((link) => link.url).map((link, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        onClick={() => router.visit(link.url, { preserveState: true })}
                                        disabled={link.active}
                                        className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold ${link.active ? 'bg-terracotta text-white' : 'bg-surface-alt text-ink hover:bg-cream'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
