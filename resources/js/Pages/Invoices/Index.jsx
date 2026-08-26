import React, { useState } from 'react';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { confirm } from '@/utils/swal';
import {
    currencySymbol,
    currencyDecimals,
    currencyInputStep,
    formatCurrency,
    normalizeCurrency,
} from '@/utils/currency';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    Eye: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ReceiptRefund: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>,
    PaperAirplane: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12l14-7-7 14-2-5-5-2z" /></svg>,
    EllipsisVertical: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>,
    ChevronLeft: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
};

function getStatusBadge(status) {
    const styles = {
        paid: 'bg-forest/10 text-forest',
        unpaid: 'bg-terracotta/10 text-terracotta',
        'partially paid': 'bg-surface-alt text-terracotta',
        draft: 'bg-surface-alt text-ink',
        void: 'bg-surface-alt text-ink-muted',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

function formatInvoiceAmount(invoice) {
    return formatCurrency(invoice.total_amount, invoice.currency);
}

function formatInvoiceBalance(invoice) {
    const balance = parseFloat(invoice.balance_due ?? 0);
    return formatCurrency(balance, invoice.currency);
}

function invoiceStatusKey(status) {
    return String(status || '').replace(/\s+/g, '_').toLowerCase();
}

function invoiceStatusLabel(t, status) {
    const key = `invoices.status.${invoiceStatusKey(status)}`;
    const translated = t(key);
    return translated === key ? status : translated;
}

export default function Index({ auth, invoices = [], bankAccounts = [], totalOutstanding = 0, totalCollected = 0, totalCount = 0, paginator = {}, filters = {} }) {
    const { t } = useTranslation();
    const { current_page = 1, last_page = 1, per_page = 10, total = 0, from = 0, to = 0 } = paginator;
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 10 } = filters;

    const [searchInput, setSearchInput] = useState(search);
    const [selectedInvoice, setSelectedInvoice] = useState(null);
    const [emailingId, setEmailingId] = useState(null);
    const [selectedIds, setSelectedIds] = useState([]);
    const pageIds = invoices.map((i) => i.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const toggleId = (id) => setSelectedIds((cur) => cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    const toggleAll = () => setSelectedIds(allSelected ? selectedIds.filter((id) => !pageIds.includes(id)) : [...new Set([...selectedIds, ...pageIds])]);
    const canEmail = Boolean(auth.planPermissions?.['invoices.email']) && (auth.permissions || []).includes('invoices.email');
    const bulkEmail = async () => {
        const ok = await confirm({
            title: t('invoices.confirm.bulk_email_title', { count: selectedIds.length }),
            text: t('invoices.confirm.bulk_email_text'),
            confirmText: t('invoices.confirm.bulk_email_confirm'),
            icon: 'question',
        });
        if (ok) router.post(route('invoices.bulk-email'), { ids: selectedIds });
    };
    const bulkPdf = () => {
        const params = new URLSearchParams();
        selectedIds.forEach((id) => params.append('ids[]', id));
        window.open(`${route('invoices.bulk-pdf')}?${params.toString()}`, '_blank');
    };

    const defaultBankCode = (bankAccounts && bankAccounts[0]?.value) || '';

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: defaultBankCode,
    });

    const applyFilters = (overrides = {}) => {
        const params = { search: overrides.search ?? searchInput, status: overrides.status ?? statusFilter, per_page: overrides.per_page ?? perPageFilter, page: overrides.page ?? 1 };
        router.get(route('invoices.index'), params, { preserveState: false });
    };

    const handlePostToLedger = async (id) => {
        const ok = await confirm({
            title: t('invoices.confirm.post_title'),
            text: t('invoices.confirm.post_text'),
            confirmText: t('invoices.confirm.post_confirm'),
            icon: 'question',
        });
        if (ok) router.post(route('invoices.post', id));
    };

    const handleVoid = async (id) => {
        const ok = await confirm({
            title: t('invoices.confirm.void_title'),
            text: t('invoices.confirm.void_text'),
            confirmText: t('invoices.confirm.void_confirm'),
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('invoices.void', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({
            title: t('invoices.confirm.delete_title'),
            text: t('invoices.confirm.delete_text'),
            confirmText: t('invoices.confirm.delete_confirm'),
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('invoices.destroy', id));
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('invoices.record-payment', selectedInvoice.id), { onSuccess: () => { setSelectedInvoice(null); reset(); } });
    };

    const handleEmailInvoice = async (id) => {
        const ok = await confirm({
            title: t('invoices.confirm.email_title'),
            text: t('invoices.confirm.email_text'),
            confirmText: t('invoices.confirm.email_confirm'),
            icon: 'question',
        });
        if (ok) router.post(route('invoices.email', id), { onStart: () => setEmailingId(id), onFinish: () => setEmailingId(null) });
    };

    const InvoiceRow = ({ invoice }) => (
        <>
            <td className="px-3 py-3 sm:py-4">
                <input type="checkbox" checked={selectedIds.includes(invoice.id)} onChange={() => toggleId(invoice.id)} className="rounded border-border-warm" />
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4">
                <Link href={route('invoices.show', invoice.id)} className="block group/link">
                    <span className="font-semibold text-ink group-hover/link:text-terracotta">{invoice.invoice_number}</span>
                    <p className="text-xs text-ink-muted mt-0.5">{new Date(invoice.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}</p>
                </Link>
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4">
                <div className="font-medium text-ink">{invoice.customer_name || t('invoices.actions.walk_in')}</div>
                <p className="text-xs text-ink-muted truncate max-w-[140px] sm:max-w-none">{invoice.customer_email || t('invoices.actions.no_email')}</p>
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4">
                    <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(invoice.status)}`}>{invoiceStatusLabel(t, invoice.status)}</span>
                    {invoice.last_viewed_at && <p className="text-[10px] text-ink-muted mt-1">{t('invoices.actions.viewed')}</p>}
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                <div className="font-mono text-sm font-semibold text-ink">{formatInvoiceAmount(invoice)}</div>
                {parseFloat(invoice.amount_paid) > 0 && invoice.status !== 'paid' && (
                    <p className="text-xs text-terracotta tabular-nums">{t('invoices.actions.balance_short')} {formatInvoiceBalance(invoice)}</p>
                )}
            </td>
            <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                <ActionsCell t={t} auth={auth} invoice={invoice} setSelectedInvoice={setSelectedInvoice} setData={setData} defaultBankCode={defaultBankCode} handlePostToLedger={handlePostToLedger} handleVoid={handleVoid} handleDelete={handleDelete} handleEmailInvoice={handleEmailInvoice} emailingId={emailingId} />
            </td>
        </>
    );

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">{t('invoices.page_title')}</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">{t('invoices.subtitle')}</p>
                </div>
                {auth.permissions.includes('invoices.create') && (
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('invoices.cash-sale')} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                            {t('invoices.cash_sale')}
                        </Link>
                        <Link href={route('invoices.batch')} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                            {t('invoices.batch')}
                        </Link>
                        <Link href={route('invoices.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg  transition-all duration-200">
                            <Icons.Plus /> {t('invoices.create')}
                        </Link>
                    </div>
                )}
            </div>
        }>
            <Head title={t('invoices.title')} />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">{t('invoices.total_invoices')}</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{totalCount}</p>
                        <p className="text-xs text-terracotta mt-1">{t('invoices.total_invoices_hint')}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{t('invoices.outstanding_ar')}</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-terracotta font-mono tabular-nums">RM {totalOutstanding.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                        <p className="text-xs text-ink-muted mt-1">{t('invoices.outstanding_hint')}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{t('invoices.collected')}</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-forest font-mono tabular-nums">RM {totalCollected.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                        <p className="text-xs text-ink-muted mt-1">{t('invoices.collected_hint')}</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    {selectedIds.length > 0 && (
                        <div className="px-4 sm:px-6 py-3 border-b border-border-warm bg-cream flex flex-wrap items-center gap-2">
                            <span className="text-sm font-semibold text-ink">{t('invoices.bulk.selected', { count: selectedIds.length })}</span>
                            <button type="button" onClick={bulkPdf} className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm bg-surface hover:bg-cream">{t('invoices.bulk.download_pdfs')}</button>
                            {canEmail && (
                                <button type="button" onClick={bulkEmail} className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm bg-surface hover:bg-cream">{t('invoices.bulk.email_selected')}</button>
                            )}
                            <button type="button" onClick={() => setSelectedIds([])} className="text-xs text-ink-muted">{t('invoices.bulk.clear')}</button>
                        </div>
                    )}
                    <form onSubmit={(e) => { e.preventDefault(); applyFilters({ page: 1 }); }} className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-0 max-w-full sm:max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder={t('invoices.search_placeholder')} value={searchInput} onChange={(e) => setSearchInput(e.target.value)} onBlur={() => applyFilters({ page: 1 })} className="pl-9 w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <select value={statusFilter} onChange={(e) => applyFilters({ status: e.target.value, page: 1 })} className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]">
                            <option value="">{t('invoices.all_statuses')}</option>
                            <option value="draft">{t('invoices.status.draft')}</option>
                            <option value="unpaid">{t('invoices.status.unpaid')}</option>
                            <option value="partially paid">{t('invoices.status.partially_paid')}</option>
                            <option value="paid">{t('invoices.status.paid')}</option>
                            <option value="void">{t('invoices.status.void')}</option>
                        </select>
                        <select value={perPageFilter} onChange={(e) => applyFilters({ per_page: Number(e.target.value), page: 1 })} className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]">
                            <option value={10}>{t('invoices.filters.per_page', { count: 10 })}</option>
                            <option value={25}>{t('invoices.filters.per_page', { count: 25 })}</option>
                            <option value={50}>{t('invoices.filters.per_page', { count: 50 })}</option>
                            <option value={100}>{t('invoices.filters.per_page', { count: 100 })}</option>
                        </select>
                        <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta">{t('invoices.filters.apply')}</button>
                        <span className="text-ink-muted text-sm font-medium ml-auto whitespace-nowrap">
                            {total > 0 ? t('invoices.pagination.range', { from, to, total }) : t('invoices.pagination.empty')}
                        </span>
                    </form>

                    {/* Desktop table */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-3 py-3 w-10"><input type="checkbox" checked={allSelected} onChange={toggleAll} className="rounded border-border-warm" /></th>
                                    <th className="px-4 sm:px-6 py-3">{t('invoices.table_invoice')}</th>
                                    <th className="px-4 sm:px-6 py-3">{t('invoices.table_customer')}</th>
                                    <th className="px-4 sm:px-6 py-3">{t('invoices.table_status')}</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">{t('invoices.table_amount')}</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">{t('invoices.table_actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.length > 0 ? invoices.map((invoice) => (
                                    <tr key={invoice.id} className={`border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors ${invoice.status === 'void' ? 'opacity-60' : ''}`}>
                                        <InvoiceRow invoice={invoice} />
                                    </tr>
                                )) : (
                                    <tr><td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">{totalCount === 0 ? t('invoices.empty_none') : t('invoices.empty_filtered')}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile cards */}
                    <div className="md:hidden divide-y divide-border-warm">
                        {invoices.length > 0 ? invoices.map((invoice) => (
                            <div key={invoice.id} className={`p-4 ${invoice.status === 'void' ? 'opacity-60' : ''}`}>
                                <div className="flex items-start justify-between gap-3">
                                    <input type="checkbox" className="mt-1 rounded border-border-warm" checked={selectedIds.includes(invoice.id)} onChange={() => toggleId(invoice.id)} />
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('invoices.show', invoice.id)} className="font-semibold text-ink hover:text-terracotta">{invoice.invoice_number}</Link>
                                        <p className="text-xs text-ink-muted mt-0.5">{invoice.customer_name || 'Walk-in'}</p>
                                        <p className="text-sm font-mono font-semibold text-ink mt-1">{formatInvoiceAmount(invoice)}</p>
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(invoice.status)}`}>{invoiceStatusLabel(t, invoice.status)}</span>
                                    </div>
                                    <ActionsCell t={t} auth={auth} invoice={invoice} setSelectedInvoice={setSelectedInvoice} setData={setData} defaultBankCode={defaultBankCode} handlePostToLedger={handlePostToLedger} handleVoid={handleVoid} handleDelete={handleDelete} handleEmailInvoice={handleEmailInvoice} emailingId={emailingId} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">{totalCount === 0 ? t('invoices.empty_none') : t('invoices.empty_filtered')}</div>
                        )}
                    </div>

                    {/* Pagination */}
                    {last_page > 1 && (
                        <div className="px-4 sm:px-6 py-4 border-t border-border-warm flex flex-wrap items-center justify-between gap-3 bg-cream/30">
                            <p className="text-sm text-ink">{t('invoices.pagination.page_of', { current: current_page, last: last_page })}</p>
                            <div className="flex items-center gap-2">
                                <Link href={route('invoices.index', { search: searchInput || undefined, status: statusFilter || undefined, per_page: perPageFilter, page: Math.max(1, current_page - 1) })} className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${current_page <= 1 ? 'pointer-events-none text-ink-muted border-border-warm' : 'text-ink border-border-warm hover:bg-cream'}`}>
                                    <Icons.ChevronLeft /> {t('invoices.pagination.previous')}
                                </Link>
                                <Link href={route('invoices.index', { search: searchInput || undefined, status: statusFilter || undefined, per_page: perPageFilter, page: Math.min(last_page, current_page + 1) })} className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${current_page >= last_page ? 'pointer-events-none text-ink-muted border-border-warm' : 'text-ink border-border-warm hover:bg-cream'}`}>
                                    {t('invoices.pagination.next')} <Icons.ChevronRight />
                                </Link>
                            </div>
                        </div>
                    )}
                </div>

                {selectedInvoice && (
                    <div className="fixed inset-0 bg-ink/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-surface rounded-2xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-border-warm">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-forest/10 text-forest"><Icons.Currency /></span>
                                <div>
                                    <h3 className="text-xl font-display font-medium text-ink">{t('invoices.payment_modal.title')}</h3>
                                    <p className="text-sm text-ink-muted">{t('invoices.payment_modal.subtitle', { number: selectedInvoice.invoice_number })}</p>
                                </div>
                            </div>
                            <form onSubmit={handlePaymentSubmit} className="space-y-5">
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">{t('invoices.payment_modal.amount')} ({normalizeCurrency(selectedInvoice.currency)})</label>
                                    <div className="relative">
                                        <span className="absolute inset-y-0 left-4 flex items-center text-ink-muted font-medium">{currencySymbol(selectedInvoice.currency)}</span>
                                        <input type="number" value={data.amount} onChange={e => setData('amount', e.target.value)} className={`w-full pl-12 pr-4 py-3 border rounded-xl font-semibold text-ink focus:ring-2 focus:ring-terracotta ${errors.amount ? 'border-terracotta ring-1 ring-terracotta' : 'border-border-warm'}`} step={currencyInputStep(selectedInvoice.currency)} required />
                                    </div>
                                    {errors.amount && <p className="text-terracotta text-[10px] mt-1.5 font-bold uppercase tracking-tight">{errors.amount}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">{t('invoices.payment_modal.date')}</label>
                                        <input type="date" value={data.payment_date} onChange={e => setData('payment_date', e.target.value)} className={`w-full border rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-terracotta ${errors.payment_date ? 'border-terracotta' : 'border-border-warm'}`} required />
                                        {errors.payment_date && <p className="text-terracotta text-[10px] mt-1.5 font-bold uppercase tracking-tight">{errors.payment_date}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">{t('invoices.payment_modal.account')}</label>
                                        <select value={data.bank_account_code} onChange={e => setData('bank_account_code', e.target.value)} className={`w-full border rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-terracotta ${errors.bank_account_code ? 'border-terracotta' : 'border-border-warm'}`}>
                                            {(bankAccounts || []).length === 0 && (
                                                <option value="">{t('invoices.payment_modal.no_bank_accounts')}</option>
                                            )}
                                            {(bankAccounts || []).map((a) => (
                                                <option key={a.value} value={a.value}>{a.label}</option>
                                            ))}
                                        </select>
                                        {errors.bank_account_code && <p className="text-terracotta text-[10px] mt-1.5 font-bold uppercase tracking-tight">{errors.bank_account_code}</p>}
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={() => { setSelectedInvoice(null); reset(); }} className="flex-1 py-3 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream">{t('invoices.payment_modal.cancel')}</button>
                                    <button type="submit" disabled={processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50"> {processing ? t('invoices.payment_modal.processing') : t('invoices.payment_modal.confirm')}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ActionsCell({ t, auth, invoice, setSelectedInvoice, setData, defaultBankCode, handlePostToLedger, handleVoid, handleDelete, handleEmailInvoice, emailingId }) {
    const isDraft = invoice.status === 'draft';
    const isVoid = invoice.status === 'void';

    return (
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
                    <Link href={route('invoices.show', invoice.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                        <Icons.ChevronRight className="w-4 h-4" /> {t('invoices.actions.open')}
                    </Link>
                </MenuItem>
                {auth.permissions.includes('invoices.create') && (
                    <MenuItem>
                        <button type="button" onClick={() => router.post(route('invoices.duplicate', invoice.id))} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                            {t('invoices.actions.duplicate')}
                        </button>
                    </MenuItem>
                )}
                <MenuItem>
                    <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                        <Icons.Eye /> {t('invoices.actions.preview_pdf')}
                    </a>
                </MenuItem>
                <MenuItem>
                    <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                        <Icons.ArrowDownTray /> {t('invoices.actions.download_pdf')}
                    </a>
                </MenuItem>
                {isDraft && (
                    <>
                        {auth.permissions.includes('invoices.post') && (
                            <MenuItem>
                                <button type="button" onClick={() => handlePostToLedger(invoice.id)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-terracotta hover:bg-surface-alt">
                                    <Icons.Check /> {t('invoices.actions.post_to_ledger')}
                                </button>
                            </MenuItem>
                        )}
                        {auth.permissions.includes('invoices.edit') && (
                            <MenuItem>
                                <Link href={route('invoices.edit', invoice.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                                    <Icons.Pencil /> {t('invoices.actions.edit')}
                                </Link>
                            </MenuItem>
                        )}
                        {auth.permissions.includes('invoices.delete') && (
                            <MenuItem>
                                <button type="button" onClick={() => handleDelete(invoice.id)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-terracotta hover:bg-terracotta/10">
                                    {t('invoices.actions.delete_draft')}
                                </button>
                            </MenuItem>
                        )}
                    </>
                )}
                {!isDraft && !isVoid && (
                    <>
                        {invoice.status !== 'paid' && auth.planPermissions['invoices.record-payment'] && auth.permissions.includes('invoices.record-payment') && (
                            <MenuItem>
                                <button type="button" onClick={() => { setSelectedInvoice(invoice); setData('amount', (parseFloat(invoice.balance_due ?? 0)).toFixed(currencyDecimals(invoice.currency))); setData('bank_account_code', defaultBankCode); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-forest hover:bg-forest/10">
                                    <Icons.Currency /> {t('invoices.actions.record_payment')}
                                </button>
                            </MenuItem>
                        )}
                        {auth.planPermissions['credit-notes.view'] && auth.permissions.includes('credit-notes.create') && (
                            <MenuItem>
                                <Link href={route('credit-notes.create', invoice.id)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream">
                                    <Icons.ReceiptRefund /> {t('invoices.actions.credit_note')}
                                </Link>
                            </MenuItem>
                        )}
                        {invoice.customer_email && auth.planPermissions['invoices.email'] && auth.permissions.includes('invoices.email') && (
                            <MenuItem>
                                <button type="button" onClick={() => handleEmailInvoice(invoice.id)} disabled={emailingId === invoice.id} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-cream disabled:opacity-50">
                                    <Icons.PaperAirplane /> {emailingId === invoice.id ? t('invoices.actions.emailing') : t('invoices.actions.email')}
                                </button>
                            </MenuItem>
                        )}
                        {auth.permissions.includes('invoices.void') && (
                            <MenuItem>
                                <button type="button" onClick={() => handleVoid(invoice.id)} className="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-terracotta hover:bg-terracotta/10">
                                    {t('invoices.actions.void_invoice')}
                                </button>
                            </MenuItem>
                        )}
                    </>
                )}
            </MenuItems>
        </Menu>
    );
}
