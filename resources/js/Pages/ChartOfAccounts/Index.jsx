import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { confirm, alertUpgrade } from '@/utils/swal';

const Icons = {
    Folder: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
};

const TYPE_OPTIONS = [
    { value: '', label: 'All types' },
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'income', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

export default function Index({ auth, accounts = [], groupedByType = {} }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');

    const filteredAccounts = accounts.filter((a) => {
        const matchesSearch =
            (a.code || '').toLowerCase().includes(search.toLowerCase()) ||
            (a.name || '').toLowerCase().includes(search.toLowerCase());
        const matchesType = typeFilter === '' || a.type === typeFilter;
        return matchesSearch && matchesType;
    });

    const activeCount = accounts.filter((a) => a.is_active).length;

    const handleDelete = async (id, name) => {
        const ok = await confirm({
            title: 'Delete account?',
            text: `Account "${name}" will be removed. This cannot be undone if the account is not in use.`,
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('chart-of-accounts.destroy', id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Chart of Accounts</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Standardized categories (Assets, Liabilities, Equity, Revenue, Expense). Used when posting invoices and in financial reports.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {accounts.length === 0 && (
                            <button
                                type="button"
                                onClick={() => router.post(route('chart-of-accounts.seed-default'))}
                                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-700 bg-amber-100 border border-amber-200 hover:bg-amber-200 transition-colors"
                            >
                                Seed default chart
                            </button>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <a
                                href={route('chart-of-accounts.export.csv')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors text-sm"
                            >
                                <Icons.ArrowDownTray /> Download CSV
                            </a>
                            <a
                                href={auth.planPermissions['reports.export.full'] ? route('chart-of-accounts.export.pdf') : '#'}
                                onClick={(e) => {
                                    if (!auth.planPermissions['reports.export.full']) {
                                        e.preventDefault();
                                        alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                    }
                                }}
                                className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm ${
                                    auth.planPermissions['reports.export.full']
                                        ? 'text-slate-700 bg-white border border-slate-200 hover:bg-slate-50'
                                        : 'text-slate-400 bg-slate-50 border border-slate-100 cursor-pointer hover:bg-slate-100'
                                }`}
                            >
                                <Icons.DocumentArrowDown /> Download PDF
                                {!auth.planPermissions['reports.export.full'] && (
                                    <svg className="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                                )}
                            </a>
                            {auth.planPermissions['accounts.create'] && auth.permissions.includes('accounts.create') && (
                                <Link
                                    href={route('chart-of-accounts.create')}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                                >
                                    <Icons.Plus /> Add account
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Chart of Accounts" />


            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-600 text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total accounts</span>
                            <span className="p-2 rounded-xl bg-white/10">
                                <Icons.Folder />
                            </span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{accounts.length}</p>
                        <p className="text-xs text-blue-100 mt-1">All types</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Active</span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 tabular-nums">{activeCount}</p>
                        <p className="text-xs text-slate-500 mt-1">Available for posting</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Assets</span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 tabular-nums">{(groupedByType.asset || []).length}</p>
                    </div>
                    <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Revenue &amp; Expense</span>
                        </div>
                        <p className="text-xl font-bold text-slate-800 tabular-nums">
                            {((groupedByType.income || []).length + (groupedByType.expense || []).length)}
                        </p>
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
                                placeholder="Search by code or name..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10 w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500"
                        >
                            {TYPE_OPTIONS.map((opt) => (
                                <option key={opt.value || 'all'} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                        {(search || typeFilter) && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setTypeFilter('');
                                }}
                                className="text-xs font-semibold text-blue-600 hover:text-blue-700"
                            >
                                Clear
                            </button>
                        )}
                        <span className="text-slate-500 text-sm font-medium ml-auto">
                            {filteredAccounts.length} of {accounts.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Code</th>
                                    <th className="px-6 py-4">Name</th>
                                    <th className="px-6 py-4">Type</th>
                                    <th className="px-6 py-4">Parent</th>
                                    <th className="px-6 py-4 max-w-[200px]">Description</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right w-32">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredAccounts.length > 0 ? (
                                    filteredAccounts.map((acc) => (
                                        <tr
                                            key={acc.id}
                                            className="border-b border-slate-50 last:border-0 hover:bg-slate-50/80 transition-colors"
                                        >
                                            <td className="px-6 py-4 font-mono text-slate-800 font-semibold">{acc.code}</td>
                                            <td className="px-6 py-4 font-medium text-slate-800">
                                                <div className="flex items-center gap-2">
                                                    <span>{acc.name}</span>
                                                    {acc.sub_type_label && (
                                                        <span className="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-blue-50 text-blue-600 uppercase tracking-wide">
                                                            {acc.sub_type_label}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                                                    {acc.type_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-slate-500 font-mono text-xs">{acc.parent_code || '—'}</td>
                                            <td className="px-6 py-4 text-slate-500 text-xs max-w-[200px] truncate" title={acc.description || ''}>
                                                {acc.description ? (acc.description.length > 50 ? acc.description.slice(0, 50) + '…' : acc.description) : '—'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold ${acc.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                                >
                                                    {acc.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {auth.planPermissions['accounts.edit'] && auth.permissions.includes('accounts.edit') && (
                                                        <Link
                                                            href={route('chart-of-accounts.edit', acc.id)}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                                                        >
                                                            <Icons.Pencil /> Edit
                                                        </Link>
                                                    )}
                                                    {auth.planPermissions['accounts.delete'] && auth.permissions.includes('accounts.delete') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDelete(acc.id, acc.name)}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors"
                                                        >
                                                            <Icons.Trash /> Delete
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-16 text-center">
                                            <p className="text-slate-400 text-sm font-medium">
                                                {search || typeFilter
                                                    ? 'No accounts match your filters.'
                                                    : 'No accounts yet. Add your first account to build your chart.'}
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
