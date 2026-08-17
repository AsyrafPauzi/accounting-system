import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Edit({ auth, order, editable = true, lock_reason = null }) {
    const { data, setData, put, processing } = useForm({
        issue_date: order.issue_date?.slice?.(0, 10) || order.issue_date || '',
        delivery_date: order.delivery_date?.slice?.(0, 10) || order.delivery_date || '',
        customer_notes: order.customer_notes || '',
    });

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit {order.do_number}</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Header and notes only — line quantities stay as delivered</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('delivery-orders.show', order.id)} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    {editable && (
                        <button type="submit" form="do-edit-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                            {processing ? 'Saving…' : 'Save changes'}
                        </button>
                    )}
                </div>
            </div>
        }>
            <Head title={`Edit ${order.do_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <form id="do-edit-form" className="space-y-4 pb-8 min-w-0 max-w-2xl" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('delivery-orders.update', order.id)); }}>
                <div className="bg-surface p-4 sm:p-5 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <div>
                        <label className={labelClass}>Issue date</label>
                        <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} disabled={!editable} />
                    </div>
                    <div>
                        <label className={labelClass}>Delivery date</label>
                        <input type="date" className={inputClass} value={data.delivery_date} onChange={(e) => setData('delivery_date', e.target.value)} disabled={!editable} />
                    </div>
                    <div>
                        <label className={labelClass}>Notes</label>
                        <textarea className={inputClass} rows={3} value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)} disabled={!editable} />
                    </div>
                </div>
                <div className="bg-surface rounded-2xl border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-cream/50 text-[10px] uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 text-left">Item</th>
                                <th className="px-4 py-3 text-right">Qty</th>
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
            </form>
        </AuthenticatedLayout>
    );
}
