import React, { useState } from 'react';
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
};

const STATUS_STYLES = {
    draft:     'bg-surface-alt text-ink-muted',
    sent:      'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    accepted:  'bg-forest/10 text-forest',
    rejected:  'bg-terracotta/10 text-terracotta',
    expired:   'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    converted: 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
};

export default function Index({ auth, estimates, filters = {}, counts = {}, base_currency = 'MYR' }) {
    const items = estimates?.data || [];
    const [search, setSearch] = useState(filters.search || '');

    const apply = (next = {}) => {
        router.get(route('estimates.index'), {
            search: next.search ?? search,
            status: next.status ?? filters.status ?? 'all',
        }, { preserveState: true, replace: true });
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

    // Email gating mirrors the invoice list — both the Spatie permission
    // (`estimates.email`) and the plan flag must be true. The bullet
    // says "Solo+", so customers on Startup never see the button.
    const planPermissions = auth?.planPermissions ?? {};
    const canEmail = (auth.permissions || []).includes('estimates.email')
        && Boolean(planPermissions['estimates.email']);

    const [emailingId, setEmailingId] = useState(null);
    const [selectedIds, setSelectedIds] = useState([]);
    const pageIds = items.map((e) => e.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const toggleId = (id) => setSelectedIds((cur) => cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    const toggleAll = () => setSelectedIds(allSelected ? selectedIds.filter((id) => !pageIds.includes(id)) : [...new Set([...selectedIds, ...pageIds])]);
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
            text: `Send ${estimate.estimate_number} to ${estimate.customer_email}? They\u2019ll get a PDF download link valid for 30 days.`,
            confirmText: 'Send email',
            confirmColor: '#0f172a',
            icon: 'info',
        });
        if (!ok) return;
        router.post(route('estimates.email', estimate.id), {}, {
            preserveScroll: true,
            onStart:  () => setEmailingId(estimate.id),
            onFinish: () => setEmailingId(null),
        });
    };

    const tabs = [
        { value: 'all',       label: 'All',       count: items.length === 0 ? 0 : (estimates?.total || 0) },
        { value: 'draft',     label: 'Draft',     count: counts.draft || 0 },
        { value: 'sent',      label: 'Sent',      count: counts.sent || 0 },
        { value: 'accepted',  label: 'Accepted',  count: counts.accepted || 0 },
        { value: 'rejected',  label: 'Rejected',  count: counts.rejected || 0 },
        { value: 'expired',   label: 'Expired',   count: counts.expired || 0 },
        { value: 'converted', label: 'Converted', count: counts.converted || 0 },
    ];

    const activeStatus = filters.status || 'all';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Quote />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Estimates</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">Send quotations to customers and convert accepted ones into invoices</p>
                        </div>
                    </div>
                    {auth.permissions.includes('estimates.create') && (
                        <div className="flex flex-wrap gap-2">
                            <Link
                                href={route('estimates.batch')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                            >
                                Batch
                            </Link>
                            <Link
                                href={route('estimates.create')}
                                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200"
                            >
                                <Icons.Plus /> New estimate
                            </Link>
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Estimates" />

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
                                placeholder="Search by estimate number or customer"
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

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-3 py-3 w-10"><input type="checkbox" checked={allSelected} onChange={toggleAll} className="rounded border-border-warm" /></th>
                                    <th className="px-6 py-3">Estimate #</th>
                                    <th className="px-6 py-3">Customer</th>
                                    <th className="px-6 py-3">Issued</th>
                                    <th className="px-6 py-3">Expiry</th>
                                    <th className="px-6 py-3 text-center">Status</th>
                                    <th className="px-6 py-3 text-right">Total</th>
                                    <th className="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {items.length > 0 ? items.map((e) => (
                                    <tr key={e.id} className="hover:bg-cream/40 transition-colors">
                                        <td className="px-3 py-4">
                                            <input type="checkbox" checked={selectedIds.includes(e.id)} onChange={() => toggleId(e.id)} className="rounded border-border-warm" />
                                        </td>
                                        <td className="px-6 py-4">
                                            <Link href={route('estimates.show', e.id)} className="font-semibold text-ink hover:text-terracotta">
                                                {e.estimate_number}
                                            </Link>
                                            {e.converted_invoice_id && (
                                                <p className="text-[10px] text-ink-muted mt-0.5">→ Invoice #{e.converted_invoice_id}</p>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink">
                                            {e.customer?.name || <span className="text-ink-muted">—</span>}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink-muted">
                                            {e.issue_date ? new Date(e.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-ink-muted">
                                            {e.expiry_date ? new Date(e.expiry_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${STATUS_STYLES[e.status] || 'bg-surface-alt text-ink-muted'}`}>
                                                {e.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono tabular-nums text-ink">
                                            {formatCurrency(e.total_amount, e.currency || base_currency)}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="inline-flex items-center gap-1">
                                                <Link href={route('estimates.show', e.id)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg" title="View">
                                                    <Icons.Eye />
                                                </Link>
                                                <a
                                                    href={route('estimates.pdf', e.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg"
                                                    title="Download PDF"
                                                >
                                                    <Icons.Pdf />
                                                </a>
                                                {canEmail && e.customer_email && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleEmail(e)}
                                                        disabled={emailingId === e.id}
                                                        className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg disabled:opacity-50 disabled:cursor-wait"
                                                        title={`Email to ${e.customer_email}`}
                                                    >
                                                        <Icons.Mail />
                                                    </button>
                                                )}
                                                {auth.permissions.includes('estimates.create') && (
                                                    <button type="button" onClick={() => router.post(route('estimates.duplicate', e.id))} className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg text-xs font-semibold" title="Duplicate">
                                                        Copy
                                                    </button>
                                                )}
                                                {auth.permissions.includes('estimates.edit') && e.status !== 'converted' && (
                                                    <Link href={route('estimates.edit', e.id)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-cream rounded-lg" title="Edit">
                                                        <Icons.Pencil />
                                                    </Link>
                                                )}
                                                {auth.permissions.includes('estimates.delete') && e.status !== 'converted' && (
                                                    <button type="button" onClick={() => handleDelete(e)} className="p-2 text-ink-muted hover:text-terracotta hover:bg-terracotta/10 rounded-lg" title="Delete">
                                                        <Icons.Trash />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="8" className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3 text-ink-muted">
                                                <span className="p-4 bg-surface-alt rounded-xl text-terracotta">
                                                    <Icons.Quote />
                                                </span>
                                                <div>
                                                    <p className="font-semibold text-ink">No estimates yet</p>
                                                    <p className="text-sm mt-1">Create your first quotation. When the customer accepts, click "Convert to Invoice".</p>
                                                </div>
                                                {auth.permissions.includes('estimates.create') && (
                                                    <Link href={route('estimates.create')} className="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark text-sm">
                                                        <Icons.Plus /> Create estimate
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {estimates?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between text-xs text-ink-muted">
                            <span>Showing {estimates.from || 0}–{estimates.to || 0} of {estimates.total}</span>
                            <div className="flex items-center gap-2">
                                {estimates.links?.filter(l => l.url).map((link, idx) => (
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
