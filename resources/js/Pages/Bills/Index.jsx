import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { confirm } from '@/utils/swal';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Exclamation: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getStatusBadge(status) {
    const styles = {
        paid: 'bg-emerald-100 text-emerald-700',
        unpaid: 'bg-rose-100 text-rose-700',
        'partially paid': 'bg-blue-100 text-blue-700',
        draft: 'bg-slate-100 text-slate-600',
        void: 'bg-slate-200 text-slate-500',
    };
    return styles[status] || 'bg-slate-100 text-slate-600';
}

export default function Index({ auth, bills = [], suppliers = [], assetAccounts = [], totalOutstanding = 0, totalPaidPeriod = 0 }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [supplierFilter, setSupplierFilter] = useState('');
    const [selectedBill, setSelectedBill] = useState(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (assetAccounts && assetAccounts[0]?.value) || '1200',
    });

    const filteredBills = bills.filter((bill) => {
        const matchesSearch =
            (bill.bill_number || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
            (bill.supplier_name && bill.supplier_name.toLowerCase().includes(searchTerm.toLowerCase()));
        const matchesStatus = statusFilter === '' || bill.status === statusFilter;
        const matchesSupplier = supplierFilter === '' || String(bill.supplier_id) === supplierFilter;
        return matchesSearch && matchesStatus && matchesSupplier;
    });

    const handlePost = async (id) => {
        const ok = await confirm({
            title: 'Post to Ledger?',
            text: 'This will create General Ledger entries (DR expense, CR Accounts Payable).',
            confirmText: 'Post',
            icon: 'question',
        });
        if (ok) router.post(route('bills.post', id));
    };

    const handleVoid = async (id) => {
        const ok = await confirm({
            title: 'Void Bill?',
            text: 'This creates a reversal entry and sets the bill to void. This action cannot be undone.',
            confirmText: 'Void',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('bills.void', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({
            title: 'Delete Draft?',
            text: 'This cannot be undone.',
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('bills.destroy', id));
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('bills.record-payment', selectedBill.id), {
            onSuccess: () => {
                setSelectedBill(null);
                reset('amount', 0);
                reset('payment_date', new Date().toISOString().split('T')[0]);
            },
        });
    };

    const openPaymentModal = (bill) => {
        const balance = (parseFloat(bill.total_amount) || 0) - (parseFloat(bill.amount_paid) || 0);
        setSelectedBill(bill);
        setData('amount', balance > 0 ? balance.toFixed(2) : 0);
        setData('payment_date', new Date().toISOString().split('T')[0]);
        setData('bank_account_code', (assetAccounts && assetAccounts[0]?.value) || '1200');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Bills & Purchases</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Record expenses and track payables</p>
                    </div>
                    <Link
                        href={route('bills.create')}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                    >
                        <Icons.Plus /> Create bill
                    </Link>
                </div>
            }
        >
            <Head title="Bills" />

            {(usePage().props?.flash?.success || usePage().props?.flash?.error) && (
                <div
                    className={`mb-4 rounded-xl border px-4 py-3 text-sm font-medium ${usePage().props.flash.error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
                >
                    {usePage().props.flash.success || usePage().props.flash.error}
                </div>
            )}

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-amber-600 to-orange-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total bills</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Document /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{bills.length}</p>
                        <p className="text-xs text-amber-100 mt-1">Draft · Unpaid · Paid</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Outstanding (AP)</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">RM {formatMoney(totalOutstanding)}</p>
                        <p className="text-xs text-slate-500 mt-1">Amount due to suppliers</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Paid (period)</span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600"><Icons.Check /></span>
                        </div>
                        <p className="text-xl font-bold text-emerald-600 font-mono tabular-nums">RM {formatMoney(totalPaidPeriod)}</p>
                        <p className="text-xs text-slate-500 mt-1">Payments recorded</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-4 bg-slate-50/50">
                        <div className="relative flex-1 min-w-[200px] max-w-sm">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                placeholder="Search by bill # or supplier..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-10 w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partially paid">Partially paid</option>
                            <option value="paid">Paid</option>
                            <option value="void">Void</option>
                        </select>
                        <select
                            value={supplierFilter}
                            onChange={(e) => setSupplierFilter(e.target.value)}
                            className="border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All suppliers</option>
                            {(suppliers || []).map((s) => (
                                <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                            ))}
                        </select>
                        {(searchTerm || statusFilter || supplierFilter) && (
                            <button
                                type="button"
                                onClick={() => { setSearchTerm(''); setStatusFilter(''); setSupplierFilter(''); }}
                                className="text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                Clear
                            </button>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            {filteredBills.length} of {bills.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Bill</th>
                                    <th className="px-6 py-4">Supplier</th>
                                    <th className="px-6 py-4">Due date</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Total</th>
                                    <th className="px-6 py-4 text-right">Balance</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredBills.length > 0 ? filteredBills.map((bill) => {
                                    const balanceDue = (parseFloat(bill.total_amount) || 0) - (parseFloat(bill.amount_paid) || 0);
                                    return (
                                        <tr key={bill.id} className={`border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors group ${bill.status === 'void' ? 'opacity-60' : ''}`}>
                                            <td className="px-6 py-4">
                                                <Link href={route('bills.edit', bill.id)} className="block group/link">
                                                    <span className="font-semibold text-slate-800 group-hover/link:text-blue-600 transition-colors">{bill.bill_number}</span>
                                                    <p className="text-xs text-slate-500 mt-0.5">
                                                        {bill.bill_date && new Date(bill.bill_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                    </p>
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 font-medium text-slate-700">{bill.supplier_name || '—'}</td>
                                            <td className="px-6 py-4 text-slate-600">
                                                {bill.due_date ? new Date(bill.due_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex w-fit px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(bill.status)}`}>
                                                    {bill.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-sm font-semibold text-slate-800 tabular-nums">
                                                RM {formatMoney(bill.total_amount)}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-sm text-rose-600 tabular-nums">
                                                {bill.status !== 'draft' && bill.status !== 'void' && balanceDue > 0 ? `RM ${formatMoney(balanceDue)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center justify-end gap-2 flex-wrap">
                                                    {bill.status === 'draft' && (
                                                        <>
                                                            <button onClick={() => handlePost(bill.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700">
                                                                <Icons.Check /> Post
                                                            </button>
                                                            <Link href={route('bills.edit', bill.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100">
                                                                <Icons.Pencil /> Edit
                                                            </Link>
                                                            <button onClick={() => handleDelete(bill.id)} className="inline-flex px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50">
                                                                Delete
                                                            </button>
                                                        </>
                                                    )}
                                                    {bill.status !== 'draft' && bill.status !== 'void' && balanceDue > 0 && (
                                                        <button
                                                            onClick={() => openPaymentModal(bill)}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100"
                                                        >
                                                            <Icons.Currency /> Record payment
                                                        </button>
                                                    )}
                                                    {bill.status !== 'draft' && bill.status !== 'void' && (
                                                        <button onClick={() => handleVoid(bill.id)} className="inline-flex px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50">
                                                            Void
                                                        </button>
                                                    )}
                                                    <Link href={route('bills.edit', bill.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100">
                                                        View <Icons.ChevronRight />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-16 text-center">
                                            <p className="text-slate-400 text-sm font-medium">
                                                {searchTerm || statusFilter || supplierFilter ? 'No bills match your filters.' : 'No bills yet. Create your first bill to get started.'}
                                            </p>
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
                                <span className="p-2.5 rounded-xl bg-emerald-100 text-emerald-600">
                                    <Icons.Currency />
                                </span>
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
                                            className="w-full pl-12 pr-4 py-3 border border-slate-200 rounded-xl font-semibold text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            step="0.01"
                                            required
                                        />
                                    </div>
                                    {errors.amount && <p className="text-rose-500 text-xs mt-1 font-medium">{errors.amount}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Date</label>
                                        <input
                                            type="date"
                                            value={data.payment_date}
                                            onChange={(e) => setData('payment_date', e.target.value)}
                                            className="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Bank account</label>
                                        <select
                                            value={data.bank_account_code}
                                            onChange={(e) => setData('bank_account_code', e.target.value)}
                                            className="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500"
                                        >
                                            {(assetAccounts || []).map((a) => (
                                                <option key={a.value} value={a.value}>{a.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button
                                        type="button"
                                        onClick={() => { setSelectedBill(null); reset(); }}
                                        className="flex-1 py-3 rounded-xl font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-[2] py-3 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25"
                                    >
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
