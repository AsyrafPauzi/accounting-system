import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

export default function Edit({ auth, order, editable = true, lock_reason = null }) {
    const { data, setData, put, processing } = useForm({
        issue_date: order.issue_date?.slice?.(0, 10) || order.issue_date || '',
        delivery_date: order.delivery_date?.slice?.(0, 10) || order.delivery_date || '',
        customer_notes: order.customer_notes || '',
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('delivery-orders.show', order.id)}
                    title={`Edit ${order.do_number}`}
                    subtitle="Header and notes only — line quantities stay as delivered"
                    formId="do-edit-form"
                    processing={processing}
                    submitLabel="Save changes"
                    showSubmit={editable}
                />
            }
        >
            <Head title={`Edit ${order.do_number}`} />
            {!editable && lock_reason && (
                <div className="mb-4 rounded-xl border border-terracotta/30 bg-terracotta/10 px-4 py-3 text-sm text-terracotta">{lock_reason}</div>
            )}
            <form id="do-edit-form" className="space-y-6 pb-12 min-w-0" onSubmit={(e) => { e.preventDefault(); if (editable) put(route('delivery-orders.update', order.id)); }}>
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                        <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Delivery details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                        <div className="min-w-0">
                            <label className={labelClass}>Issue date</label>
                            <input type="date" className={inputClass} value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div className="min-w-0">
                            <label className={labelClass}>Delivery date</label>
                            <input type="date" className={inputClass} value={data.delivery_date} onChange={(e) => setData('delivery_date', e.target.value)} disabled={!editable} />
                        </div>
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Notes (on PDF)</label>
                            <input className={inputClass} value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)} disabled={!editable} />
                        </div>
                    </div>
                </div>
                <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 min-w-0 overflow-hidden">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                <th className="px-4 py-3 text-left">Item</th>
                                <th className="px-4 py-3 text-right">Qty</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {(order.items || []).map((i) => (
                                <tr key={i.id}>
                                    <td className="px-4 py-3 text-sm font-medium text-ink">{i.description}</td>
                                    <td className="px-4 py-3 text-right font-mono tabular-nums text-sm">{i.quantity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
