import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm, alertUpgrade } from '@/utils/swal';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';
import IndexPagination from '@/Components/IndexPagination';
import { formatCurrency } from '@/utils/currency';

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

export default function Index({ auth, accounts = [] }) {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [perPage, setPerPage] = useState(25);
    const [currentPage, setCurrentPage] = useState(1);

    const filteredAccounts = useMemo(() => accounts.filter((a) => {
        const matchesSearch =
            (a.code || '').toLowerCase().includes(search.toLowerCase()) ||
            (a.name || '').toLowerCase().includes(search.toLowerCase());
        const matchesType = typeFilter === '' || a.type === typeFilter;
        return matchesSearch && matchesType;
    }), [accounts, search, typeFilter]);

    const lastPage = Math.max(1, Math.ceil(filteredAccounts.length / perPage) || 1);
    const safePage = Math.min(currentPage, lastPage);

    useEffect(() => {
        setCurrentPage(1);
    }, [search, typeFilter, perPage]);

    useEffect(() => {
        if (currentPage > lastPage) {
            setCurrentPage(lastPage);
        }
    }, [currentPage, lastPage]);

    const paginatedAccounts = useMemo(() => {
        const start = (safePage - 1) * perPage;
        return filteredAccounts.slice(start, start + perPage);
    }, [filteredAccounts, safePage, perPage]);

    const from = filteredAccounts.length === 0 ? 0 : (safePage - 1) * perPage + 1;
    const to = filteredAccounts.length === 0 ? 0 : Math.min(safePage * perPage, filteredAccounts.length);

    const ledgerUrl = (code) => route('general-ledger.report', { account_code: code, from: 'coa' });

    const activeCount = accounts.filter((a) => a.is_active).length;
    const totalBalanceAccounts = accounts.filter((a) => Math.abs(Number(a.balance || 0)) > 0.009).length;
    const netBalance = accounts.reduce((sum, a) => sum + Number(a.balance || 0), 0);

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
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-terracotta">Ledger</p>
                            <h1 className="font-display text-xl lg:text-2xl font-medium text-ink tracking-tight">Chart of accounts</h1>
                        </div>
                        <p className="text-ink-muted text-sm mt-1 truncate">
                            Categories used when posting invoices and in your financial reports.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                        <a
                            href={route('chart-of-accounts.export.csv')}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors text-sm"
                        >
                            <Icons.ArrowDownTray /> CSV
                        </a>
                        <a
                            href={auth.planPermissions['reports.export.full'] ? route('chart-of-accounts.export.pdf') : '#'}
                            onClick={(e) => {
                                if (!auth.planPermissions['reports.export.full']) {
                                    e.preventDefault();
                                    alertUpgrade('Professional PDF exports are available on the Corporate plan.');
                                }
                            }}
                            className={`inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold transition-colors text-sm ${
                                auth.planPermissions['reports.export.full']
                                    ? 'text-ink bg-surface border border-border-warm hover:bg-cream'
                                    : 'text-ink-muted bg-cream border border-border-warm cursor-pointer hover:bg-surface-alt'
                            }`}
                        >
                            <Icons.DocumentArrowDown /> PDF
                            {!auth.planPermissions['reports.export.full'] && (
                                <svg className="w-3 h-3 text-mustard" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" /></svg>
                            )}
                        </a>
                        {auth.planPermissions['accounts.create'] && auth.permissions.includes('accounts.create') && (
                            <Link
                                href={route('chart-of-accounts.create')}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200 text-sm"
                            >
                                <Icons.Plus /> Add account
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Chart of Accounts" />


            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 border-b border-border-warm bg-cream/40">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Chart overview</p>
                        <h3 className="mt-1 text-lg font-semibold text-ink">Your accounts and balances</h3>
                    </div>
                    <div className="grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-border-warm">
                        <div className="p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Accounts</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums text-ink">{accounts.length}</p>
                            <p className="text-xs text-ink-muted mt-1">Total chart lines</p>
                        </div>
                        <div className="p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Active</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums text-ink">{activeCount}</p>
                            <p className="text-xs text-ink-muted mt-1">Available for posting</p>
                        </div>
                        <div className="p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">With balance</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums text-ink">{totalBalanceAccounts}</p>
                            <p className="text-xs text-ink-muted mt-1">Used in real postings</p>
                        </div>
                        <div className="p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Net balance</p>
                            <p className="mt-1 text-lg font-bold tabular-nums text-ink">{formatCurrency(netBalance, 'MYR')}</p>
                            <p className="text-xs text-ink-muted mt-1">Signed total across accounts</p>
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-[220px] max-w-full sm:max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by code or name..."
                                className="w-full pl-9 pr-4 py-2.5 border border-border-warm rounded-xl text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                            />
                        </div>

                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[160px]"
                        >
                            {TYPE_OPTIONS.map((opt) => (
                                <option key={opt.value || 'all'} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                            className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]"
                        >
                            <option value={10}>10 per page</option>
                            <option value={25}>25 per page</option>
                            <option value={50}>50 per page</option>
                            <option value={100}>100 per page</option>
                        </select>

                        {(search || typeFilter) && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setTypeFilter('');
                                }}
                                className="px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                            >
                                Clear
                            </button>
                        )}

                        <span className="text-ink-muted text-sm font-medium ml-auto whitespace-nowrap">
                            {filteredAccounts.length > 0 ? `${from}–${to} of ${filteredAccounts.length}` : '0 of 0'}
                        </span>
                    </div>

                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 py-3">Code</th>
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3 text-right">Balance</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Parent</th>
                                    <th className="px-4 py-3 max-w-[200px]">Description</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {paginatedAccounts.length > 0 ? (
                                    paginatedAccounts.map((acc) => (
                                        <tr
                                            key={acc.id}
                                            className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors"
                                        >
                                            <td className="px-4 py-3 font-mono text-ink font-semibold">
                                                {acc.code}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-ink">
                                                <div className="flex items-center gap-2">
                                                    <Link href={ledgerUrl(acc.code)} className="hover:text-terracotta">
                                                        {acc.name}
                                                    </Link>
                                                    {acc.sub_type_label && (
                                                        <span className="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-surface-alt text-terracotta uppercase tracking-wide">
                                                            {acc.sub_type_label}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono tabular-nums text-ink">
                                                {formatCurrency(acc.balance ?? 0, 'MYR')}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-alt text-ink">
                                                    {acc.type_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-ink-muted font-mono text-xs">{acc.parent_code || '—'}</td>
                                            <td className="px-6 py-4 text-ink-muted text-xs max-w-[200px] truncate" title={acc.description || ''}>
                                                {acc.description ? (acc.description.length > 50 ? acc.description.slice(0, 50) + '…' : acc.description) : '—'}
                                            </td>
                                            <td className="px-4 py-3">
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
                                        <td colSpan={8} className="px-4 py-16 text-center">
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

                    <div className="md:hidden divide-y divide-border-warm">
                        {paginatedAccounts.length > 0 ? (
                            paginatedAccounts.map((acc) => (
                                <div key={acc.id} className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-mono font-semibold text-ink">{acc.code}</span>
                                                <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold ${acc.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                                    {acc.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                            <Link href={ledgerUrl(acc.code)} className="block mt-1 font-semibold text-ink hover:text-terracotta">
                                                {acc.name}
                                            </Link>
                                            <p className="text-xs text-ink-muted mt-1">
                                                {acc.type_label}{acc.parent_code ? ` · Parent ${acc.parent_code}` : ''}
                                            </p>
                                        </div>
                                        <div className="text-right shrink-0">
                                            <p className="font-mono tabular-nums font-semibold text-ink">{formatCurrency(acc.balance ?? 0, 'MYR')}</p>
                                        </div>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="px-4 py-16 text-center">
                                <p className="text-ink-muted text-sm font-medium">
                                    {search || typeFilter
                                        ? 'No accounts match your filters.'
                                        : 'No accounts yet. Add your first account to build your chart.'}
                                </p>
                            </div>
                        )}
                    </div>

                    <IndexPagination
                        currentPage={safePage}
                        lastPage={lastPage}
                        onPage={setCurrentPage}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
