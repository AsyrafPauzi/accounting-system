import React, { useState } from 'react';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    Quote: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
    Eye: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>,
    Pdf: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Mail: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ChevronLeft: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    EllipsisVertical: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>,
};

const STATUS_STYLES = {
    draft: 'bg-surface-alt text-ink',
    sent: 'bg-blue-100 text-blue-800',
    accepted: 'bg-forest/10 text-forest',
    rejected: 'bg-terracotta/10 text-terracotta',
    expired: 'bg-amber-100 text-amber-800',
    converted: 'bg-violet-100 text-violet-800',
};

export default function Index({
    auth,
    estimates,
    filters = {},
    counts = {},
    totalCount = 0,
    openValue = 0,
    convertedCount = 0,
    base_currency = 'MYR',
}) {
    const items = estimates?.data || [];
    const {
        search = '',
        status: statusFilter = '',
        per_page: perPageFilter = 25,
    } = filters;

    const [searchInput, setSearchInput] = useState(search);
    const [emailingId, setEmailingId] = useState(null);
    const [selectedIds, setSelectedIds] = useState([]);

    const pageIds = items.map((e) => e.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const toggleId = (id) => setSelectedIds((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));
    const toggleAll = () => setSelectedIds(allSelected ? selectedIds.filter((id) => !pageIds.includes(id)) : [...new Set([...selectedIds, ...pageIds])]);

    const planPermissions = auth?.planPermissions ?? {};
    const canEmail = (auth.permissions || []).includes('estimates.email') && Boolean(planPermissions['estimates.email']);

    const applyFilters = (overrides = {}) => {
        router.get(route('estimates.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const handleDelete = async (estimate) => {
        const ok = await confirm({
            title: 'Delete this estimate?',
            text: `Remove ${estimate.estimate_number}? You can only do this if it has not been converted.`,
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('estimates.destroy', estimate.id));
    };

    const bulkEmail = async () => {
        const ok = await confirm({ title: `Email ${selectedIds.length} estimate PDF(s)?`, text: 'Queued to each customer email on file. Rows without email are skipped.', confirmText: 'Send', icon: 'question' });
        if (ok) router.post(route('estimates.bulk-email'), { ids: selectedIds });
    };

    const bulkPdf = () => {
        const params = new URLSearchParams();
        selectedIds.forEach((id) => params.append('ids[]', id));
        window.open(`${route('estimates.bulk-pdf')}?${params.toString()}`, '_blank');
    };

    const handleEmail = async (estimate) => {
        if (!estimate.customer_email) {
            window.alert('This customer has no email on file. Add one to the customer record first.');
            return;
        }
        const ok = await confirm({
            title: 'Email this estimate?',
            text: `Send ${estimate.estimate_number} to ${estimate.customer_email}?`,
            confirmText: 'Send email',
            icon: 'info',
        });
        if (!ok) return;
        router.post(route('estimates.email', estimate.id), {}, {
            preserveScroll: true,
            onStart: () => setEmailingId(estimate.id),
            onFinish: () => setEmailingId(null),
        });
    };

    const currentPage = estimates?.current_page || 1;
    const lastPage = estimates?.last_page || 1;
    const from = estimates?.from || 0;
    const to = estimates?.to || 0;
    const total = estimates?.total || 0;

    const Actions = ({ estimate }) => (
        <Menu as="div" className="relative inline-block text-left">
            <MenuButton className="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-border-warm bg-surface text-ink hover:bg-cream hover:border-border-warm transition-colors">
                <Icons.EllipsisVertical />
            </MenuButton>
            <MenuItems
                anchor="bottom end"
                transition
                className="z-[100] mt-2 w-52 origin-top-right rounded-xl bg-surface shadow-xl ring-1 ring-black/5 focus:outline-none py-1 transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                <MenuItem>
                    <Link href={route('estimates.show', estimate.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                        <Icons.ChevronRight /> Open
                    </Link>
                </MenuItem>
                <MenuItem>
                    <a href={route('estimates.pdf', estimate.id)} target="_blank" rel="noreferrer" className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                        <Icons.Pdf /> Download PDF
                    </a>
                </MenuItem>
                {canEmail && estimate.customer_email && (
                    <MenuItem>
                        <button type="button" onClick={() => handleEmail(estimate)} disabled={emailingId === estimate.id} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream disabled:opacity-50">
                            <Icons.Mail /> {emailingId === estimate.id ? 'Emailing…' : 'Email'}
                        </button>
                    </MenuItem>
                )}
                {auth.permissions.includes('estimates.create') && (
                    <MenuItem>
                        <button type="button" onClick={() => router.post(route('estimates.duplicate', estimate.id))} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                            Duplicate
                        </button>
                    </MenuItem>
                )}
                {auth.permissions.includes('estimates.edit') && estimate.status !== 'converted' && (
                    <MenuItem>
                        <Link href={route('estimates.edit', estimate.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                            <Icons.Pencil /> Edit
                        </Link>
                    </MenuItem>
                )}
                {auth.permissions.includes('estimates.delete') && estimate.status !== 'converted' && (
                    <MenuItem>
                        <button type="button" onClick={() => handleDelete(estimate)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-terracotta hover:bg-terracotta/10">
                            <Icons.Trash /> Delete
                        </button>
                    </MenuItem>
                )}
            </MenuItems>
        </Menu>
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div>
                        <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Estimates</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Create, send and convert quotations</p>
                    </div>
                    {auth.permissions.includes('estimates.create') && (
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('estimates.batch')} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                                Batch
                            </Link>
                            <Link href={route('estimates.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200">
                                <Icons.Plus /> New estimate
                            </Link>
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Estimates" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total estimates</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Quote /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{totalCount}</p>
                        <p className="text-xs text-white/80 mt-1">
                            Draft {counts.draft || 0} · Sent {counts.sent || 0} · Accepted {counts.accepted || 0}
                        </p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Open quotes</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-terracotta font-mono tabular-nums">
                            {formatCurrency(openValue, base_currency)}
                        </p>
                        <p className="text-xs text-ink-muted mt-1">Draft · Sent · Accepted</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Converted</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-forest font-mono tabular-nums">{convertedCount}</p>
                        <p className="text-xs text-ink-muted mt-1">Became invoices</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    {selectedIds.length > 0 && (
                        <div className="px-4 sm:px-6 py-3 border-b border-border-warm bg-cream flex flex-wrap items-center gap-2">
                            <span className="text-sm font-semibold text-ink">{selectedIds.length} selected</span>
                            <button type="button" onClick={bulkPdf} className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm bg-surface hover:bg-cream">Download PDFs</button>
                            {canEmail && (
                                <button type="button" onClick={bulkEmail} className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm bg-surface hover:bg-cream">Email selected</button>
                            )}
                            <button type="button" onClick={() => setSelectedIds([])} className="text-xs text-ink-muted">Clear</button>
                        </div>
                    )}

                    <form
                        onSubmit={(e) => { e.preventDefault(); applyFilters({ page: 1 }); }}
                        className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50"
                    >
                        <div className="relative flex-1 min-w-0 max-w-full sm:max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input
                                type="text"
                                placeholder="Search by estimate # or customer..."
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                                onBlur={() => applyFilters({ page: 1 })}
                                className="pl-9 w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                            />
                        </div>
                        <select
                            value={statusFilter}
                            onChange={(e) => applyFilters({ status: e.target.value, page: 1 })}
                            className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]"
                        >
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                            <option value="expired">Expired</option>
                            <option value="converted">Converted</option>
                        </select>
                        <select
                            value={perPageFilter}
                            onChange={(e) => applyFilters({ per_page: Number(e.target.value), page: 1 })}
                            className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]"
                        >
                            <option value={10}>10 per page</option>
                            <option value={25}>25 per page</option>
                            <option value={50}>50 per page</option>
                            <option value={100}>100 per page</option>
                        </select>
                        <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta">Apply</button>
                        <span className="text-ink-muted text-sm font-medium ml-auto whitespace-nowrap">
                            {total > 0 ? `${from}–${to} of ${total}` : '0 of 0'}
                        </span>
                    </form>

                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-3 py-3 w-10"><input type="checkbox" checked={allSelected} onChange={toggleAll} className="rounded border-border-warm" /></th>
                                    <th className="px-4 sm:px-6 py-3">Estimate</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Amount</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length > 0 ? items.map((estimate) => (
                                    <tr key={estimate.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                        <td className="px-3 py-3 sm:py-4">
                                            <input type="checkbox" checked={selectedIds.includes(estimate.id)} onChange={() => toggleId(estimate.id)} className="rounded border-border-warm" />
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <Link href={route('estimates.show', estimate.id)} className="block group/link">
                                                <span className="font-semibold text-ink group-hover/link:text-terracotta">{estimate.estimate_number}</span>
                                                <p className="text-xs text-ink-muted mt-0.5">
                                                    {estimate.issue_date
                                                        ? new Date(estimate.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })
                                                        : '—'}
                                                    {estimate.expiry_date && (
                                                        <> · exp {new Date(estimate.expiry_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short' })}</>
                                                    )}
                                                </p>
                                            </Link>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <div className="font-medium text-ink">{estimate.customer?.name || '—'}</div>
                                            <p className="text-xs text-ink-muted truncate max-w-[140px] sm:max-w-none">{estimate.customer_email || 'No email'}</p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${STATUS_STYLES[estimate.status] || 'bg-surface-alt text-ink-muted'}`}>
                                                {estimate.status}
                                            </span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <div className="font-mono text-sm font-semibold text-ink">
                                                {formatCurrency(estimate.total_amount, estimate.currency || base_currency)}
                                            </div>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <Actions estimate={estimate} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {totalCount === 0 ? 'No estimates yet. Create your first quotation to get started.' : 'No estimates match your filters.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {items.length > 0 ? items.map((estimate) => (
                            <div key={estimate.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <input type="checkbox" className="mt-1 rounded border-border-warm" checked={selectedIds.includes(estimate.id)} onChange={() => toggleId(estimate.id)} />
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('estimates.show', estimate.id)} className="font-semibold text-ink hover:text-terracotta">{estimate.estimate_number}</Link>
                                        <p className="text-xs text-ink-muted mt-0.5">{estimate.customer?.name || '—'}</p>
                                        <p className="text-sm font-mono font-semibold text-ink mt-1">
                                            {formatCurrency(estimate.total_amount, estimate.currency || base_currency)}
                                        </p>
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${STATUS_STYLES[estimate.status] || 'bg-surface-alt text-ink-muted'}`}>
                                            {estimate.status}
                                        </span>
                                    </div>
                                    <Actions estimate={estimate} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">
                                {totalCount === 0 ? 'No estimates yet. Create your first quotation to get started.' : 'No estimates match your filters.'}
                            </div>
                        )}
                    </div>

                    {lastPage > 1 && (
                        <div className="px-4 sm:px-6 py-4 border-t border-border-warm flex flex-wrap items-center justify-between gap-3 bg-cream/30">
                            <p className="text-sm text-ink">Page {currentPage} of {lastPage}</p>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    disabled={currentPage <= 1}
                                    onClick={() => applyFilters({ page: Math.max(1, currentPage - 1) })}
                                    className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${currentPage <= 1 ? 'pointer-events-none text-ink-muted border-border-warm' : 'text-ink border-border-warm hover:bg-cream'}`}
                                >
                                    <Icons.ChevronLeft /> Previous
                                </button>
                                <button
                                    type="button"
                                    disabled={currentPage >= lastPage}
                                    onClick={() => applyFilters({ page: Math.min(lastPage, currentPage + 1) })}
                                    className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${currentPage >= lastPage ? 'pointer-events-none text-ink-muted border-border-warm' : 'text-ink border-border-warm hover:bg-cream'}`}
                                >
                                    Next <Icons.ChevronRight />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
