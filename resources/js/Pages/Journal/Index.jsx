import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Journal: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Clock: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

const STATUSES = [
    { value: 'draft', label: 'Draft' },
    { value: 'posted', label: 'Posted' },
];

function getStatusBadge(status) {
    return status === 'posted'
        ? 'bg-forest/10 text-forest'
        : 'bg-surface-alt text-ink';
}

function formatDate(value) {
    if (! value) return '—';
    return new Date(value).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Index({
    auth,
    journals = [],
    can_create = false,
    totalCount = 0,
    draftCount = 0,
    postedCount = 0,
    paginator = {},
    filters = {},
}) {
    const { current_page = 1, last_page = 1, from = 0, to = 0, total = 0 } = paginator;
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 10 } = filters;
    const [searchInput, setSearchInput] = useState(search);

    const applyFilters = (overrides = {}) => {
        router.get(route('journal.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false, preserveScroll: true });
    };

    const handlePost = async (id) => {
        const ok = await confirm({
            title: 'Post to Ledger?',
            text: 'This will lock the journal entry and create ledger postings. This action cannot be undone.',
            confirmText: 'Post Entry',
            icon: 'question',
        });
        if (ok) router.post(route('journal.post', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({
            title: 'Delete Draft?',
            text: 'Are you sure you want to delete this draft journal entry?',
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('journal.destroy', id));
    };

    const emptyMessage = totalCount === 0
        ? 'No journal entries yet. Create your first manual journal to get started.'
        : 'No journal entries match your filters.';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Manual Journals</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Record adjustments and transactions the system cannot see</p>
                    </div>
                    {can_create && (
                        <Link
                            href={route('journal.create')}
                            className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200"
                        >
                            <Icons.Plus /> New Journal Entry
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Manual Journals" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total entries</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Journal /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{totalCount}</p>
                        <p className="text-xs text-white/80 mt-1">Draft · Posted</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Drafts</span>
                            <span className="p-2 rounded-xl bg-mustard/15 text-mustard"><Icons.Clock /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-ink font-mono tabular-nums">{draftCount}</p>
                        <p className="text-xs text-ink-muted mt-1">Not yet posted</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Posted</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-forest font-mono tabular-nums">{postedCount}</p>
                        <p className="text-xs text-ink-muted mt-1">Locked in the ledger</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search by description or reference..."
                        status={statusFilter}
                        statuses={STATUSES}
                        perPage={perPageFilter}
                        onApply={applyFilters}
                        from={from}
                        to={to}
                        total={total}
                    />

                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Date</th>
                                    <th className="px-4 sm:px-6 py-3">Reference</th>
                                    <th className="px-4 sm:px-6 py-3">Description</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Debit</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Credit</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {journals.length > 0 ? journals.map((journal) => (
                                    <tr key={journal.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 font-medium text-ink whitespace-nowrap">
                                            {formatDate(journal.date)}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <span className="font-mono text-sm font-semibold text-ink">
                                                {journal.reference_number || '—'}
                                            </span>
                                            {journal.reference_type && (
                                                <p className="text-xs text-ink-muted mt-0.5">{journal.reference_type}</p>
                                            )}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 max-w-xs">
                                            <Link
                                                href={journal.status === 'posted' ? route('general-ledger.show', journal.id) : route('journal.edit', journal.id)}
                                                className="block font-medium text-ink hover:text-terracotta truncate"
                                                title={journal.description}
                                            >
                                                {journal.description || 'Untitled entry'}
                                            </Link>
                                            <p className="text-xs text-ink-muted mt-0.5">{journal.items_count} {journal.items_count === 1 ? 'line' : 'lines'}</p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(journal.status)}`}>
                                                {journal.status}
                                            </span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right font-mono text-sm font-semibold text-ink tabular-nums">
                                            {formatCurrency(journal.total_debit, 'MYR')}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right font-mono text-sm font-semibold text-ink tabular-nums">
                                            {formatCurrency(journal.total_credit, 'MYR')}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            {journal.status === 'posted' ? (
                                                <span className="text-[10px] text-ink-muted font-semibold uppercase tracking-wider">Locked</span>
                                            ) : (
                                                <RowActionsMenu items={[
                                                    { label: 'Edit', href: route('journal.edit', journal.id), icon: <ActionIcons.Pencil />, show: auth.permissions.includes('journal.edit') },
                                                    { label: 'Post to ledger', icon: <ActionIcons.Check />, show: auth.permissions.includes('journal.post'), onClick: () => handlePost(journal.id) },
                                                    { label: 'Delete draft', icon: <ActionIcons.Trash />, danger: true, show: auth.permissions.includes('journal.delete'), onClick: () => handleDelete(journal.id) },
                                                ]} />
                                            )}
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-16 text-center text-ink-muted text-sm">{emptyMessage}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {journals.length > 0 ? journals.map((journal) => (
                            <div key={journal.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={journal.status === 'posted' ? route('general-ledger.show', journal.id) : route('journal.edit', journal.id)}
                                            className="font-semibold text-ink hover:text-terracotta"
                                        >
                                            {journal.description || 'Untitled entry'}
                                        </Link>
                                        <p className="text-xs text-ink-muted mt-0.5">
                                            {formatDate(journal.date)} · {journal.reference_number || 'No reference'}
                                        </p>
                                        <p className="text-sm font-mono font-semibold text-ink mt-1">
                                            {formatCurrency(journal.total_debit, 'MYR')}
                                        </p>
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(journal.status)}`}>
                                            {journal.status}
                                        </span>
                                    </div>
                                    {journal.status !== 'posted' && (
                                        <RowActionsMenu items={[
                                            { label: 'Edit', href: route('journal.edit', journal.id), icon: <ActionIcons.Pencil />, show: auth.permissions.includes('journal.edit') },
                                            { label: 'Post to ledger', icon: <ActionIcons.Check />, show: auth.permissions.includes('journal.post'), onClick: () => handlePost(journal.id) },
                                            { label: 'Delete draft', icon: <ActionIcons.Trash />, danger: true, show: auth.permissions.includes('journal.delete'), onClick: () => handleDelete(journal.id) },
                                        ]} />
                                    )}
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">{emptyMessage}</div>
                        )}
                    </div>

                    <IndexPagination
                        currentPage={current_page}
                        lastPage={last_page}
                        onPage={(page) => applyFilters({ page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
