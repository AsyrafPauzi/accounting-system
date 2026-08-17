import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { confirm, alertUpgrade } from '@/utils/swal';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Folder: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    DocumentArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
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
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Ledger</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Chart of accounts</h1>
                        <p className="text-ink-muted text-sm">
                            Categories used when posting invoices and in your financial reports.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {accounts.length === 0 && (
                            <button
                                type="button"
                                onClick={() => router.post(route('chart-of-accounts.seed-default'))}
                                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-mustard/15 border border-mustard/40 hover:bg-mustard/15 transition-colors"
                            >
                                Seed default chart
                            </button>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <a
                                href={route('chart-of-accounts.export.csv')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors text-sm"
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
                                        ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                        : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                                }`}
                            >
                                <Icons.DocumentArrowDown /> Download PDF
                                {!auth.planPermissions['reports.export.full'] && (
                                    <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                                )}
                            </a>
                            {auth.planPermissions['accounts.create'] && auth.permissions.includes('accounts.create') && (
                                <Link
                                    href={route('chart-of-accounts.create')}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg  transition-all duration-200"
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
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total accounts</span>
                            <span className="p-2 rounded-xl bg-surface/10">
                                <Icons.Folder />
                            </span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{accounts.length}</p>
                        <p className="text-xs text-terracotta mt-1">All types</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Active</span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink tabular-nums">{activeCount}</p>
                        <p className="text-xs text-ink-muted mt-1">Available for posting</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Assets</span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink tabular-nums">{(groupedByType.asset || []).length}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Revenue &amp; Expense</span>
                        </div>
                        <p className="text-xl font-display font-medium text-ink tabular-nums">
                            {((groupedByType.income || []).length + (groupedByType.expense || []).length)}
                        </p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-[200px] max-w-sm">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                placeholder="Search by code or name..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10 w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink placeholder-ink-muted/60 focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors"
                            />
                        </div>
                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
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
                                className="text-xs font-semibold text-terracotta hover:text-terracotta"
                            >
                                Clear
                            </button>
                        )}
                        <span className="text-ink-muted text-sm font-medium ml-auto">
                            {filteredAccounts.length} of {accounts.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Code</th>
                                    <th className="px-6 py-4">Name</th>
                                    <th className="px-6 py-4">Type</th>
                                    <th className="px-6 py-4">Parent</th>
                                    <th className="px-6 py-4 max-w-[200px]">Description</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredAccounts.length > 0 ? (
                                    filteredAccounts.map((acc) => (
                                        <tr
                                            key={acc.id}
                                            className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors"
                                        >
                                            <td className="px-6 py-4 font-mono text-ink font-semibold">{acc.code}</td>
                                            <td className="px-6 py-4 font-medium text-ink">
                                                <div className="flex items-center gap-2">
                                                    <span>{acc.name}</span>
                                                    {acc.sub_type_label && (
                                                        <span className="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-surface-alt text-terracotta uppercase tracking-wide">
                                                            {acc.sub_type_label}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-alt text-ink">
                                                    {acc.type_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-ink-muted font-mono text-xs">{acc.parent_code || '—'}</td>
                                            <td className="px-6 py-4 text-ink-muted text-xs max-w-[200px] truncate" title={acc.description || ''}>
                                                {acc.description ? (acc.description.length > 50 ? acc.description.slice(0, 50) + '…' : acc.description) : '—'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold ${acc.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}
                                                >
                                                    {acc.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <RowActionsMenu items={[
                                                    { label: 'Edit', href: route('chart-of-accounts.edit', acc.id), icon: <ActionIcons.Pencil />, show: Boolean(auth.planPermissions['accounts.edit']) && auth.permissions.includes('accounts.edit') },
                                                    { label: 'Delete', icon: <ActionIcons.Trash />, danger: true, show: Boolean(auth.planPermissions['accounts.delete']) && auth.permissions.includes('accounts.delete'), onClick: () => handleDelete(acc.id, acc.name) },
                                                ]} />
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-16 text-center">
                                            <p className="text-ink-muted text-sm font-medium">
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
