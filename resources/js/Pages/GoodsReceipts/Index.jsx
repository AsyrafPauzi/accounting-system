import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

function statusBadge(status) {
    const styles = {
        received: 'bg-mustard/15 text-mustard-dark',
        billed: 'bg-forest/10 text-forest',
        cancelled: 'bg-surface-alt text-ink-muted',
    };

    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, orders }) {
    const rows = orders?.data || orders || [];
    const [search, setSearch] = useState('');
    const filtered = rows.filter((d) =>
        (d.grn_number || '').toLowerCase().includes(search.toLowerCase()) ||
        (d.supplier?.name || '').toLowerCase().includes(search.toLowerCase())
    );

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
                    <p className="text-xl font-bold tabular-nums">{orders?.total ?? rows.length}</p>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm flex items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-0 max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder="Search GRN # or supplier..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 w-full border border-border-warm rounded-xl py-2 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <span className="text-ink-muted text-sm ml-auto">{filtered.length} shown</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">GRN</th>
                                    <th className="px-4 sm:px-6 py-3">Supplier</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length > 0 ? filtered.map((d) => (
                                    <tr key={d.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                        <td className="px-4 sm:px-6 py-3">
                                            <Link href={route('goods-receipts.show', d.id)} className="font-semibold text-ink hover:text-terracotta">{d.grn_number}</Link>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 font-medium">{d.supplier?.name || '—'}</td>
                                        <td className="px-4 sm:px-6 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${statusBadge(d.status)}`}>{d.status}</span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 text-right">
                                            <Link href={route('goods-receipts.show', d.id)} className="inline-flex px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt">Open</Link>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {search ? 'No goods receipts match.' : 'No goods receipts yet. Create one from a purchase order.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
