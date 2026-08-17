import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

function statusBadge(status) {
    const styles = {
        draft: 'bg-surface-alt text-ink',
        delivered: 'bg-mustard/15 text-mustard-dark',
        invoiced: 'bg-forest/10 text-forest',
        cancelled: 'bg-surface-alt text-ink-muted',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, orders }) {
    const rows = orders?.data || orders || [];
    const [search, setSearch] = useState('');
    const filtered = rows.filter((d) =>
        (d.do_number || '').toLowerCase().includes(search.toLowerCase()) ||
        (d.customer?.name || '').toLowerCase().includes(search.toLowerCase())
    );

    const convert = async (id) => {
        const ok = await confirm({ title: 'Convert to invoice?', text: 'Creates a draft invoice from this delivery.', confirmText: 'Convert', icon: 'question' });
        if (ok) router.post(route('delivery-orders.invoice', id), {});
    };

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
                    <p className="text-xl font-bold tabular-nums">{orders?.total ?? rows.length}</p>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm flex items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-0 max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder="Search DO # or customer..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 w-full border border-border-warm rounded-xl py-2 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <span className="text-ink-muted text-sm ml-auto">{filtered.length} shown</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Delivery order</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3">Sales order</th>
                                    <th className="px-4 sm:px-6 py-3">Invoice</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length > 0 ? filtered.map((d) => {
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
                                                <div className="inline-flex items-center gap-1">
                                                    <Link href={route('delivery-orders.show', d.id)} className="px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt">Open</Link>
                                                    <a href={route('delivery-orders.pdf', d.id)} target="_blank" rel="noreferrer" className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm hover:bg-cream">PDF</a>
                                                    {d.status !== 'invoiced' && d.status !== 'cancelled' && auth.permissions.includes('invoices.create') && (
                                                        <button type="button" onClick={() => convert(d.id)} className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm hover:bg-cream">Invoice</button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {search
                                                ? 'No delivery orders match.'
                                                : (
                                                    <>
                                                        No delivery orders yet.{' '}
                                                        {auth.permissions.includes('sales-orders.view') && (
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
