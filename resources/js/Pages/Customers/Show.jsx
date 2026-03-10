import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

// Icon components for cleaner, modern look
const Icons = {
    ChevronLeft: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
    ),
    Document: ({ className = 'w-5 h-5' }) => (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
    ),
    Currency: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    ),
    Check: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    ),
    Exclamation: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    ),
    CreditCard: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
    ),
    Shield: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
    ),
    Phone: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
    ),
    Mail: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
    ),
    Globe: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
    ),
    Location: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
    ),
    User: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
    ),
    Pencil: () => (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
    ),
    Plus: () => (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
    ),
    ExternalLink: () => (
        <svg className="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
    ),
};

export default function Show({ auth, customer, invoices = [], stats, auditLogs = [] }) {
    const formatAddress = (street, city, state, zip, country) => {
        if (!street && !city) return <span className="text-slate-400 italic">No address provided</span>;
        return (
            <div className="text-sm text-slate-600 leading-relaxed space-y-0.5">
                {street && <p>{street}</p>}
                <p>{(zip ? zip + ' ' : '')}{city}{state ? `, ${state}` : ''}{country ? ` ${country}` : ''}</p>
            </div>
        );
    };

    const websiteUrl = customer.website?.startsWith('http') ? customer.website : customer.website ? `https://${customer.website}` : null;

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link 
                            href={route('customers.index')} 
                            className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200"
                        >
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-4">
                            <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xl font-black shadow-lg shadow-blue-500/25">
                                {customer.name.charAt(0)}
                            </div>
                            <div>
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">{customer.name}</h2>
                                    <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase tracking-wider ${
                                        customer.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'
                                    }`}>
                                        {customer.is_active ? 'Active' : 'Suspended'}
                                    </span>
                                    {customer.credit_hold && <span className="px-2.5 py-0.5 rounded-lg text-[10px] font-semibold bg-amber-100 text-amber-700">Credit hold</span>}
                                    {customer.risk_rating && <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold ${
                                        customer.risk_rating === 'high' ? 'bg-rose-100 text-rose-700' : customer.risk_rating === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'
                                    }`}>{customer.risk_rating} risk</span>}
                                </div>
                                <p className="text-slate-500 text-sm font-medium mt-1">
                                    {customer.code} · {customer.industry || 'General'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link 
                            href={route('customers.edit', customer.id)} 
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200"
                        >
                            <Icons.Pencil /> Edit
                        </Link>
                        <Link 
                            href={route('invoices.create', { customer_id: customer.id })} 
                            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                        >
                            <Icons.Plus /> New Invoice
                        </Link>
                        <Link 
                            href={route('invoices.index')} 
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200"
                        >
                            <Icons.Document /> View All Invoices
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`${customer.name} - Customer Profile`} />

            <div className="space-y-8">
                {/* KPI row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Lifetime Value</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Currency /></span>
                        </div>
                        <p className="text-xl font-bold font-mono tabular-nums">
                            RM {parseFloat(stats.total_invoiced).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-blue-100 mt-1">Total invoiced</p>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total Collected</span>
                            <span className="p-2 rounded-xl bg-emerald-50 text-emerald-600"><Icons.Check /></span>
                        </div>
                        <p className="text-xl font-bold text-emerald-600 font-mono tabular-nums">
                            RM {parseFloat(stats.total_paid).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">Cash received</p>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Outstanding</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">
                            RM {parseFloat(stats.balance).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">AR balance</p>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Credit Limit</span>
                            <span className="p-2 rounded-xl bg-blue-50 text-blue-600"><Icons.CreditCard /></span>
                        </div>
                        <p className="text-xl font-bold text-slate-900 font-mono tabular-nums">
                            RM {parseFloat(customer.credit_limit || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        {stats.remaining_limit != null && (
                            <p className="text-xs text-slate-500 mt-1">Remaining: RM {parseFloat(stats.remaining_limit).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                        )}
                    </div>
                </div>

                {/* Aging summary */}
                {(stats.aging && (stats.aging['0_30'] > 0 || stats.aging['31_60'] > 0 || stats.aging['61_90'] > 0 || stats.aging['90_plus'] > 0)) && (
                    <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider mb-4">Aging (open balance)</h3>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p className="text-[10px] font-semibold text-slate-400 uppercase">Current / 0–30</p>
                                <p className="font-mono font-semibold text-slate-800">RM {parseFloat(stats.aging['0_30'] || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-semibold text-slate-400 uppercase">31–60 days</p>
                                <p className="font-mono font-semibold text-amber-600">RM {parseFloat(stats.aging['31_60'] || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-semibold text-slate-400 uppercase">61–90 days</p>
                                <p className="font-mono font-semibold text-rose-600">RM {parseFloat(stats.aging['61_90'] || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-semibold text-slate-400 uppercase">90+ days</p>
                                <p className="font-mono font-semibold text-rose-700">RM {parseFloat(stats.aging['90_plus'] || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Left Column */}
                    <div className="space-y-6">
                        <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                            <div className="flex items-center gap-2 mb-5">
                                <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Shield /></span>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Legal & Compliance</h3>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">LHDN TIN</p>
                                    <p className="text-sm font-medium text-slate-700">{customer.tin || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">SSM BRN</p>
                                    <p className="text-sm font-medium text-slate-700">{customer.brn || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Payment Terms</p>
                                    <p className="text-sm font-semibold text-blue-600">Net {customer.payment_terms} Days</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                            <div className="flex items-center gap-2 mb-5">
                                <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Phone /></span>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Contact</h3>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Primary Contact</p>
                                    <p className="text-sm font-medium text-slate-700">{customer.contact_person || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Email</p>
                                    <a href={`mailto:${customer.email}`} className="text-sm font-medium text-blue-600 hover:underline break-all">
                                        {customer.email}
                                    </a>
                                </div>
                                <div>
                                    <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Phone</p>
                                    <a href={customer.phone ? `tel:${customer.phone}` : '#'} className={`text-sm font-medium ${customer.phone ? 'text-slate-700 hover:text-blue-600' : 'text-slate-400'}`}>
                                        {customer.phone || '—'}
                                    </a>
                                </div>
                                {websiteUrl && (
                                    <div>
                                        <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Website</p>
                                        <a href={websiteUrl} target="_blank" rel="noopener noreferrer" className="inline-flex items-center text-sm font-medium text-blue-600 hover:underline truncate max-w-full">
                                            {customer.website.replace(/^https?:\/\//, '')}
                                            <Icons.ExternalLink />
                                        </a>
                                    </div>
                                )}
                                {(customer.contacts && customer.contacts.length > 0) && (
                                    <div className="pt-3 border-t border-slate-100">
                                        <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Additional contacts</p>
                                        <div className="space-y-2">
                                            {customer.contacts.map(c => (
                                                <div key={c.id} className="text-sm">
                                                    <span className="font-medium text-slate-700">{c.name || '—'}</span>
                                                    <span className="text-slate-400 text-xs ml-1">({c.type})</span>
                                                    {c.email && <a href={`mailto:${c.email}`} className="block text-blue-600 hover:underline text-xs">{c.email}</a>}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right Column */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                                <div className="flex items-center gap-2 mb-4">
                                    <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Location /></span>
                                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Billing Address</h3>
                                </div>
                                <div className="text-sm">{formatAddress(customer.billing_street, customer.billing_city, customer.billing_state, customer.billing_zip, customer.billing_country)}</div>
                            </div>
                            <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                                <div className="flex items-center gap-2 mb-4">
                                    <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Location /></span>
                                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Shipping Address</h3>
                                </div>
                                <div className="text-sm">{formatAddress(customer.shipping_street, customer.shipping_city, customer.shipping_state, customer.shipping_zip, customer.shipping_country)}</div>
                            </div>
                        </div>

                        <div className="bg-gradient-to-br from-slate-800 to-slate-900 p-6 rounded-2xl relative overflow-hidden shadow-lg">
                            <div className="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full -translate-y-1/2 translate-x-1/2" />
                            <div className="relative">
                                <h3 className="font-semibold text-blue-400 text-sm uppercase tracking-wider mb-3">Internal Notes</h3>
                                <p className="text-slate-300 text-sm leading-relaxed whitespace-pre-line">
                                    {customer.internal_notes || 'No notes recorded.'}
                                </p>
                            </div>
                        </div>

                        {auditLogs.length > 0 && (
                            <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider mb-4">History (audit)</h3>
                                <ul className="space-y-3">
                                    {auditLogs.map(log => (
                                        <li key={log.id} className="text-sm text-slate-600 border-l-2 border-slate-200 pl-4 py-1">
                                            <span className="font-medium text-slate-800">{log.field.replace(/_/g, ' ')}</span>
                                            {' '}changed from <span className="font-mono text-slate-500">{log.old_value || '—'}</span>
                                            {' '}to <span className="font-mono text-slate-700">{log.new_value || '—'}</span>
                                            {log.user && <span className="text-slate-400 text-xs ml-1">by {log.user.name}</span>}
                                            <span className="text-slate-400 text-xs ml-1">{new Date(log.created_at).toLocaleString('en-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                            <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                                <div className="flex items-center gap-2">
                                    <Icons.Document className="text-slate-500" />
                                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Transaction History</h3>
                                </div>
                                <span className="px-3 py-1 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium">
                                    {invoices.length} invoice{invoices.length !== 1 ? 's' : ''}
                                </span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                            <th className="px-6 py-4">Date</th>
                                            <th className="px-6 py-4">Invoice #</th>
                                            <th className="px-6 py-4">Status</th>
                                            <th className="px-6 py-4 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invoices.length > 0 ? invoices.map(inv => (
                                            <tr key={inv.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors group">
                                                <td className="px-6 py-4 text-sm text-slate-500">
                                                    {new Date(inv.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <Link href={route('invoices.edit', inv.id)} className="font-semibold text-slate-800 group-hover:text-blue-600 transition-colors">
                                                        {inv.invoice_number}
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${
                                                        inv.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 
                                                        inv.status === 'unpaid' ? 'bg-rose-100 text-rose-700' : 
                                                        'bg-blue-100 text-blue-700'
                                                    }`}>
                                                        {inv.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-mono text-sm font-semibold text-slate-700">
                                                    RM {parseFloat(inv.total_amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan={4} className="px-6 py-16 text-center text-slate-400 text-sm">
                                                    No invoices yet. Create one to get started.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}