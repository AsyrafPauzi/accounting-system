import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';

const Icons = {
    Repeat: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
    Pause: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Play: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Lightning: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
};

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

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const isDueToday = (d) => d && new Date(d) <= new Date(new Date().toDateString());

export default function Index({ auth, templates, filters = {}, counts = {}, base_currency = 'MYR' }) {
    const items = templates?.data || [];
    const [search, setSearch] = useState(filters.search || '');

    const apply = (next = {}) => {
        router.get(route('recurring-invoices.index'), {
            search: next.search ?? search,
            status: next.status ?? filters.status ?? 'all',
        }, { preserveState: true, replace: true });
    };

    const handleDelete = async (template) => {
        const ok = await confirm({
            title: 'Delete this recurring invoice?',
            text: `Stop the schedule for "${template.name || `template #${template.id}`}". Invoices already generated stay intact.`,
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('recurring-invoices.destroy', template.id));
    };

    const handleToggle = (template) => router.post(route('recurring-invoices.toggle', template.id), {}, { preserveScroll: true });

    const handleRunNow = async (template) => {
        const ok = await confirm({
            title: 'Generate a draft invoice now?',
            text: `A draft invoice will be created for ${template.customer?.name || 'this customer'}. The schedule will advance to the next cycle.`,
            confirmText: 'Yes, generate',
            confirmColor: '#7c3aed',
            icon: 'question',
        });
        if (ok) router.post(route('recurring-invoices.run', template.id));
    };

    const tabs = [
        { value: 'all',    label: 'All',    count: counts.all || 0 },
        { value: 'active', label: 'Active', count: counts.active || 0 },
        { value: 'paused', label: 'Paused', count: counts.paused || 0 },
        { value: 'due',    label: 'Due now',count: counts.due || 0 },
    ];

    const activeStatus = filters.status || 'all';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Repeat />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Recurring Invoices</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">Templates that auto-generate draft invoices on a schedule. You review and post each one manually.</p>
                        </div>
                    </div>
                    {auth.permissions.includes('recurring-invoices.create') && (
                        <Link
                            href={route('recurring-invoices.create')}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200"
                        >
                            <Icons.Plus /> New recurring invoice
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Recurring Invoices" />

            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="p-4 sm:p-6 flex flex-col gap-3 border-b border-border-warm">
                        <div className="flex-1 max-w-md relative">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && apply({ search })}
                                onBlur={() => apply({ search })}
                                placeholder="Search by template name or customer"
                                className="w-full pl-10 pr-4 py-2.5 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                        <div className="flex items-center gap-2 flex-wrap">
                            {tabs.map(tab => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() => apply({ status: tab.value })}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-2 ${activeStatus === tab.value
                                        ? 'bg-terracotta text-white'
                                        : 'bg-surface-alt text-ink hover:bg-cream'
                                    }`}
                                >
                                    {tab.label}
                                    {tab.count > 0 && (
                                        <span className={`px-1.5 py-0.5 rounded text-[10px] ${activeStatus === tab.value ? 'bg-white/20 text-white' : 'bg-cream text-ink-muted'}`}>
                                            {tab.count}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Template</th>
                                    <th className="px-6 py-3">Customer</th>
                                    <th className="px-6 py-3">Cadence</th>
                                    <th className="px-6 py-3">Next run</th>
                                    <th className="px-6 py-3 text-center">Generated</th>
                                    <th className="px-6 py-3 text-center">Status</th>
                                    <th className="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {items.length > 0 ? items.map((t) => (
                                    <tr key={t.id} className="hover:bg-cream/40 transition-colors">
                                        <td className="px-6 py-4">
                                            <p className="font-semibold text-ink">{t.name || <span className="italic text-ink-muted">Untitled template</span>}</p>
                                            {t.last_generated_invoice && (
                                                <p className="text-[10px] text-ink-muted mt-0.5">Last: {t.last_generated_invoice.invoice_number} ({t.last_generated_invoice.status})</p>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink">
                                            {t.customer?.name || <span className="text-ink-muted">—</span>}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink-muted">
                                            {cadenceLabel(t)}
                                        </td>
                                        <td className="px-6 py-4 text-sm">
                                            {t.is_active ? (
                                                <span className={isDueToday(t.next_run_date) ? 'text-terracotta font-semibold' : 'text-ink-muted'}>
                                                    {fmtDate(t.next_run_date)}
                                                    {isDueToday(t.next_run_date) && <span className="ml-1 text-[10px] uppercase tracking-wider">· due</span>}
                                                </span>
                                            ) : (
                                                <span className="text-ink-muted/60 italic">Paused</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-center font-mono text-ink">{t.generated_count || 0}</td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${t.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                                {t.is_active ? 'Active' : 'Paused'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="inline-flex items-center gap-1">
                                                {auth.permissions.includes('recurring-invoices.run') && t.is_active && (
                                                    <button type="button" onClick={() => handleRunNow(t)} className="p-2 text-violet-600 hover:bg-violet-100 rounded-lg" title="Generate draft invoice now">
                                                        <Icons.Lightning />
                                                    </button>
                                                )}
                                                {auth.permissions.includes('recurring-invoices.edit') && (
                                                    <button type="button" onClick={() => handleToggle(t)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg" title={t.is_active ? 'Pause' : 'Resume'}>
                                                        {t.is_active ? <Icons.Pause /> : <Icons.Play />}
                                                    </button>
                                                )}
                                                {auth.permissions.includes('recurring-invoices.edit') && (
                                                    <Link href={route('recurring-invoices.edit', t.id)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg" title="Edit">
                                                        <Icons.Pencil />
                                                    </Link>
                                                )}
                                                {auth.permissions.includes('recurring-invoices.delete') && (
                                                    <button type="button" onClick={() => handleDelete(t)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-terracotta/10 rounded-lg" title="Delete">
                                                        <Icons.Trash />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3 text-ink-muted">
                                                <span className="p-4 bg-surface-alt rounded-xl text-terracotta">
                                                    <Icons.Repeat />
                                                </span>
                                                <div>
                                                    <p className="font-semibold text-ink">No recurring templates yet</p>
                                                    <p className="text-sm mt-1">Set up a monthly retainer or quarterly subscription invoice once — the system creates a fresh draft on every cycle.</p>
                                                </div>
                                                {auth.permissions.includes('recurring-invoices.create') && (
                                                    <Link href={route('recurring-invoices.create')} className="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark text-sm">
                                                        <Icons.Plus /> Create recurring invoice
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {templates?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between text-xs text-ink-muted">
                            <span>Showing {templates.from || 0}–{templates.to || 0} of {templates.total}</span>
                            <div className="flex items-center gap-2">
                                {templates.links?.filter(l => l.url).map((link, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        onClick={() => router.visit(link.url, { preserveState: true })}
                                        disabled={link.active}
                                        className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold ${link.active ? 'bg-terracotta text-white' : 'bg-surface-alt text-ink hover:bg-cream'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
