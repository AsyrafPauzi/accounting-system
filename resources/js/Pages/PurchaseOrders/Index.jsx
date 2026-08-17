import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

function statusBadge(status) {
    const styles = {
        draft: 'bg-surface-alt text-ink',
        confirmed: 'bg-mustard/15 text-mustard-dark',
        partially_received: 'bg-terracotta/10 text-terracotta',
        received: 'bg-forest/10 text-forest',
        billed: 'bg-forest/10 text-forest',
        cancelled: 'bg-surface-alt text-ink-muted',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, orders, base_currency = 'MYR' }) {
    const rows = orders?.data || orders || [];
    const [search, setSearch] = useState('');
    const filtered = rows.filter((po) =>
        (po.po_number || '').toLowerCase().includes(search.toLowerCase()) ||
        (po.supplier?.name || '').toLowerCase().includes(search.toLowerCase())
    );

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Purchase Orders</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Confirm the order, then receive goods and convert to a bill from the same document</p>
                </div>
                {auth.permissions.includes('bills.create') && (
                    <Link href={route('purchase-orders.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta shadow-lg">
                        <Icons.Plus /> New purchase order
                    </Link>
                )}
            </div>
        }>
            <Head title="Purchase Orders" />
            <div className="space-y-4 min-w-0">
                <div className="bg-terracotta text-white rounded-2xl p-4 shadow-lg max-w-sm">
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Orders</span>
                        <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                    </div>
                    <p className="text-xl font-bold tabular-nums">{orders?.total ?? rows.length}</p>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm flex items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-0 max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder="Search PO # or supplier..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 w-full border border-border-warm rounded-xl py-2 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <span className="text-ink-muted text-sm ml-auto">{filtered.length} shown</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Purchase order</th>
                                    <th className="px-4 sm:px-6 py-3">Supplier</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Total</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length > 0 ? filtered.map((po) => (
                                    <tr key={po.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                        <td className="px-4 sm:px-6 py-3">
                                            <Link href={route('purchase-orders.show', po.id)} className="font-semibold text-ink hover:text-terracotta">{po.po_number}</Link>
                                            <p className="text-xs text-ink-muted mt-0.5">
                                                {po.issue_date ? new Date(po.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                            </p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 font-medium">{po.supplier?.name || '—'}</td>
                                        <td className="px-4 sm:px-6 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${statusBadge(po.status)}`}>{String(po.status || '').replace(/_/g, ' ')}</span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 text-right font-mono text-sm font-semibold">{formatCurrency(po.total_amount, po.currency || base_currency)}</td>
                                        <td className="px-4 sm:px-6 py-3 text-right">
                                            <Link href={route('purchase-orders.show', po.id)} className="inline-flex px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt">Open</Link>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan={5} className="px-6 py-16 text-center text-ink-muted text-sm">{search ? 'No purchase orders match.' : 'No purchase orders yet.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
