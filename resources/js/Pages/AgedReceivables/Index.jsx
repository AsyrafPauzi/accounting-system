import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const Icons = {
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    DocumentText: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getAgingBadge(bucket) {
    const styles = {
        current: 'bg-emerald-100 text-emerald-700',
        '1-30': 'bg-amber-100 text-amber-700',
        '31-60': 'bg-orange-100 text-orange-700',
        '61-90': 'bg-rose-100 text-rose-700',
        '90+': 'bg-rose-200 text-rose-800',
    };
    return styles[bucket] || 'bg-slate-100 text-slate-600';
}

export default function Index({ auth, invoices = [], summary = {}, assetAccounts = [] }) {
    const { total_receivable = 0, overdue_count = 0, aging_breakdown = {} } = summary;
    const [selectedInvoice, setSelectedInvoice] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (assetAccounts && assetAccounts[0]?.value) || '1200',
    });

    const openPaymentModal = (invoice) => {
        const balance = parseFloat(invoice.balance_due) || 0;
        setSelectedInvoice(invoice);
        setData('amount', balance > 0 ? balance.toFixed(2) : 0);
        setData('payment_date', new Date().toISOString().split('T')[0]);
        setData('bank_account_code', (assetAccounts && assetAccounts[0]?.value) || '1200');
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('invoices.record-payment', selectedInvoice.id), {
            onSuccess: () => {
                setSelectedInvoice(null);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Aged Receivables</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Who hasn&apos;t paid you — 30, 60, or 90+ days overdue</p>
                    </div>
                    <Link
                        href={route('invoices.index')}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 shadow-sm"
                    >
                        View all invoices
                    </Link>
                </div>
            }
        >
            <Head title="Aged Receivables" />

            {(usePage().props?.flash?.success || usePage().props?.flash?.error) && (
                <div className={`mb-4 rounded-xl border px-4 py-3 text-sm font-medium ${usePage().props.flash.error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
                    {usePage().props.flash.success || usePage().props.flash.error}
                </div>
            )}

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-indigo-600 to-blue-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total receivables</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Currency /></span>
                        </div>
                        <p className="text-2xl font-bold font-mono tabular-nums">RM {formatMoney(total_receivable)}</p>
                        <p className="text-xs text-indigo-100 mt-1">Outstanding from customers</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Overdue</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-2xl font-bold text-rose-600 tabular-nums">{overdue_count}</p>
                        <p className="text-xs text-slate-500 mt-1">Invoices past due date</p>
                    </div>
                    {Object.entries(aging_breakdown).map(([key, bucket]) => (
                        (bucket.count > 0 || bucket.amount > 0) && (
                            <div key={key} className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                                <div className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">{bucket.label}</div>
                                <p className="text-xl font-bold text-slate-800 font-mono tabular-nums">RM {formatMoney(bucket.amount)}</p>
                                <p className="text-xs text-slate-500 mt-0.5">{bucket.count} invoice{bucket.count !== 1 ? 's' : ''}</p>
                            </div>
                        )
                    ))}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h3 className="text-sm font-bold text-slate-800">Outstanding invoices</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4">Invoice #</th>
                                    <th className="px-6 py-4">Due date</th>
                                    <th className="px-6 py-4 text-right">Total</th>
                                    <th className="px-6 py-4 text-right">Paid</th>
                                    <th className="px-6 py-4 text-right">Balance due</th>
                                    <th className="px-6 py-4">Aging</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.length > 0 ? invoices.map((inv) => (
                                    <tr key={inv.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80">
                                        <td className="px-6 py-4">
                                            <div className="font-medium text-slate-700">{inv.customer_name || '—'}</div>
                                            {inv.customer_email && <p className="text-xs text-slate-400">{inv.customer_email}</p>}
                                        </td>
                                        <td className="px-6 py-4">
                                            <Link href={route('invoices.edit', inv.id)} className="font-semibold text-slate-800 hover:text-blue-600">
                                                {inv.invoice_number}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-slate-600">
                                            {inv.due_date ? new Date(inv.due_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-slate-800">RM {formatMoney(inv.total_amount)}</td>
                                        <td className="px-6 py-4 text-right font-mono text-slate-600">RM {formatMoney(inv.amount_paid)}</td>
                                        <td className="px-6 py-4 text-right font-mono font-semibold text-rose-600">RM {formatMoney(inv.balance_due)}</td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold ${getAgingBadge(inv.aging_bucket)}`}>
                                                {inv.aging_label || 'Current'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link href={route('invoices.edit', inv.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100">
                                                    View invoice <Icons.ChevronRight />
                                                </Link>
                                                <button
                                                    onClick={() => openPaymentModal(inv)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100"
                                                >
                                                    <Icons.Currency /> Record payment
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center text-slate-500 text-sm">
                                            No outstanding receivables. All invoices are paid or no unpaid invoices.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {selectedInvoice && (
                    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 border border-slate-100">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-emerald-100 text-emerald-600"><Icons.Currency /></span>
                                <div>
                                    <h3 className="text-xl font-bold text-slate-900">Record receipt</h3>
                                    <p className="text-sm text-slate-500">Invoice {selectedInvoice.invoice_number}</p>
                                </div>
                            </div>
                            <form onSubmit={handlePaymentSubmit} className="space-y-5">
                                <div>
                                    <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Amount (RM)</label>
                                    <div className="relative">
                                        <span className="absolute inset-y-0 left-4 flex items-center text-slate-400 font-medium">RM</span>
                                        <input
                                            type="number"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            className="w-full pl-12 pr-4 py-3 border border-slate-200 rounded-xl font-semibold text-slate-700"
                                            step="0.01"
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Date</label>
                                        <input type="date" value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} className="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm" required />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Bank account</label>
                                        <select value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} className="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm">
                                            {(assetAccounts || []).map((a) => (
                                                <option key={a.value} value={a.value}>{a.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={() => { setSelectedInvoice(null); reset(); }} className="flex-1 py-3 rounded-xl font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50">
                                        Cancel
                                    </button>
                                    <button type="submit" disabled={processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                                        {processing ? 'Processing...' : 'Confirm receipt'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
