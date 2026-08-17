import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import DocumentTrail from '@/Components/DocumentTrail';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, order, trail = [] }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={order.grn_number} />
            <div className="space-y-4 min-w-0">
                <Link href={route('goods-receipts.index')} className="text-xs font-semibold text-ink-muted">← Goods receipts</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-display">{order.grn_number}</h1>
                        <p className="text-sm text-ink-muted">{order.supplier?.name} · {order.status}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a className={btn} href={route('goods-receipts.pdf', order.id)} target="_blank" rel="noreferrer">PDF</a>
                        <button type="button" className={btn} onClick={() => router.post(route('goods-receipts.email', order.id))}>Email</button>
                        {order.status === 'received' && (
                            <button type="button" className={`${btn} text-terracotta`} onClick={() => router.post(route('goods-receipts.return', order.id))}>Return</button>
                        )}
                        {order.status !== 'billed' && order.status !== 'cancelled' && <button type="button" className={primary} onClick={() => router.post(route('goods-receipts.bill', order.id), {})}>Convert to bill</button>}
                    </div>
                </div>
                <DocumentTrail steps={trail} />
                <div className="bg-surface rounded-2xl border border-border-warm overflow-hidden">
                    <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-4 bg-cream/50 px-4 py-3 text-[10px] font-display font-medium uppercase tracking-widest text-ink-muted">
                        <span>Item</span>
                        <span className="text-right">Quantity received</span>
                    </div>
                    <div className="divide-y divide-border-warm">
                        {(order.items || []).map((i) => (
                            <div key={i.id} className="grid grid-cols-[minmax(0,1fr)_auto] gap-4 px-4 py-3 text-sm">
                                <span className="min-w-0">{i.description}</span>
                                <span className="font-mono text-right tabular-nums">{i.quantity}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
