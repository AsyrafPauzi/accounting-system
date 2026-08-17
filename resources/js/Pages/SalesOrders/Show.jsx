import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import DocumentTrail from '@/Components/DocumentTrail';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, order, document_trail = [] }) {
    const currency = order.currency || 'MYR';
    const [qtys, setQtys] = useState(
        Object.fromEntries((order.items || []).map((i) => [i.id, Math.max(0, Number(i.quantity) - Number(i.qty_delivered || 0))]))
    );

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={order.so_number} />
            <div className="space-y-4 min-w-0">
                <Link href={route('sales-orders.index')} className="text-xs font-semibold text-ink-muted">← Sales orders</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{order.so_number}</h1>
                        <p className="text-sm text-ink-muted">{order.customer?.name} · {order.status}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a className={btn} href={route('sales-orders.pdf', order.id)} target="_blank" rel="noreferrer">PDF</a>
                        {auth.permissions.includes('sales-orders.edit') && !['delivered', 'invoiced', 'cancelled'].includes(order.status) && (
                            <Link className={btn} href={route('sales-orders.edit', order.id)}>Edit</Link>
                        )}
                        {auth.permissions.includes('invoices.email') && (
                            <button type="button" className={btn} onClick={() => router.post(route('sales-orders.email', order.id))}>Email</button>
                        )}
                        {auth.permissions.includes('sales-orders.edit') && !['cancelled', 'invoiced'].includes(order.status) && (
                            <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('sales-orders.cancel', order.id))}>Cancel order</button>
                        )}
                        {order.status !== 'cancelled' && order.status !== 'invoiced' && (
                            <button type="button" className={primary} onClick={() => router.post(route('sales-orders.invoice', order.id), {})}>Convert to invoice</button>
                        )}
                    </div>
                </div>
                <DocumentTrail steps={document_trail} />
                <div className="bg-surface rounded-2xl border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/50 text-[10px] uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Item</th>
                                <th className="px-3 py-3 text-right">Ordered</th>
                                <th className="px-3 py-3 text-right">Delivered</th>
                                <th className="px-3 py-3 text-right">Invoiced</th>
                                <th className="px-3 py-3 text-right">Deliver now</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(order.items || []).map((i) => (
                                <tr key={i.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div>{i.description}</div>
                                        <div className="text-xs text-ink-muted font-mono">{formatCurrency(i.unit_price, currency)}</div>
                                    </td>
                                    <td className="px-3 py-3 text-right font-mono">{i.quantity}</td>
                                    <td className="px-3 py-3 text-right font-mono">{i.qty_delivered}</td>
                                    <td className="px-3 py-3 text-right font-mono">{i.qty_invoiced}</td>
                                    <td className="px-3 py-3 text-right">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className="w-24 border rounded-lg px-2 py-1 text-right font-mono"
                                            value={qtys[i.id] ?? 0}
                                            onChange={(e) => setQtys({ ...qtys, [i.id]: e.target.value })}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <button
                    type="button"
                    className={btn}
                    disabled={order.status === 'cancelled' || order.status === 'invoiced'}
                    onClick={() => router.post(route('sales-orders.deliver', order.id), { quantities: qtys })}
                >
                    Create delivery order
                </button>
                {(order.delivery_orders || order.deliveryOrders || []).length > 0 && (
                    <div className="text-sm space-y-1">
                        <h3 className="font-semibold">Delivery orders</h3>
                        {(order.delivery_orders || order.deliveryOrders).map((d) => (
                            <Link key={d.id} className="block text-terracotta" href={route('delivery-orders.show', d.id)}>{d.do_number} · {d.status}</Link>
                        ))}
                    </div>
                )}
                {(order.invoices || []).length > 0 && (
                    <div className="text-sm space-y-1">
                        <h3 className="font-semibold">Invoices</h3>
                        {order.invoices.map((inv) => (
                            <Link key={inv.id} className="block text-terracotta" href={route('invoices.show', inv.id)}>{inv.invoice_number} · {inv.status}</Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
