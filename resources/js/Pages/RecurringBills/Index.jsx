import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Repeat: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

const STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'due', label: 'Due now' },
];

const cadenceLabel = (template) => {
    const interval = Math.max(1, parseInt(template.interval || 1, 10));
    const unit = ({
        weekly: interval === 1 ? 'week' : 'weeks',
        monthly: interval === 1 ? 'month' : 'months',
        quarterly: interval === 1 ? 'quarter' : 'quarters',
        yearly: interval === 1 ? 'year' : 'years',
    })[template.cadence] || 'cycles';
    return interval === 1 ? `Every ${unit}` : `Every ${interval} ${unit}`;
};

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');

const isDueToday = (d) => d && new Date(d) <= new Date(new Date().toDateString());

export default function Index({ auth, templates, filters = {}, counts = {} }) {
    const items = templates?.data || [];
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 10 } = filters;
    const [searchInput, setSearchInput] = useState(search);
    const permissions = auth.permissions || [];

    const applyFilters = (overrides = {}) => {
        router.get(route('recurring-bills.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const handleRunNow = async (template) => {
        const ok = await confirm({
            title: 'Generate a draft bill now?',
            text: `A draft bill will be created for ${template.supplier?.name || 'this supplier'}. The schedule will advance to the next cycle.`,
            confirmText: 'Yes, generate',
            icon: 'question',
        });
        if (ok) router.post(route('recurring-bills.run', template.id));
    };

    const handleToggle = (template) => router.post(route('recurring-bills.toggle', template.id), {}, { preserveScroll: true });

    const currentPage = templates?.current_page || 1;
    const lastPage = templates?.last_page || 1;
    const from = templates?.from || 0;
    const to = templates?.to || 0;
    const total = templates?.total || 0;
    const totalCount = counts.all || 0;

    const Actions = ({ template }) => (
        <RowActionsMenu items={[
            { label: 'Open', href: route('recurring-bills.show', template.id), icon: <ActionIcons.Open /> },
            { label: 'Generate now', icon: <ActionIcons.Lightning />, show: template.is_active, onClick: () => handleRunNow(template) },
            { label: template.is_active ? 'Pause' : 'Resume', show: permissions.includes('bills.create'), onClick: () => handleToggle(template) },
        ]} />
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Recurring Bills</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Templates that generate draft supplier bills on a schedule</p>
                    </div>
                    {permissions.includes('bills.create') && (
                        <Link href={route('recurring-bills.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200">
                            <Icons.Plus /> New recurring bill
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Recurring Bills" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Templates</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Repeat /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{counts.all || 0}</p>
                        <p className="text-xs text-white/80 mt-1">Active · Paused</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Active</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-forest font-mono tabular-nums">{counts.active || 0}</p>
                        <p className="text-xs text-ink-muted mt-1">Running on schedule</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Due now</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-terracotta font-mono tabular-nums">{counts.due || 0}</p>
                        <p className="text-xs text-ink-muted mt-1">Ready to generate</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search by template or supplier..."
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
                                    <th className="px-4 sm:px-6 py-3">Template</th>
                                    <th className="px-4 sm:px-6 py-3">Supplier</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3">Next run</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Generated</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length > 0 ? items.map((t) => (
                                    <tr key={t.id} className={`border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors ${!t.is_active ? 'opacity-60' : ''}`}>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <Link href={route('recurring-bills.show', t.id)} className="block group/link">
                                                <span className="font-semibold text-ink group-hover/link:text-terracotta">{t.name || `Template #${t.id}`}</span>
                                                <p className="text-xs text-ink-muted mt-0.5">{cadenceLabel(t)}</p>
                                            </Link>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <div className="font-medium text-ink">{t.supplier?.name || '—'}</div>
                                            <p className="text-xs text-ink-muted truncate max-w-[140px] sm:max-w-none">{t.supplier?.email || 'No email'}</p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${t.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                                {t.is_active ? 'Active' : 'Paused'}
                                            </span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            {t.is_active ? (
                                                <span className={isDueToday(t.next_run_date) ? 'text-terracotta font-semibold text-sm' : 'text-ink text-sm'}>
                                                    {fmtDate(t.next_run_date)}
                                                    {isDueToday(t.next_run_date) && <span className="ml-1 text-[10px] uppercase tracking-wider">· due</span>}
                                                </span>
                                            ) : (
                                                <span className="text-ink-muted text-sm">Paused</span>
                                            )}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <div className="font-mono text-sm font-semibold text-ink">{t.generated_count || 0}</div>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <Actions template={t} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {totalCount === 0 ? 'No recurring bills yet. Create your first template to get started.' : 'No templates match your filters.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {items.length > 0 ? items.map((t) => (
                            <div key={t.id} className={`p-4 ${!t.is_active ? 'opacity-60' : ''}`}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('recurring-bills.show', t.id)} className="font-semibold text-ink hover:text-terracotta">{t.name || `Template #${t.id}`}</Link>
                                        <p className="text-xs text-ink-muted mt-0.5">{t.supplier?.name || '—'}</p>
                                        <p className="text-sm font-medium text-ink mt-1">{t.is_active ? fmtDate(t.next_run_date) : 'Paused'} · {cadenceLabel(t)}</p>
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${t.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                            {t.is_active ? 'Active' : 'Paused'}
                                        </span>
                                    </div>
                                    <Actions template={t} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">
                                {totalCount === 0 ? 'No recurring bills yet. Create your first template to get started.' : 'No templates match your filters.'}
                            </div>
                        )}
                    </div>

                    <IndexPagination currentPage={currentPage} lastPage={lastPage} onPage={(page) => applyFilters({ page })} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
