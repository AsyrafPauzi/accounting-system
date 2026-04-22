import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const Icons = {
    BuildingOffice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Index({ auth, suppliers = [], totalAp = 0 }) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');

    const filteredSuppliers = suppliers.filter(s => {
        const matchesSearch = (s.name || '').toLowerCase().includes(search.toLowerCase()) || (s.code || '').toLowerCase().includes(search.toLowerCase());
        const matchesStatus = statusFilter === '' || (statusFilter === 'active' && s.is_active) || (statusFilter === 'suspended' && !s.is_active);
        return matchesSearch && matchesStatus;
    });

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Supplier Directory</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">Vendor database for bills and purchases</p>
                    </div>
                    <Link
                        href={route('suppliers.create')}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                    >
                        <Icons.Plus /> Add supplier
                    </Link>
                </div>
            }
        >
            <Head title="Suppliers" />


            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-amber-600 to-orange-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total vendors</span>
                            <span className="p-2 rounded-xl bg-white/10"><Icons.BuildingOffice /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{suppliers.length} supplier{suppliers.length !== 1 ? 's' : ''}</p>
                        <p className="text-xs text-amber-100 mt-1">Active and suspended</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total payables</span>
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-600"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-xl font-bold text-rose-600 font-mono tabular-nums">RM {formatMoney(totalAp)}</p>
                        <p className="text-xs text-slate-500 mt-1">Outstanding across suppliers</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50">
                        <div className="relative flex-1 min-w-[200px] max-w-sm">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                placeholder="Search by name or code..."
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
                        {(search || statusFilter) && (
                            <button type="button" onClick={() => { setSearch(''); setStatusFilter(''); }} className="text-xs font-semibold text-blue-600 hover:text-blue-700">Clear</button>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            {filteredSuppliers.length} of {suppliers.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Supplier</th>
                                    <th className="px-6 py-4 hidden md:table-cell">Contact</th>
                                    <th className="px-6 py-4">Terms</th>
                                    <th className="px-6 py-4 text-right">Balance due</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredSuppliers.length > 0 ? filteredSuppliers.map(supplier => (
                                    <tr key={supplier.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors group">
                                        <td className="px-6 py-4">
                                            <Link href={route('suppliers.show', supplier.id)} className="flex items-center gap-3 group/link">
                                                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                                    {(supplier.name || '?').charAt(0)}
                                                </div>
                                                <div>
                                                    <span className="font-semibold text-slate-800 group-hover/link:text-blue-600 transition-colors">{supplier.name}</span>
                                                    <p className="text-xs text-slate-500 font-mono mt-0.5">{supplier.code}</p>
                                                    {!supplier.is_active && (
                                                        <span className="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 mt-1">Suspended</span>
                                                    )}
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 hidden md:table-cell">
                                            <div className="text-xs text-slate-600">
                                                {supplier.contact_person || supplier.email || '—'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                Net {supplier.payment_terms} days
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <span className={`font-mono text-sm font-semibold tabular-nums ${(parseFloat(supplier.balance) || 0) > 0 ? 'text-rose-600' : 'text-slate-500'}`}>
                                                RM {formatMoney(supplier.balance)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link
                                                    href={route('suppliers.edit', supplier.id)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                                                >
                                                    <Icons.Pencil /> Edit
                                                </Link>
                                                <Link
                                                    href={route('suppliers.show', supplier.id)}
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
                                                {search ? 'No suppliers match your search.' : 'No suppliers yet. Add your first vendor to get started.'}
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
