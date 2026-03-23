import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const Icons = {
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    DocumentText: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
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

export default function Index({ auth, bills = [], summary = {}, assetAccounts = [] }) {
    const { total_payable = 0, overdue_count = 0, aging_breakdown = {} } = summary;
    const [selectedBill, setSelectedBill] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (assetAccounts && assetAccounts[0]?.value) || '1200',
    });

    const openPaymentModal = (bill) => {
        const balance = parseFloat(bill.balance_due) || 0;
        setSelectedBill(bill);
        setData('amount', balance > 0 ? balance.toFixed(2) : 0);
        setData('payment_date', new Date().toISOString().split('T')[0]);
        setData('bank_account_code', (assetAccounts && assetAccounts[0]?.value) || '1200');
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('bills.record-payment', selectedBill.id), {
            onSuccess: () => {
                setSelectedBill(null);
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
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Accounts Payable</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Track what you owe suppliers — outstanding and aging</p>
                    </div>
                    <Link
                        href={route('bills.index')}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 shadow-sm"
                    >
                        View all bills
                    </Link>
                </div>
            }
        >
            <Head title="Accounts Payable" />

            {(usePage().props?.flash?.success || usePage().props?.flash?.error) && (
                <div className={`mb-4 rounded-xl border px-4 py-3 text-sm font-medium ${usePage().props.flash.error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
                    {usePage().props.flash.success || usePage().props.flash.error}
                </div>
            )}

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-rose-600 to-rose-700 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total payable</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Currency /></span>
                        </div>
                        <p className="text-2xl font-bold font-mono tabular-nums">RM {formatMoney(total_payable)}</p>
                        <p className="text-xs text-rose-100 mt-1">Outstanding to suppliers</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Overdue</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-2xl font-bold text-rose-600 tabular-nums">{overdue_count}</p>
                        <p className="text-xs text-slate-500 mt-1">Bills past due date</p>
                    </div>
                    {Object.entries(aging_breakdown).map(([key, bucket]) => (
                        (bucket.count > 0 || bucket.amount > 0) && (
                            <div key={key} className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                                <div className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">{bucket.label}</div>
                                <p className="text-xl font-bold text-slate-800 font-mono tabular-nums">RM {formatMoney(bucket.amount)}</p>
                                <p className="text-xs text-slate-500 mt-0.5">{bucket.count} bill{bucket.count !== 1 ? 's' : ''}</p>
                            </div>
                        )
                    ))}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h3 className="text-sm font-bold text-slate-800">Unpaid bills</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Supplier</th>
                                    <th className="px-6 py-4">Bill #</th>
                                    <th className="px-6 py-4">Due date</th>
                                    <th className="px-6 py-4 text-right">Total</th>
                                    <th className="px-6 py-4 text-right">Paid</th>
                                    <th className="px-6 py-4 text-right">Balance due</th>
                                    <th className="px-6 py-4">Aging</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bills.length > 0 ? bills.map((bill) => (
                                    <tr key={bill.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80">
                                        <td className="px-6 py-4 font-medium text-slate-700">{bill.supplier_name || '—'}</td>
                                        <td className="px-6 py-4">
                                            <Link href={route('bills.edit', bill.id)} className="font-semibold text-slate-800 hover:text-blue-600">
                                                {bill.bill_number}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-slate-600">
                                            {bill.due_date ? new Date(bill.due_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-slate-800">RM {formatMoney(bill.total_amount)}</td>
                                        <td className="px-6 py-4 text-right font-mono text-slate-600">RM {formatMoney(bill.amount_paid)}</td>
                                        <td className="px-6 py-4 text-right font-mono font-semibold text-rose-600">RM {formatMoney(bill.balance_due)}</td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold ${getAgingBadge(bill.aging_bucket)}`}>
                                                {bill.aging_label || 'Current'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link href={route('bills.edit', bill.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100">
                                                    View bill <Icons.ChevronRight />
                                                </Link>
                                                <button
                                                    onClick={() => openPaymentModal(bill)}
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
                                            No outstanding payables. All bills are paid or no bills have been posted yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {selectedBill && (
                    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 border border-slate-100">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-emerald-100 text-emerald-600"><Icons.Currency /></span>
                                <div>
                                    <h3 className="text-xl font-bold text-slate-900">Record payment</h3>
                                    <p className="text-sm text-slate-500">Bill {selectedBill.bill_number}</p>
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
                                    <button type="button" onClick={() => { setSelectedBill(null); reset(); }} className="flex-1 py-3 rounded-xl font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50">
                                        Cancel
                                    </button>
                                    <button type="submit" disabled={processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                                        {processing ? 'Processing...' : 'Confirm payment'}
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
