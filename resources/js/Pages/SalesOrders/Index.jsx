import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { confirm } from '@/utils/swal';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

function statusBadge(status) {
    const styles = {
        draft: 'bg-surface-alt text-ink',
        confirmed: 'bg-mustard/15 text-mustard-dark',
        partially_delivered: 'bg-terracotta/10 text-terracotta',
        delivered: 'bg-forest/10 text-forest',
        invoiced: 'bg-forest/10 text-forest',
        cancelled: 'bg-surface-alt text-ink-muted',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, orders, base_currency = 'MYR' }) {
    const rows = orders?.data || orders || [];
    const [search, setSearch] = useState('');
    const filtered = rows.filter((so) =>
        (so.so_number || '').toLowerCase().includes(search.toLowerCase()) ||
        (so.customer?.name || '').toLowerCase().includes(search.toLowerCase())
    );

    const convert = async (id) => {
        const ok = await confirm({ title: 'Convert to invoice?', text: 'Creates a draft invoice from remaining uninvoiced quantities.', confirmText: 'Convert', icon: 'question' });
        if (ok) router.post(route('sales-orders.invoice', id), {});
    };

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Sales Orders</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Confirm the order, then deliver and invoice from the same document</p>
                </div>
                {auth.permissions.includes('sales-orders.create') && (
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('sales-orders.batch')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                            Batch
                        </Link>
                        <Link href={route('sales-orders.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta shadow-lg">
                            <Icons.Plus /> New sales order
                        </Link>
                    </div>
                )}
            </div>
        }>
            <Head title="Sales Orders" />
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
                            <input type="text" placeholder="Search SO # or customer..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 w-full border border-border-warm rounded-xl py-2 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <span className="text-ink-muted text-sm ml-auto">{filtered.length} shown</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Sales order</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 hidden lg:table-cell">Linked</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Amount</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length > 0 ? filtered.map((so) => {
                                    const dos = so.delivery_orders || so.deliveryOrders || [];
                                    const invoices = so.invoices || [];
                                    return (
                                        <tr key={so.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                            <td className="px-4 sm:px-6 py-3">
                                                <Link href={route('sales-orders.show', so.id)} className="font-semibold text-ink hover:text-terracotta">{so.so_number}</Link>
                                                <p className="text-xs text-ink-muted mt-0.5">
                                                    {so.issue_date ? new Date(so.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                                </p>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 font-medium">{so.customer?.name || '—'}</td>
                                            <td className="px-4 sm:px-6 py-3">
                                                <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${statusBadge(so.status)}`}>{String(so.status || '').replace(/_/g, ' ')}</span>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 hidden lg:table-cell text-xs space-y-1">
                                                {dos.map((d) => (
                                                    <Link key={d.id} href={route('delivery-orders.show', d.id)} className="block text-terracotta hover:underline">{d.do_number}</Link>
                                                ))}
                                                {invoices.map((inv) => (
                                                    <Link key={inv.id} href={route('invoices.show', inv.id)} className="block text-terracotta hover:underline">{inv.invoice_number}</Link>
                                                ))}
                                                {dos.length === 0 && invoices.length === 0 && <span className="text-ink-muted">None yet</span>}
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 text-right font-mono text-sm font-semibold">{formatCurrency(so.total_amount, so.currency || base_currency)}</td>
                                            <td className="px-4 sm:px-6 py-3 text-right">
                                                <div className="inline-flex items-center gap-1">
                                                    <Link href={route('sales-orders.show', so.id)} className="px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt">Open</Link>
                                                    <a href={route('sales-orders.pdf', so.id)} target="_blank" rel="noreferrer" className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm hover:bg-cream">PDF</a>
                                                    {so.status !== 'invoiced' && so.status !== 'cancelled' && auth.permissions.includes('invoices.create') && (
                                                        <button type="button" onClick={() => convert(so.id)} className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm hover:bg-cream">Invoice</button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr><td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">{search ? 'No sales orders match.' : 'No sales orders yet.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
