import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import DocumentTrail from '@/Components/DocumentTrail';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, order, document_trail = [] }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={order.do_number} />
            <div className="space-y-4 min-w-0">
                <Link href={route('delivery-orders.index')} className="text-xs font-semibold text-ink-muted">← Delivery orders</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{order.do_number}</h1>
                        <p className="text-sm text-ink-muted">{order.customer?.name} · {order.status}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(order.sales_order || order.salesOrder) && (
                            <Link className={btn} href={route('sales-orders.show', (order.sales_order || order.salesOrder).id)}>
                                Open {(order.sales_order || order.salesOrder).so_number}
                            </Link>
                        )}
                        <a className={btn} href={route('delivery-orders.pdf', order.id)} target="_blank" rel="noreferrer">PDF</a>
                        {auth.permissions.includes('delivery-orders.edit') && order.status === 'delivered' && (
                            <Link className={btn} href={route('delivery-orders.edit', order.id)}>Edit</Link>
                        )}
                        {auth.permissions.includes('invoices.email') && (
                            <button type="button" className={btn} onClick={() => router.post(route('delivery-orders.email', order.id))}>Email</button>
                        )}
                        {auth.permissions.includes('delivery-orders.edit') && order.status === 'delivered' && (
                            <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('delivery-orders.return', order.id))}>Return</button>
                        )}
                        {order.status !== 'invoiced' && order.status !== 'cancelled' && (
                            <button type="button" className={primary} onClick={() => router.post(route('delivery-orders.invoice', order.id), {})}>Convert to invoice</button>
                        )}
                    </div>
                </div>
                <DocumentTrail steps={document_trail} />
                <div className="bg-surface rounded-2xl border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/50 text-[10px] uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Item</th>
                                <th className="px-4 py-3 text-right">Qty delivered</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(order.items || []).map((i) => (
                                <tr key={i.id} className="border-t">
                                    <td className="px-4 py-3">{i.description}</td>
                                    <td className="px-4 py-3 text-right font-mono">{i.quantity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {(order.invoices || []).length > 0 && (
                    <div className="text-sm space-y-1">
                        <h3 className="font-semibold">Invoices</h3>
                        {order.invoices.map((inv) => (
                            <Link key={inv.id} className="block text-terracotta" href={route('invoices.show', inv.id)}>{inv.invoice_number}</Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
