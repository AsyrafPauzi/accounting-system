import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatDate } from '@/utils/dates';

const Icons = {
    Repeat: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Lightning: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>,
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

export default function Index({ auth, templates = [] }) {
    const [search, setSearch] = useState('');
    const filteredTemplates = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (!query) return templates;
        return templates.filter((template) => (
            (template.name || '').toLowerCase().includes(query)
            || (template.supplier?.name || '').toLowerCase().includes(query)
        ));
    }, [search, templates]);

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div className="flex items-center gap-3">
                    <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                        <Icons.Repeat />
                    </span>
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Recurring Bills</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Templates that create draft supplier bills on a schedule.</p>
                    </div>
                </div>
                {auth.permissions.includes('bills.create') && (
                    <Link
                        href={route('recurring-bills.create')}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark shadow-lg transition-all duration-200"
                    >
                        <Icons.Plus /> New recurring bill
                    </Link>
                )}
            </div>
        }>
            <Head title="Recurring Bills" />
            <div className="space-y-6">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="p-4 sm:p-6 border-b border-border-warm">
                        <div className="max-w-md relative">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search by template name or supplier"
                                className="w-full pl-10 pr-4 py-2.5 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Template</th>
                                    <th className="px-6 py-3">Supplier</th>
                                    <th className="px-6 py-3">Cadence</th>
                                    <th className="px-6 py-3">Next run</th>
                                    <th className="px-6 py-3 text-center">Status</th>
                                    <th className="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {filteredTemplates.length > 0 ? filteredTemplates.map((template) => (
                                    <tr key={template.id} className="hover:bg-cream/40 transition-colors">
                                        <td className="px-6 py-4">
                                            <Link className="font-semibold text-ink hover:text-terracotta" href={route('recurring-bills.show', template.id)}>
                                                {template.name || `Template #${template.id}`}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink">{template.supplier?.name || <span className="text-ink-muted">—</span>}</td>
                                        <td className="px-6 py-4 text-sm text-ink-muted">{cadenceLabel(template)}</td>
                                        <td className="px-6 py-4 text-sm text-ink-muted">{template.is_active ? formatDate(template.next_run_date) : '—'}</td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${template.is_active ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                                                {template.is_active ? 'Active' : 'Paused'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-violet-600 hover:bg-violet-100 rounded-lg"
                                                onClick={() => router.post(route('recurring-bills.run', template.id))}
                                            >
                                                <Icons.Lightning /> Run now
                                            </button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3 text-ink-muted">
                                                <span className="p-4 bg-surface-alt rounded-xl text-terracotta">
                                                    <Icons.Repeat />
                                                </span>
                                                <p className="font-semibold text-ink">{templates.length === 0 ? 'No recurring bills yet.' : 'No recurring bills match your search.'}</p>
                                            </div>
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
