import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ReceiptRefund: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>,
    PaperAirplane: () => (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12l14-7-7 14-2-5-5-2z" />
        </svg>
    ),
};

export default function Index({ auth, invoices = [] }) {
    // --- New Filter States ---
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('');

    // State to manage the "Record Payment" Modal
    const [selectedInvoice, setSelectedInvoice] = useState(null);

    // Form hook for the Payment Modal
    const { data, setData, post, processing, reset, errors } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: '1200', // Default: Main Bank Account
    });

    // --- Filter Logic ---
    const filteredInvoices = invoices.filter((invoice) => {
        const matchesSearch = 
            (invoice.invoice_number || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
            (invoice.customer_name && invoice.customer_name.toLowerCase().includes(searchTerm.toLowerCase()));
        
        const matchesStatus = statusFilter === '' || invoice.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    // --- KPI Calculations ---
    const totalOutstanding = invoices.reduce((sum, inv) => {
        if (inv.status === 'void' || inv.status === 'paid') return sum;
        const total = parseFloat(inv.total_amount) || 0;
        const paid = parseFloat(inv.amount_paid) || 0;
        return sum + (total - paid);
    }, 0);
    const totalCollected = invoices.reduce((sum, inv) => sum + (parseFloat(inv.amount_paid) || 0), 0);

    // --- Action Handlers ---

    const handlePostToLedger = async (id) => {
        const ok = await confirm({
            title: 'Post to Ledger?',
            text: 'This will lock the invoice details and create General Ledger entries.',
            confirmText: 'Post',
            icon: 'question',
        });
        if (ok) router.post(route('invoices.post', id));
    };

    const handleVoid = async (id) => {
        const ok = await confirm({
            title: 'Void Invoice?',
            text: 'This creates a reversal entry in the ledger and cancels the balance. This action cannot be undone.',
            confirmText: 'Void',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('invoices.void', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({
            title: 'Delete Draft?',
            text: 'This cannot be undone. The draft will be permanently removed.',
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('invoices.destroy', id));
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

    const [emailingId, setEmailingId] = useState(null);

    const handleEmailInvoice = async (id) => {
        const ok = await confirm({
            title: 'Email Invoice PDF?',
            text: 'This will send the PDF invoice to the customer email on file.',
            confirmText: 'Send',
            icon: 'question',
        });
        if (ok) {
            router.post(route('invoices.email', id), {
                onStart: () => setEmailingId(id),
                onFinish: () => setEmailingId(null),
            });
        }
    };

    const getStatusBadge = (status) => {
        const styles = {
            paid: 'bg-emerald-100 text-emerald-700',
            unpaid: 'bg-rose-100 text-rose-700',
            'partially paid': 'bg-blue-100 text-blue-700',
            draft: 'bg-slate-100 text-slate-600',
            void: 'bg-slate-200 text-slate-500',
        };
        return styles[status] || 'bg-slate-100 text-slate-600';
    };

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Sales Invoices</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Create, manage and track revenue documents</p>
                    </div>
                    <Link 
                        href={route('invoices.create')} 
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                    >
                        <Icons.Plus /> Create Invoice
                    </Link>
                </div>
            }
        >
            <Head title="Invoices" />

            <div className="space-y-6">
                {/* KPI row */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total Invoices</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Document /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{invoices.length}</p>
                        <p className="text-xs text-blue-100 mt-1">Draft · Unpaid · Paid</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Outstanding (AR)</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">
                            RM {totalOutstanding.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">Awaiting collection</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Collected</span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600"><Icons.Check /></span>
                        </div>
                        <p className="text-xl font-bold text-emerald-600 font-mono tabular-nums">
                            RM {totalCollected.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">Cash received</p>
                    </div>
                </div>

                {/* Table Card */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-4 bg-slate-50/50">
                        <div className="relative flex-1 min-w-[200px] max-w-sm">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input 
                                type="text" 
                                placeholder="Search by invoice # or customer..." 
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
                            <option value="partially paid">Partially Paid</option>
                            <option value="paid">Paid</option>
                            <option value="void">Void</option>
                        </select>
                        {(searchTerm || statusFilter) && (
                            <button 
                                onClick={() => { setSearchTerm(''); setStatusFilter(''); }}
                                className="text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                Clear
                            </button>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            {filteredInvoices.length} of {invoices.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Invoice</th>
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Amount</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredInvoices.length > 0 ? filteredInvoices.map((invoice) => (
                                    <tr key={invoice.id} className={`border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors group ${invoice.status === 'void' ? 'opacity-60' : ''}`}>
                                        <td className="px-6 py-4">
                                            <Link href={route('invoices.edit', invoice.id)} className="block group/link">
                                                <span className="font-semibold text-slate-800 group-hover/link:text-blue-600 transition-colors">{invoice.invoice_number}</span>
                                                <p className="text-xs text-slate-500 mt-0.5">
                                                    {new Date(invoice.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                </p>
                                                {invoice.last_emailed_status === 'sent' && invoice.last_emailed_at && (
                                                    <p className="text-[10px] text-slate-400 mt-0.5">
                                                        Emailed: {new Date(invoice.last_emailed_at).toLocaleString('en-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                                    </p>
                                                )}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-medium text-slate-700">{invoice.customer_name || 'Walk-in'}</div>
                                            <p className="text-xs text-slate-400">
                                                MSIC: {invoice.msic_code || '—'}
                                            </p>
                                            <p className="text-[10px] text-slate-400 mt-0.5">
                                                {invoice.customer_email || 'No email on file'}
                                            </p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex flex-col gap-1">
                                                <span className={`inline-flex w-fit px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(invoice.status)}`}>
                                                    {invoice.status}
                                                </span>
                                                {invoice.last_emailed_status && (
                                                    <span className={`inline-flex w-fit px-2 py-0.5 rounded-md text-[10px] font-medium ${
                                                        invoice.last_emailed_status === 'sent'
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : invoice.last_emailed_status === 'pending'
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-rose-100 text-rose-700'
                                                    }`} title={invoice.last_emailed_status === 'sent' && invoice.last_emailed_at ? `Sent ${new Date(invoice.last_emailed_at).toLocaleString()}` : invoice.last_emailed_status === 'failed' && invoice.last_emailed_error ? invoice.last_emailed_error : ''}>
                                                        {invoice.last_emailed_status === 'sent' && 'Email sent'}
                                                        {invoice.last_emailed_status === 'pending' && 'Email queued'}
                                                        {invoice.last_emailed_status === 'failed' && 'Email failed'}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="font-mono text-sm font-semibold text-slate-800 tabular-nums">
                                                RM {parseFloat(invoice.total_amount || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </div>
                                            {parseFloat(invoice.amount_paid) > 0 && invoice.status !== 'paid' && (
                                                <p className="text-xs text-rose-600 font-medium mt-0.5 tabular-nums">
                                                    Bal: RM {(parseFloat(invoice.total_amount) - parseFloat(invoice.amount_paid)).toFixed(2)}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2 flex-wrap">
                                                {invoice.status === 'draft' && (
                                                    <>
                                                        <button 
                                                            onClick={() => handlePostToLedger(invoice.id)}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700"
                                                        >
                                                            <Icons.Check /> Post
                                                        </button>
                                                        <Link href={route('invoices.edit', invoice.id)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100">
                                                            <Icons.Pencil /> Edit
                                                        </Link>
                                                        <button onClick={() => handleDelete(invoice.id)} className="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50">
                                                            Delete
                                                        </button>
                                                    </>
                                                )}
                                                {invoice.status !== 'draft' && invoice.status !== 'void' && (
                                                    <>
                                                        {invoice.status !== 'paid' && (
                                                            <button 
                                                                onClick={() => {
                                                                    setSelectedInvoice(invoice);
                                                                    const remaining = parseFloat(invoice.total_amount) - parseFloat(invoice.amount_paid);
                                                                    setData('amount', remaining.toFixed(2));
                                                                }}
                                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100"
                                                            >
                                                                <Icons.Currency /> Record Payment
                                                            </button>
                                                        )}
                                                        <Link 
                                                            href={route('credit-notes.create', invoice.id)} 
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100"
                                                        >
                                                            <Icons.ReceiptRefund /> Credit Note
                                                        </Link>
                                                        {!!invoice.customer_email && (
                                                            <button
                                                                onClick={() => handleEmailInvoice(invoice.id)}
                                                                disabled={emailingId === invoice.id}
                                                                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold ${
                                                                    emailingId === invoice.id
                                                                        ? 'text-indigo-400 bg-indigo-50 cursor-wait'
                                                                        : 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100'
                                                                }`}
                                                            >
                                                                <Icons.PaperAirplane />{' '}
                                                                {emailingId === invoice.id ? 'Emailing…' : 'Email'}
                                                            </button>
                                                        )}
                                                        <button onClick={() => handleVoid(invoice.id)} className="inline-flex px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50">
                                                            Void
                                                        </button>
                                                    </>
                                                )}
                                                <a 
                                                    href={route('invoices.pdf', invoice.id)} 
                                                    target="_blank" 
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                                >
                                                    <Icons.ArrowDownTray /> PDF
                                                </a>
                                                <Link 
                                                    href={route('invoices.edit', invoice.id)} 
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100"
                                                >
                                                    View <Icons.ChevronRight />
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-16 text-center">
                                            <p className="text-slate-400 text-sm font-medium">
                                                {searchTerm || statusFilter ? 'No invoices match your filters.' : 'No invoices yet. Create your first invoice to get started.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Record Payment Modal */}
                {selectedInvoice && (
                    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 border border-slate-100">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-emerald-100 text-emerald-600">
                                    <Icons.Currency />
                                </span>
                                <div>
                                    <h3 className="text-xl font-bold text-slate-900">Record Receipt</h3>
                                    <p className="text-sm text-slate-500">Payment for {selectedInvoice.invoice_number}</p>
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
                                            onChange={e => setData('amount', e.target.value)}
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
                                            onChange={e => setData('payment_date', e.target.value)}
                                            className="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Account</label>
                                        <select 
                                            value={data.bank_account_code}
                                            onChange={e => setData('bank_account_code', e.target.value)}
                                            className="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="1200">Maybank (1200)</option>
                                            <option value="1210">Petty Cash (1210)</option>
                                        </select>
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button 
                                        type="button" 
                                        onClick={() => { setSelectedInvoice(null); reset(); }} 
                                        className="flex-1 py-3 rounded-xl font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                    <button 
                                        type="submit" 
                                        disabled={processing} 
                                        className="flex-[2] py-3 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25"
                                    >
                                        {processing ? 'Processing...' : 'Confirm Receipt'}
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