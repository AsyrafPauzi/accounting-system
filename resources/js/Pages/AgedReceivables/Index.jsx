import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';
import useClientIndexFilters from '@/hooks/useClientIndexFilters';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
};

const SEARCH_KEYS = ['invoice_number', 'customer_name', 'customer_email'];
const AGING_STATUSES = [
    { value: 'current', label: 'Current' },
    { value: '1-30', label: '1–30 days' },
    { value: '31-60', label: '31–60 days' },
    { value: '61-90', label: '61–90 days' },
    { value: '90+', label: '90+ days' },
];

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}

function getAgingBadge(bucket) {
    const styles = {
        current: 'bg-forest/10 text-forest',
        '1-30': 'bg-mustard/15 text-mustard',
        '31-60': 'bg-mustard/15 text-mustard',
        '61-90': 'bg-terracotta/10 text-terracotta',
        '90+': 'bg-terracotta/10 text-terracotta',
    };
    return styles[bucket] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, invoices = [], summary = {}, bankAccounts = [] }) {
    const { total_receivable = 0, overdue_count = 0, aging_breakdown = {} } = summary;
    const overdueAmount = Object.entries(aging_breakdown)
        .filter(([key]) => key !== 'current')
        .reduce((sum, [, bucket]) => sum + (Number(bucket?.amount) || 0), 0);
    const permissions = auth.permissions || [];
    const filters = useClientIndexFilters(invoices, { searchKeys: SEARCH_KEYS, statusKey: 'aging_bucket' });
    const [selectedInvoice, setSelectedInvoice] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (bankAccounts && bankAccounts[0]?.value) || '',
    });

    const openPaymentModal = (invoice) => {
        const balance = parseFloat(invoice.balance_due) || 0;
        setSelectedInvoice(invoice);
        setData('amount', balance > 0 ? balance.toFixed(2) : 0);
        setData('payment_date', new Date().toISOString().split('T')[0]);
        setData('bank_account_code', (bankAccounts && bankAccounts[0]?.value) || '');
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        post(route('invoices.record-payment', selectedInvoice.id), {
            onSuccess: () => {
                setSelectedInvoice(null);
                reset();
            },
        });
    };

    const rowActions = (invoice) => [
        { label: 'Open', href: route('invoices.show', invoice.id), icon: <ActionIcons.Open /> },
        { label: 'Record payment', icon: <ActionIcons.Currency />, show: permissions.includes('invoices.record-payment'), onClick: () => openPaymentModal(invoice) },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Aged Receivables</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Who hasn&apos;t paid you — outstanding and aging</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('invoices.index')}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                        >
                            View all invoices
                        </Link>
                        {permissions.includes('invoices.create') && (
                            <Link
                                href={route('invoices.create')}
                                className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg transition-all duration-200"
                            >
                                <Icons.Plus /> Create invoice
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Aged Receivables" />

            <div className="space-y-4 sm:space-y-6 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 sm:p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Unpaid invoices</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-xl sm:text-2xl font-bold tabular-nums">{invoices.length}</p>
                        <p className="text-xs text-terracotta mt-1">Unpaid · Partially paid</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Outstanding (AR)</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Currency /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-terracotta font-mono tabular-nums">RM {formatMoney(total_receivable)}</p>
                        <p className="text-xs text-ink-muted mt-1">Outstanding from customers</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 sm:p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Overdue</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Exclamation /></span>
                        </div>
                        <p className="text-lg sm:text-xl font-bold text-terracotta font-mono tabular-nums">RM {formatMoney(overdueAmount)}</p>
                        <p className="text-xs text-ink-muted mt-1">{overdue_count} {overdue_count === 1 ? 'invoice' : 'invoices'} past due date</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={filters.searchInput}
                        onSearchChange={filters.setSearchInput}
                        searchPlaceholder="Search by invoice # or customer..."
                        status={filters.status}
                        statuses={AGING_STATUSES}
                        perPage={filters.perPage}
                        onApply={filters.apply}
                        from={filters.from}
                        to={filters.to}
                        total={filters.total}
                    />

                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Invoice</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3">Aging</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Amount</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filters.items.length > 0 ? filters.items.map((invoice) => (
                                    <tr key={invoice.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors">
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <Link href={route('invoices.show', invoice.id)} className="block group/link">
                                                <span className="font-semibold text-ink group-hover/link:text-terracotta">{invoice.invoice_number}</span>
                                                <p className="text-xs text-ink-muted mt-0.5">Due {formatDate(invoice.due_date)}</p>
                                            </Link>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            {invoice.customer_id ? (
                                                <Link href={route('customers.show', invoice.customer_id)} className="font-medium text-ink hover:text-terracotta">
                                                    {invoice.customer_name || '—'}
                                                </Link>
                                            ) : (
                                                <div className="font-medium text-ink">{invoice.customer_name || '—'}</div>
                                            )}
                                            <p className="text-xs text-ink-muted truncate max-w-[140px] sm:max-w-none">{invoice.customer_email || 'No email'}</p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getAgingBadge(invoice.aging_bucket)}`}>
                                                {invoice.aging_label || 'Current'}
                                            </span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <div className="font-mono text-sm font-semibold text-ink">RM {formatMoney(invoice.total_amount)}</div>
                                            {parseFloat(invoice.amount_paid) > 0 && (
                                                <p className="text-xs text-terracotta tabular-nums">Bal: RM {formatMoney(invoice.balance_due)}</p>
                                            )}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                            <RowActionsMenu items={rowActions(invoice)} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-16 text-center text-ink-muted text-sm">
                                            {filters.searchInput || filters.status
                                                ? 'No invoices match your filters.'
                                                : 'No outstanding receivables. All invoices are paid or no unpaid invoices.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden divide-y divide-border-warm">
                        {filters.items.length > 0 ? filters.items.map((invoice) => (
                            <div key={invoice.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('invoices.show', invoice.id)} className="font-semibold text-ink hover:text-terracotta">{invoice.invoice_number}</Link>
                                        <p className="text-xs text-ink-muted mt-0.5">{invoice.customer_name || '—'}</p>
                                        <p className="text-sm font-mono font-semibold text-ink mt-1">RM {formatMoney(invoice.total_amount)}</p>
                                        {parseFloat(invoice.amount_paid) > 0 && (
                                            <p className="text-xs text-terracotta tabular-nums">Bal: RM {formatMoney(invoice.balance_due)}</p>
                                        )}
                                        <span className={`inline-flex mt-2 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${getAgingBadge(invoice.aging_bucket)}`}>
                                            {invoice.aging_label || 'Current'}
                                        </span>
                                    </div>
                                    <RowActionsMenu items={rowActions(invoice)} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-ink-muted text-sm">
                                {filters.searchInput || filters.status
                                    ? 'No invoices match your filters.'
                                    : 'No outstanding receivables. All invoices are paid or no unpaid invoices.'}
                            </div>
                        )}
                    </div>

                    <IndexPagination currentPage={filters.currentPage} lastPage={filters.lastPage} onPage={(page) => filters.apply({ page })} />
                </div>

                {selectedInvoice && (
                    <div className="fixed inset-0 bg-ink/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                        <div className="bg-surface rounded-2xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-border-warm">
                            <div className="flex items-center gap-3 mb-6">
                                <span className="p-2.5 rounded-xl bg-forest/10 text-forest"><Icons.Currency /></span>
                                <div>
                                    <h3 className="text-xl font-display font-medium text-ink">Record receipt</h3>
                                    <p className="text-sm text-ink-muted">Invoice {selectedInvoice.invoice_number}</p>
                                </div>
                            </div>
                            <form onSubmit={handlePaymentSubmit} className="space-y-5">
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Amount (RM)</label>
                                    <div className="relative">
                                        <span className="absolute inset-y-0 left-4 flex items-center text-ink-muted font-medium">RM</span>
                                        <input
                                            type="number"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            className="w-full pl-12 pr-4 py-3 border border-border-warm rounded-xl font-semibold text-ink"
                                            step="0.01"
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Date</label>
                                        <input type="date" value={data.payment_date} onChange={(e) => setData('payment_date', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm" required />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Bank account</label>
                                        <select value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm">
                                            {(bankAccounts || []).length === 0 && (
                                                <option value="">No bank/cash accounts — add one in Chart of Accounts</option>
                                            )}
                                            {(bankAccounts || []).map((a) => (
                                                <option key={a.value} value={a.value}>{a.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={() => { setSelectedInvoice(null); reset(); }} className="flex-1 py-3 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream">
                                        Cancel
                                    </button>
                                    <button type="submit" disabled={processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50">
                                        {processing ? 'Processing...' : 'Confirm receipt'}
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
