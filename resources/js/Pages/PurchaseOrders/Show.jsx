import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import DocumentTrail from '@/Components/DocumentTrail';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, order, trail = [], editable = false, lock_reason = null }) {
    const currency = order.currency || 'MYR';
    const [qtys, setQtys] = useState(
        Object.fromEntries((order.items || []).map((i) => [i.id, Math.max(0, Number(i.quantity) - Number(i.qty_received || 0))]))
    );

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={order.po_number} />
            <div className="space-y-4 min-w-0">
                <Link href={route('purchase-orders.index')} className="text-xs font-semibold text-ink-muted">← Purchase orders</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{order.po_number}</h1>
                        <p className="text-sm text-ink-muted">{order.supplier?.name} · {order.status}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {editable && <Link className={btn} href={route('purchase-orders.edit', order.id)}>Edit</Link>}
                        <a className={btn} href={route('purchase-orders.pdf', order.id)} target="_blank" rel="noreferrer">PDF</a>
                        <button type="button" className={btn} onClick={() => router.post(route('purchase-orders.email', order.id))}>Email</button>
                        {order.status !== 'cancelled' && order.status !== 'billed' && (
                            <button type="button" className={btn} onClick={() => router.post(route('purchase-orders.cancel', order.id))}>Cancel</button>
                        )}
                        {order.status !== 'cancelled' && <button type="button" className={primary} onClick={() => router.post(route('purchase-orders.bill', order.id), {})}>Convert to bill</button>}
                    </div>
                </div>
                {lock_reason && <p className="text-xs text-ink-muted">{lock_reason}</p>}
                <DocumentTrail steps={trail} />
                <div className="bg-surface rounded-2xl border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/80 text-[10px] font-display font-medium uppercase tracking-widest text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Item</th>
                                <th className="px-3 py-3 text-right">Ordered</th>
                                <th className="px-3 py-3 text-right">Received</th>
                                <th className="px-3 py-3 text-right">Billed</th>
                                <th className="px-3 py-3 text-right">Receive now</th>
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
                                    <td className="px-3 py-3 text-right font-mono">{i.qty_received}</td>
                                    <td className="px-3 py-3 text-right font-mono">{i.qty_billed}</td>
                                    <td className="px-3 py-3 text-right">
                                        <input type="number" min="0" step="0.01" className="w-24 border rounded-lg px-2 py-1 text-right font-mono" value={qtys[i.id] ?? 0} onChange={(e) => setQtys({ ...qtys, [i.id]: e.target.value })} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {order.status !== 'cancelled' && (
                <button type="button" className={btn} onClick={() => router.post(route('purchase-orders.receive', order.id), { quantities: qtys })}>Create goods receipt</button>
                )}
                {(order.goods_receipts || order.goodsReceipts || []).length > 0 && (
                    <div className="text-sm space-y-1">
                        <h3 className="font-semibold">Goods receipts</h3>
                        {(order.goods_receipts || order.goodsReceipts).map((d) => (
                            <Link key={d.id} className="block text-terracotta" href={route('goods-receipts.show', d.id)}>{d.grn_number} · {d.status}</Link>
                        ))}
                    </div>
                )}
                {(order.bills || []).length > 0 && (
                    <div className="text-sm space-y-1">
                        <h3 className="font-semibold">Bills</h3>
                        {order.bills.map((b) => (
                            <Link key={b.id} className="block text-terracotta" href={route('bills.show', b.id)}>{b.bill_number} · {b.status}</Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
