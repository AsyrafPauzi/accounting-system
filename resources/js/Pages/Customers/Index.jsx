import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

export default function Index({ auth, customers = [] }) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [segmentFilter, setSegmentFilter] = useState('');
    const [creditHoldFilter, setCreditHoldFilter] = useState('');

    const filteredCustomers = customers.filter(c => {
        const matchesSearch = (c.name || '').toLowerCase().includes(search.toLowerCase()) || (c.code || '').toLowerCase().includes(search.toLowerCase());
        const matchesStatus = statusFilter === '' || (statusFilter === 'active' && c.is_active) || (statusFilter === 'suspended' && !c.is_active);
        const matchesSegment = segmentFilter === '' || (c.segment || '') === segmentFilter;
        const matchesCreditHold = creditHoldFilter === '' || (creditHoldFilter === 'yes' && c.credit_hold) || (creditHoldFilter === 'no' && !c.credit_hold);
        return matchesSearch && matchesStatus && matchesSegment && matchesCreditHold;
    });

    const totalOutstanding = customers.reduce((sum, c) => sum + (parseFloat(c.balance) || 0), 0);

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Customer Directory</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Manage corporate relationships and credit profiles</p>
                    </div>
                    <Link 
                        href={route('customers.create')} 
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                    >
                        <Icons.Plus /> Onboard Customer
                    </Link>
                </div>
            }
        >
            <Head title="Customers" />

            <div className="space-y-6">
                {/* KPI row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total Portfolio</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.Users /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">
                            {customers.length} account{customers.length !== 1 ? 's' : ''}
                        </p>
                        <p className="text-xs text-blue-100 mt-1">Active and suspended</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total Outstanding</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">
                            RM {totalOutstanding.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">AR across all customers</p>
                    </div>
                </div>

                {/* Table Card */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50">
                        <div className="relative flex-1 min-w-[200px] max-w-sm">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input 
                                type="text" 
                                placeholder="Search by company name or code..." 
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="pl-10 w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                        <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} className="border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 min-w-[140px]">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <select value={segmentFilter} onChange={e => setSegmentFilter(e.target.value)} className="border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 min-w-[140px]">
                            <option value="">All segments</option>
                            <option value="SME">SME</option>
                            <option value="Enterprise">Enterprise</option>
                            <option value="Govt">Govt</option>
                        </select>
                        <select value={creditHoldFilter} onChange={e => setCreditHoldFilter(e.target.value)} className="border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 min-w-[160px]">
                            <option value="">Credit hold: All</option>
                            <option value="yes">On hold</option>
                            <option value="no">Not on hold</option>
                        </select>
                        {(search || statusFilter || segmentFilter || creditHoldFilter) && (
                            <button type="button" onClick={() => { setSearch(''); setStatusFilter(''); setSegmentFilter(''); setCreditHoldFilter(''); }} className="text-xs font-semibold text-blue-600 hover:text-blue-700">Clear</button>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            {filteredCustomers.length} of {customers.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4 hidden md:table-cell">Tax / LHDN</th>
                                    <th className="px-6 py-4">Terms</th>
                                    <th className="px-6 py-4 text-right">Outstanding</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredCustomers.length > 0 ? filteredCustomers.map(customer => (
                                    <tr key={customer.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors group">
                                        <td className="px-6 py-4">
                                            <Link href={route('customers.show', customer.id)} className="flex items-center gap-3 group/link">
                                                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                                    {(customer.name || '?').charAt(0)}
                                                </div>
                                                <div>
                                                    <span className="font-semibold text-slate-800 group-hover/link:text-blue-600 transition-colors">{customer.name}</span>
                                                    <p className="text-xs text-slate-500 font-mono mt-0.5">{customer.code}</p>
                                                    <div className="flex flex-wrap gap-1 mt-1">
                                                        {customer.credit_hold && <span className="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700">Credit hold</span>}
                                                        {customer.has_overdue && <span className="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-700">Overdue</span>}
                                                        {customer.risk_rating === 'high' && <span className="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-700">High risk</span>}
                                                    </div>
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 hidden md:table-cell">
                                            <div className="text-xs text-slate-600 space-y-0.5">
                                                <span>TIN: {customer.tin || '—'}</span>
                                                <span className="block text-slate-400">BRN: {customer.brn || '—'}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                Net {customer.payment_terms} Days
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <span className={`font-mono text-sm font-semibold tabular-nums ${(parseFloat(customer.balance) || 0) > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                RM {(parseFloat(customer.balance) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link 
                                                    href={route('customers.edit', customer.id)} 
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                                                >
                                                    <Icons.Pencil /> Edit
                                                </Link>
                                                <Link 
                                                    href={route('customers.show', customer.id)} 
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors"
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
                                                {search ? 'No customers match your search.' : 'No customers yet. Onboard your first customer to get started.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}