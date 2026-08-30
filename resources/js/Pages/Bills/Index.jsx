import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { confirm } from '@/utils/swal';
import Modal from '@/Components/Modal';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';
import useClientIndexFilters from '@/hooks/useClientIndexFilters';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Exclamation: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Check: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Pencil: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    FileText: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

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

const SEARCH_KEYS = ['bill_number', 'supplier_name'];

function billStatusKey(status) {
    return String(status || '').replace(/\s+/g, '_').toLowerCase();
}

function billStatusLabel(t, status) {
    const key = `bills.status.${billStatusKey(status)}`;
    const translated = t(key);
    return translated === key ? status : translated;
}

export default function Index({ auth, bills = [], suppliers = [], bankAccounts = [], totalOutstanding = 0, totalPaidPeriod = 0 }) {
    const { t } = useTranslation();
    const billStatuses = useMemo(() => ([
        { value: 'draft', label: t('bills.status.draft') },
        { value: 'unpaid', label: t('bills.status.unpaid') },
        { value: 'partially paid', label: t('bills.status.partially_paid') },
        { value: 'paid', label: t('bills.status.paid') },
        { value: 'void', label: t('bills.status.void') },
    ]), [t]);
    const [supplierFilter, setSupplierFilter] = useState('');
    const [selectedBillForReceipt, setSelectedBillForReceipt] = useState(null);
    const [selectedBill, setSelectedBill] = useState(null);
    const supplierFiltered = useMemo(
        () => (supplierFilter ? bills.filter((bill) => String(bill.supplier_id) === supplierFilter) : bills),
        [bills, supplierFilter]
    );
    const filters = useClientIndexFilters(supplierFiltered, { searchKeys: SEARCH_KEYS });
    const permissions = auth.permissions || [];

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (bankAccounts && bankAccounts[0]?.value) || '',
    });

    const filteredBills = filters.items;

    const handlePost = async (id) => {
        const ok = await confirm({
            title: t('bills.confirm.post_title'),
            text: t('bills.confirm.post_text'),
            confirmText: t('bills.confirm.post_confirm'),
            icon: 'question',
        });
        if (ok) router.post(route('bills.post', id));
    };

    const handleVoid = async (id) => {
        const ok = await confirm({
            title: t('bills.confirm.void_title'),
            text: t('bills.confirm.void_text'),
            confirmText: t('bills.confirm.void_confirm'),
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('bills.void', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({
            title: t('bills.confirm.delete_title'),
            text: t('bills.confirm.delete_text'),
            confirmText: t('bills.confirm.delete_confirm'),
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.delete(route('bills.destroy', id));
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('bills.record-payment', selectedBill.id), {
            onSuccess: () => {
                setSelectedBill(null);
                reset('amount', 0);
                reset('payment_date', new Date().toISOString().split('T')[0]);
            },
        });
    };

    const openPaymentModal = (bill) => {
        const balance = parseFloat(bill.balance_due ?? 0);
        setSelectedBill(bill);
        setData('amount', balance > 0 ? balance.toFixed(2) : 0);
        setData('payment_date', new Date().toISOString().split('T')[0]);
        setData('bank_account_code', (bankAccounts && bankAccounts[0]?.value) || '');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">{t('bills.page_title')}</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">{t('bills.subtitle')}</p>
                    </div>
                    {auth.permissions.includes('bills.create') && (
                        <div className="flex flex-wrap gap-2">
                            <Link
                                href={route('bills.batch')}
                                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                            >
                                {t('bills.batch')}
                            </Link>
                            <Link
                                href={route('bills.create')}
                                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg  transition-all duration-200"
                            >
                                <Icons.Plus /> {t('bills.create')}
                            </Link>
                        </div>
                    )}
                </div>
            }
        >
            <Head title={t('bills.title')} />


            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="relative overflow-hidden bg-mustard text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">{t('bills.total_bills')}</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{bills.length}</p>
                        <p className="text-xs text-mustard mt-1">{t('bills.total_bills_hint')}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{t('bills.outstanding_ap')}</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-xl font-bold text-terracotta font-mono tabular-nums">RM {formatMoney(totalOutstanding)}</p>
                        <p className="text-xs text-ink-muted mt-1">{t('bills.outstanding_hint')}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{t('bills.paid_period')}</span>
                            <span className="p-2 rounded-xl bg-forest/10 text-forest"><Icons.Check /></span>
                        </div>
                        <p className="text-xl font-bold text-forest font-mono tabular-nums">RM {formatMoney(totalPaidPeriod)}</p>
                        <p className="text-xs text-ink-muted mt-1">{t('bills.paid_hint')}</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={filters.searchInput}
                        onSearchChange={filters.setSearchInput}
                        searchPlaceholder={t('bills.search_placeholder')}
                        status={filters.status}
                        statuses={billStatuses}
                        extraFilters={
                            <select
                                value={supplierFilter}
                                onChange={(e) => { setSupplierFilter(e.target.value); filters.apply({ page: 1 }); }}
                                className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[160px]"
                            >
                                <option value="">{t('bills.all_suppliers')}</option>
                                {(suppliers || []).map((s) => (
                                    <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                                ))}
                            </select>
                        }
                        perPage={filters.perPage}
                        onApply={filters.apply}
                        from={filters.from}
                        to={filters.to}
                        total={filters.total}
                    />

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">{t('bills.table_bill')}</th>
                                    <th className="px-6 py-4">{t('bills.table_supplier')}</th>
                                    <th className="px-6 py-4">{t('bills.table_due_date')}</th>
                                    <th className="px-6 py-4">{t('bills.table_status')}</th>
                                    <th className="px-6 py-4">{t('bills.table_receipt')}</th>
                                    <th className="px-6 py-4 text-right">{t('bills.table_total')}</th>
                                    <th className="px-6 py-4 text-right">{t('bills.table_balance')}</th>
                                    <th className="px-6 py-4 text-right">{t('bills.table_actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredBills.length > 0 ? filteredBills.map((bill) => {
                                    const balanceDue = parseFloat(bill.balance_due ?? 0);
                                    return (
                                        <tr key={bill.id} className={`border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors group ${bill.status === 'void' ? 'opacity-60' : ''}`}>
                                            <td className="px-6 py-4">
                                                <Link href={route('bills.show', bill.id)} className="block group/link">
                                                    <span className="font-semibold text-ink group-hover/link:text-terracotta transition-colors">{bill.bill_number}</span>
                                                    <p className="text-xs text-ink-muted mt-0.5">
                                                        {bill.bill_date && new Date(bill.bill_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                    </p>
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 font-medium text-ink">{bill.supplier_name || '—'}</td>
                                            <td className="px-6 py-4 text-ink">
                                                {bill.due_date ? new Date(bill.due_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex w-fit px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getStatusBadge(bill.status)}`}>
                                                    {billStatusLabel(t, bill.status)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                {bill.supplier_invoice_url ? (
                                                    <button 
                                                        onClick={(e) => { e.stopPropagation(); setSelectedBillForReceipt(bill); }}
                                                        className="p-1.5 bg-surface-alt text-terracotta rounded-lg hover:bg-surface-alt transition-colors inline-flex items-center gap-1"
                                                        title="View Receipt"
                                                    >
                                                        <Icons.FileText />
                                                        <span className="text-[10px] font-bold">VIEW</span>
                                                    </button>
                                                ) : (
                                                    <span className="text-ink-muted text-[10px] font-medium italic">None</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-sm font-semibold text-ink tabular-nums">
                                                RM {formatMoney(bill.total_amount)}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-sm text-terracotta tabular-nums">
                                                {bill.status !== 'draft' && bill.status !== 'void' && balanceDue > 0 ? `RM ${formatMoney(balanceDue)}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <RowActionsMenu items={[
                                                    { label: t('bills.actions.open'), href: route('bills.show', bill.id), icon: <ActionIcons.Open /> },
                                                    { label: t('bills.actions.view_receipt'), icon: <ActionIcons.Pdf />, show: Boolean(bill.supplier_invoice_url), onClick: () => setSelectedBillForReceipt(bill) },
                                                    { label: t('bills.actions.post_to_ledger'), icon: <ActionIcons.Check />, show: bill.status === 'draft' && permissions.includes('bills.post'), onClick: () => handlePost(bill.id) },
                                                    { label: t('bills.actions.edit'), href: route('bills.edit', bill.id), icon: <ActionIcons.Pencil />, show: bill.status === 'draft' && permissions.includes('bills.edit') },
                                                    { label: t('bills.actions.record_payment'), icon: <ActionIcons.Currency />, show: bill.status !== 'draft' && bill.status !== 'void' && balanceDue > 0 && permissions.includes('bills.record-payment'), onClick: () => openPaymentModal(bill) },
                                                    { label: t('bills.actions.delete_draft'), icon: <ActionIcons.Trash />, danger: true, show: bill.status === 'draft' && permissions.includes('bills.delete'), onClick: () => handleDelete(bill.id) },
                                                    { label: t('bills.actions.void_bill'), icon: <ActionIcons.Trash />, danger: true, show: bill.status !== 'draft' && bill.status !== 'void' && permissions.includes('bills.void'), onClick: () => handleVoid(bill.id) },
                                                ]} />
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center">
                                            <p className="text-ink-muted text-sm font-medium">
                                                {filters.searchInput || filters.status || supplierFilter ? t('bills.empty_filtered') : t('bills.empty_none')}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <IndexPagination currentPage={filters.currentPage} lastPage={filters.lastPage} onPage={(page) => filters.apply({ page })} />
                </div>

                <Modal show={selectedBillForReceipt !== null} onClose={() => setSelectedBillForReceipt(null)} maxWidth="2xl">
                    <div className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-display font-medium text-ink">Receipt Preview</h3>
                            <button onClick={() => setSelectedBillForReceipt(null)} className="p-2 text-ink-muted hover:text-ink">
                                <IconX size={20} />
                            </button>
                        </div>
                        <div className="bg-surface-alt rounded-xl border border-border-warm flex items-center justify-center h-[70vh] overflow-hidden">
                            {(() => {
                                const path = selectedBillForReceipt?.supplier_invoice_path || '';
                                const url = selectedBillForReceipt?.supplier_invoice_url;
                                const isPdf = /\.pdf($|\?)/i.test(path) || /\.pdf($|\?)/i.test(url || '');
                                if (!url) {
                                    return (
                                        <p className="text-sm text-ink-muted px-6 text-center">
                                            No receipt file is linked to this bill.
                                        </p>
                                    );
                                }
                                return isPdf ? (
                                    <iframe
                                        src={`${url}#view=FitH&toolbar=1`}
                                        title="Receipt PDF"
                                        className="w-full h-full bg-cream"
                                    />
                                ) : (
                                    <img
                                        src={url}
                                        alt="Receipt Full Size"
                                        className="max-w-full max-h-full object-contain shadow-2xl transition-transform duration-300 p-4"
                                    />
                                );
                            })()}
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <a 
                                href={selectedBillForReceipt?.supplier_invoice_url} 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                className="px-4 py-2 text-ink border border-border-warm rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-cream"
                            >
                                Open in New Tab
                            </a>
                            <button 
                                onClick={() => setSelectedBillForReceipt(null)}
                                className="px-4 py-2 bg-ink text-white rounded-lg text-sm font-bold"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </Modal>

                {selectedBill && (
                    <div className="fixed inset-0 bg-ink/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-surface rounded-2xl shadow-2xl max-w-md w-full p-8 border border-border-warm">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-forest/10 text-forest">
                                    <Icons.Currency />
                                </span>
                                <div>
                                    <h3 className="text-xl font-display font-medium text-ink">{t('bills.payment_modal.title')}</h3>
                                    <p className="text-sm text-ink-muted">{t('bills.payment_modal.subtitle', { number: selectedBill.bill_number })}</p>
                                </div>
                            </div>
                            <form onSubmit={handlePaymentSubmit} className="space-y-5">
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">{t('bills.payment_modal.amount')} (RM)</label>
                                    <div className="relative">
                                        <span className="absolute inset-y-0 left-4 flex items-center text-ink-muted font-medium">RM</span>
                                        <input
                                            type="number"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            className="w-full pl-12 pr-4 py-3 border border-border-warm rounded-xl font-semibold text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta"
                                            step="0.01"
                                            required
                                        />
                                    </div>
                                    {errors.amount && <p className="text-terracotta text-xs mt-1 font-medium">{errors.amount}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">{t('bills.payment_modal.date')}</label>
                                        <input
                                            type="date"
                                            value={data.payment_date}
                                            onChange={(e) => setData('payment_date', e.target.value)}
                                            className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-terracotta"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">{t('bills.payment_modal.account')}</label>
                                        <select
                                            value={data.bank_account_code}
                                            onChange={(e) => setData('bank_account_code', e.target.value)}
                                            className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-terracotta"
                                        >
                                            {(bankAccounts || []).length === 0 && (
                                                <option value="">{t('bills.payment_modal.no_bank_accounts')}</option>
                                            )}
                                            {(bankAccounts || []).map((a) => (
                                                <option key={a.value} value={a.value}>{a.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button
                                        type="button"
                                        onClick={() => { setSelectedBill(null); reset(); }}
                                        className="flex-1 py-3 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream"
                                    >
                                        {t('bills.payment_modal.cancel')}
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-[2] py-3 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg "
                                    >
                                        {processing ? t('bills.payment_modal.processing') : t('bills.payment_modal.confirm')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

const IconX = ({ size = 20, ...props }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M18 6L6 18M6 6l12 12" />
    </svg>
);
