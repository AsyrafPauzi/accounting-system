import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';

const Icons = {
    Box: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14L12 11m0 0L4 7m8 4v10" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

const fmt = (n) => 'RM ' + (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Index({ auth, products, filters = {} }) {
    const items = products?.data || [];
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const apply = (next = {}) => {
        router.get(route('products.index'), {
            search: next.search ?? search,
            status: next.status ?? status,
        }, { preserveState: true, replace: true });
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

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Box />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Products & Services</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">A catalogue of reusable invoice line items so you don't retype the same thing</p>
                        </div>
                    </div>
                    {auth.permissions.includes('products.create') && (
                        <Link
                            href={route('products.create')}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200"
                        >
                            <Icons.Plus /> New product
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Products & Services" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="p-4 sm:p-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between border-b border-border-warm">
                        <div className="flex-1 max-w-md relative">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && apply({ search })}
                                onBlur={() => apply({ search })}
                                placeholder="Search by name, code, or description"
                                className="w-full pl-10 pr-4 py-2.5 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            {[
                                { value: 'all', label: 'All' },
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ].map(opt => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => { setStatus(opt.value); apply({ status: opt.value }); }}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${status === opt.value
                                        ? 'bg-terracotta text-white'
                                        : 'bg-surface-alt text-ink hover:bg-cream'
                                    }`}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Product</th>
                                    <th className="px-6 py-3">Default account</th>
                                    <th className="px-6 py-3 text-right">Unit price</th>
                                    <th className="px-6 py-3 text-right">Tax %</th>
                                    <th className="px-6 py-3 text-center">Status</th>
                                    <th className="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {items.length > 0 ? items.map((p) => (
                                    <tr key={p.id} className="hover:bg-cream/40 transition-colors">
                                        <td className="px-6 py-4">
                                            <Link href={auth.permissions.includes('products.edit') ? route('products.edit', p.id) : '#'} className="block">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-semibold text-ink">{p.name}</span>
                                                    {p.code && (
                                                        <span className="text-[10px] font-mono uppercase tracking-wider bg-surface-alt text-ink-muted px-1.5 py-0.5 rounded">{p.code}</span>
                                                    )}
                                                </div>
                                                {p.description && (
                                                    <p className="text-xs text-ink-muted mt-0.5 line-clamp-1 max-w-md">{p.description}</p>
                                                )}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink-muted font-mono">
                                            {p.account_code || <span className="text-ink-muted/60">—</span>}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">{fmt(p.unit_price)}</td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-ink-muted">
                                            {Number(p.tax_rate) > 0 ? `${Number(p.tax_rate).toFixed(2)}%` : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${p.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                                {p.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="inline-flex items-center gap-1">
                                                {auth.permissions.includes('products.edit') && (
                                                    <Link href={route('products.edit', p.id)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg" title="Edit">
                                                        <Icons.Pencil />
                                                    </Link>
                                                )}
                                                {auth.permissions.includes('products.delete') && (
                                                    <button type="button" onClick={() => handleDelete(p)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-terracotta/10 rounded-lg" title="Remove">
                                                        <Icons.Trash />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3 text-ink-muted">
                                                <span className="p-4 bg-surface-alt rounded-xl text-terracotta">
                                                    <Icons.Box />
                                                </span>
                                                <div>
                                                    <p className="font-semibold text-ink">No products yet</p>
                                                    <p className="text-sm mt-1">Create your first reusable line item to speed up invoicing.</p>
                                                </div>
                                                {auth.permissions.includes('products.create') && (
                                                    <Link href={route('products.create')} className="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark text-sm">
                                                        <Icons.Plus /> Create product
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {products?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between text-xs text-ink-muted">
                            <span>Showing {products.from || 0}–{products.to || 0} of {products.total}</span>
                            <div className="flex items-center gap-2">
                                {products.links?.filter(l => l.url).map((link, idx) => (
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
