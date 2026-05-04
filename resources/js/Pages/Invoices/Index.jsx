import React, { useState } from 'react';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
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
    PaperAirplane: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12l14-7-7 14-2-5-5-2z" /></svg>,
    EllipsisVertical: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>,
    ChevronLeft: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
};

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

export default function Index({ auth, invoices = [], totalOutstanding = 0, totalCollected = 0, totalCount = 0, paginator = {}, filters = {} }) {
    const { current_page = 1, last_page = 1, per_page = 10, total = 0, from = 0, to = 0 } = paginator;
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 10 } = filters;

    const [searchInput, setSearchInput] = useState(search);
    const [selectedInvoice, setSelectedInvoice] = useState(null);
    const [emailingId, setEmailingId] = useState(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: '1200',
    });

    const applyFilters = (overrides = {}) => {
        const params = { search: overrides.search ?? searchInput, status: overrides.status ?? statusFilter, per_page: overrides.per_page ?? perPageFilter, page: overrides.page ?? 1 };
        router.get(route('invoices.index'), params, { preserveState: false });
    };

    const handlePostToLedger = async (id) => {
        const ok = await confirm({ title: 'Post to Ledger?', text: 'This will lock the invoice details and create General Ledger entries.', confirmText: 'Post', icon: 'question' });
        if (ok) router.post(route('invoices.post', id));
    };

    const handleVoid = async (id) => {
        const ok = await confirm({ title: 'Void Invoice?', text: 'This creates a reversal entry and cancels the balance. This action cannot be undone.', confirmText: 'Void', confirmColor: '#dc2626', icon: 'warning' });
        if (ok) router.post(route('invoices.void', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({ title: 'Delete Draft?', text: 'This cannot be undone.', confirmText: 'Delete', confirmColor: '#dc2626', icon: 'warning' });
        if (ok) router.delete(route('invoices.destroy', id));
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('invoices.record-payment', selectedInvoice.id), { onSuccess: () => { setSelectedInvoice(null); reset(); } });
    };

    const handleEmailInvoice = async (id) => {
        const ok = await confirm({ title: 'Email Invoice PDF?', text: 'This will send the PDF to the customer email on file.', confirmText: 'Send', icon: 'question' });
        if (ok) router.post(route('invoices.email', id), { onStart: () => setEmailingId(id), onFinish: () => setEmailingId(null) });
    };

    const InvoiceRow = ({ invoice }) => (
        <>
            <td className="px-4 sm:px-6 py-3 sm:py-4">
                <Link href={route('invoices.edit', invoice.id)} className="block group/link">
                    <span className="font-semibold text-slate-800 group-hover/link:text-blue-600">{invoice.invoice_number}</span>
                    <p className="text-xs text-slate-500 mt-0.5">{new Date(invoice.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}</p>
                </Link>
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4">
                <div className="font-medium text-slate-700">{invoice.customer_name || 'Walk-in'}</div>
                <p className="text-xs text-slate-400 truncate max-w-[140px] sm:max-w-none">{invoice.customer_email || 'No email'}</p>
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4">
                <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(invoice.status)}`}>{invoice.status}</span>
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                <div className="font-mono text-sm font-semibold text-slate-800">RM {parseFloat(invoice.total_amount || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</div>
                {parseFloat(invoice.amount_paid) > 0 && invoice.status !== 'paid' && (
                    <p className="text-xs text-rose-600 tabular-nums">Bal: RM {(parseFloat(invoice.total_amount) - parseFloat(invoice.amount_paid)).toFixed(2)}</p>
                )}
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                <ActionsCell auth={auth} invoice={invoice} setSelectedInvoice={setSelectedInvoice} setData={setData} handlePostToLedger={handlePostToLedger} handleVoid={handleVoid} handleDelete={handleDelete} handleEmailInvoice={handleEmailInvoice} emailingId={emailingId} />
            </td>
        </>
    );

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Sales Invoices</h2>
                    <p className="text-slate-500 text-sm font-medium mt-1">Create, manage and track revenue documents</p>
                </div>
                {auth.permissions.includes('invoices.create') && (
                    <Link href={route('invoices.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200">
                        <Icons.Plus /> Create Invoice
                    </Link>
                )}
            </div>
        }>
            <Head title="Invoices" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total Invoices</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Document /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{totalCount}</p>
                        <p className="text-xs text-blue-100 mt-1">Draft · Unpaid · Paid</p>
                    </div>
                    <div className="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Outstanding (AR)</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-rose-600 font-mono tabular-nums">RM {totalOutstanding.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                        <p className="text-xs text-slate-500 mt-1">Awaiting collection</p>
                    </div>
                    <div className="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Collected</span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-emerald-600 font-mono tabular-nums">RM {totalCollected.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                        <p className="text-xs text-slate-500 mt-1">Cash received</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <form onSubmit={(e) => { e.preventDefault(); applyFilters({ page: 1 }); }} className="px-4 sm:px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50">
                        <div className="relative flex-1 min-w-0 max-w-full sm:max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-slate-400"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder="Search by invoice # or customer..." value={searchInput} onChange={(e) => setSearchInput(e.target.value)} onBlur={() => applyFilters({ page: 1 })} className="pl-9 w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <select value={statusFilter} onChange={(e) => applyFilters({ status: e.target.value, page: 1 })} className="border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 min-w-[140px]">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partially paid">Partially Paid</option>
                            <option value="paid">Paid</option>
                            <option value="void">Void</option>
                        </select>
                        <select value={perPageFilter} onChange={(e) => applyFilters({ per_page: Number(e.target.value), page: 1 })} className="border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 min-w-[140px]">
                            <option value={10}>10 per page</option>
                            <option value={25}>25 per page</option>
                            <option value={50}>50 per page</option>
                            <option value={100}>100 per page</option>
                        </select>
                        <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700">Apply</button>
                        <span className="text-slate-500 text-sm font-medium ml-auto whitespace-nowrap">
                            {total > 0 ? `${from}–${to} of ${total}` : '0 of 0'}
                        </span>
                    </form>

                    {/* Desktop table */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-4 sm:px-6 py-3">Invoice</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Amount</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.length > 0 ? invoices.map((invoice) => (
                                    <tr key={invoice.id} className={`border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors ${invoice.status === 'void' ? 'opacity-60' : ''}`}>
                                        <InvoiceRow invoice={invoice} />
                                    </tr>
                                )) : (
                                    <tr><td colSpan={5} className="px-6 py-16 text-center text-slate-400 text-sm">{totalCount === 0 ? 'No invoices yet. Create your first invoice to get started.' : 'No invoices match your filters.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile cards */}
                    <div className="md:hidden divide-y divide-slate-100">
                        {invoices.length > 0 ? invoices.map((invoice) => (
                            <div key={invoice.id} className={`p-4 ${invoice.status === 'void' ? 'opacity-60' : ''}`}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('invoices.edit', invoice.id)} className="font-semibold text-slate-800 hover:text-blue-600">{invoice.invoice_number}</Link>
                                        <p className="text-xs text-slate-500 mt-0.5">{invoice.customer_name || 'Walk-in'}</p>
                                        <p className="text-sm font-mono font-semibold text-slate-800 mt-1">RM {parseFloat(invoice.total_amount || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(invoice.status)}`}>{invoice.status}</span>
                                    </div>
                                    <ActionsCell auth={auth} invoice={invoice} setSelectedInvoice={setSelectedInvoice} setData={setData} handlePostToLedger={handlePostToLedger} handleVoid={handleVoid} handleDelete={handleDelete} handleEmailInvoice={handleEmailInvoice} emailingId={emailingId} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-slate-400 text-sm">{totalCount === 0 ? 'No invoices yet. Create your first invoice to get started.' : 'No invoices match your filters.'}</div>
                        )}
                    </div>

                    {/* Pagination */}
                    {last_page > 1 && (
                        <div className="px-4 sm:px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/30">
                            <p className="text-sm text-slate-600">Page {current_page} of {last_page}</p>
                            <div className="flex items-center gap-2">
                                <Link href={route('invoices.index', { search: searchInput || undefined, status: statusFilter || undefined, per_page: perPageFilter, page: Math.max(1, current_page - 1) })} className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${current_page <= 1 ? 'pointer-events-none text-slate-300 border-slate-200' : 'text-slate-700 border-slate-200 hover:bg-slate-50'}`}>
                                    <Icons.ChevronLeft /> Previous
                                </Link>
                                <Link href={route('invoices.index', { search: searchInput || undefined, status: statusFilter || undefined, per_page: perPageFilter, page: Math.min(last_page, current_page + 1) })} className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${current_page >= last_page ? 'pointer-events-none text-slate-300 border-slate-200' : 'text-slate-700 border-slate-200 hover:bg-slate-50'}`}>
                                    Next <Icons.ChevronRight />
                                </Link>
                            </div>
                        </div>
                    )}
                </div>

                {selectedInvoice && (
                    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-slate-100">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-emerald-100 text-emerald-600"><Icons.Currency /></span>
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
                                        <input type="number" value={data.amount} onChange={e => setData('amount', e.target.value)} className={`w-full pl-12 pr-4 py-3 border rounded-xl font-semibold text-slate-700 focus:ring-2 focus:ring-blue-500 ${errors.amount ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-200'}`} step="0.01" required />
                                    </div>
                                    {errors.amount && <p className="text-rose-500 text-[10px] mt-1.5 font-bold uppercase tracking-tight">{errors.amount}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Date</label>
                                        <input type="date" value={data.payment_date} onChange={e => setData('payment_date', e.target.value)} className={`w-full border rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500 ${errors.payment_date ? 'border-rose-500' : 'border-slate-200'}`} required />
                                        {errors.payment_date && <p className="text-rose-500 text-[10px] mt-1.5 font-bold uppercase tracking-tight">{errors.payment_date}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Account</label>
                                        <select value={data.bank_account_code} onChange={e => setData('bank_account_code', e.target.value)} className={`w-full border rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500 ${errors.bank_account_code ? 'border-rose-500' : 'border-slate-200'}`}>
                                            <option value="1200">Maybank (1200)</option>
                                            <option value="1210">Petty Cash (1210)</option>
                                        </select>
                                        {errors.bank_account_code && <p className="text-rose-500 text-[10px] mt-1.5 font-bold uppercase tracking-tight">{errors.bank_account_code}</p>}
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={() => { setSelectedInvoice(null); reset(); }} className="flex-1 py-3 rounded-xl font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50">Cancel</button>
                                    <button type="submit" disabled={processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"> {processing ? 'Processing...' : 'Confirm Receipt'}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ActionsCell({ auth, invoice, setSelectedInvoice, setData, handlePostToLedger, handleVoid, handleDelete, handleEmailInvoice, emailingId }) {
    const isDraft = invoice.status === 'draft';
    const isVoid = invoice.status === 'void';

    return (
        <Menu as="div" className="relative inline-block text-left">
            <MenuButton className="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors">
                <Icons.EllipsisVertical />
            </MenuButton>
            <MenuItems
                anchor="bottom end"
                transition
                className="z-[100] mt-2 w-52 origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black/5 focus:outline-none py-1 transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                <MenuItem>
                    <Link href={route('invoices.edit', invoice.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                        <Icons.ChevronRight className="w-4 h-4" /> View
                    </Link>
                </MenuItem>
                <MenuItem>
                    <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                        <Icons.ArrowDownTray /> Download PDF
                    </a>
                </MenuItem>
                {isDraft && (
                    <>
                        {auth.permissions.includes('invoices.post') && (
                            <MenuItem>
                                <button type="button" onClick={() => handlePostToLedger(invoice.id)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-blue-600 hover:bg-blue-50">
                                    <Icons.Check /> Post to ledger
                                </button>
                            </MenuItem>
                        )}
                        {auth.permissions.includes('invoices.edit') && (
                            <MenuItem>
                                <Link href={route('invoices.edit', invoice.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                                    <Icons.Pencil /> Edit
                                </Link>
                            </MenuItem>
                        )}
                        {auth.permissions.includes('invoices.delete') && (
                            <MenuItem>
                                <button type="button" onClick={() => handleDelete(invoice.id)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50">
                                    Delete draft
                                </button>
                            </MenuItem>
                        )}
                    </>
                )}
                {!isDraft && !isVoid && (
                    <>
                        {invoice.status !== 'paid' && auth.planPermissions['invoices.record-payment'] && auth.permissions.includes('invoices.record-payment') && (
                            <MenuItem>
                                <button type="button" onClick={() => { setSelectedInvoice(invoice); setData('amount', (parseFloat(invoice.total_amount) - parseFloat(invoice.amount_paid)).toFixed(2)); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-emerald-600 hover:bg-emerald-50">
                                    <Icons.Currency /> Record payment
                                </button>
                            </MenuItem>
                        )}
                        {auth.planPermissions['credit-notes.view'] && auth.permissions.includes('credit-notes.create') && (
                            <MenuItem>
                                <Link href={route('credit-notes.create', invoice.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                                    <Icons.ReceiptRefund /> Credit note
                                </Link>
                            </MenuItem>
                        )}
                        {invoice.customer_email && auth.planPermissions['invoices.email'] && auth.permissions.includes('invoices.email') && (
                            <MenuItem>
                                <button type="button" onClick={() => handleEmailInvoice(invoice.id)} disabled={emailingId === invoice.id} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                                    <Icons.PaperAirplane /> {emailingId === invoice.id ? 'Emailing…' : 'Email'}
                                </button>
                            </MenuItem>
                        )}
                        {auth.permissions.includes('invoices.void') && (
                            <MenuItem>
                                <button type="button" onClick={() => handleVoid(invoice.id)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50">
                                    Void invoice
                                </button>
                            </MenuItem>
                        )}
                    </>
                )}
            </MenuItems>
        </Menu>
    );
}
