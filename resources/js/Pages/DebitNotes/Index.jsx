import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

function statusBadge(status) {
    const styles = {
        posted: 'bg-forest/10 text-forest',
        void: 'bg-surface-alt text-ink-muted',
        draft: 'bg-surface-alt text-ink',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

export default function Index({ auth, debitNotes = [] }) {
    const [search, setSearch] = useState('');
    const filtered = debitNotes.filter((dn) =>
        (dn.dn_number || '').toLowerCase().includes(search.toLowerCase()) ||
        (dn.customer_name || '').toLowerCase().includes(search.toLowerCase())
    );
    const totalValue = debitNotes.filter((dn) => dn.status !== 'void').reduce((sum, dn) => sum + (parseFloat(dn.total_amount) || 0), 0);

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Debit Notes</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Charge extra on a posted invoice — tax and ledger included</p>
                </div>
                {auth.permissions.includes('debit-notes.create') && (
                    <Link href={route('debit-notes.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg">
                        <Icons.Plus /> Issue debit note
                    </Link>
                )}
            </div>
        }>
            <Head title="Debit Notes" />
            <div className="space-y-4 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="relative overflow-hidden bg-terracotta text-white rounded-2xl p-4 shadow-lg">
                        <div className="flex items-center justify-between mb-1">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Issued</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-xl font-bold tabular-nums">{debitNotes.length}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 border border-border-warm shadow-sm">
                        <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Value</span>
                        <p className="text-lg font-bold text-ink font-mono tabular-nums mt-1">RM {totalValue.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm flex items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-0 max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder="Search DN # or customer..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 w-full border border-border-warm rounded-xl py-2 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <span className="text-ink-muted text-sm ml-auto">{filtered.length} of {debitNotes.length}</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Debit note</th>
                                    <th className="px-4 sm:px-6 py-3">Customer</th>
                                    <th className="px-4 sm:px-6 py-3 hidden md:table-cell">Related invoice</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Amount</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length > 0 ? filtered.map((dn) => (
                                    <tr key={dn.id} className={`border-b border-border-warm last:border-0 hover:bg-cream/80 ${dn.status === 'void' ? 'opacity-60' : ''}`}>
                                        <td className="px-4 sm:px-6 py-3">
                                            <Link href={route('debit-notes.show', dn.id)} className="font-semibold text-ink hover:text-terracotta">{dn.dn_number}</Link>
                                            <p className="text-xs text-ink-muted mt-0.5">
                                                {dn.issue_date ? new Date(dn.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                            </p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 font-medium text-ink">{dn.customer_name || '—'}</td>
                                        <td className="px-4 sm:px-6 py-3 hidden md:table-cell">
                                            {dn.invoice_id ? (
                                                <Link href={route('invoices.show', dn.invoice_id)} className="text-sm text-terracotta hover:underline">
                                                    {dn.invoice?.invoice_number || `Invoice #${dn.invoice_id}`}
                                                </Link>
                                            ) : <span className="text-xs text-ink-muted">Standalone</span>}
                                        </td>
                                        <td className="px-4 sm:px-6 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase ${statusBadge(dn.status)}`}>{dn.status}</span>
                                        </td>
                                        <td className="px-4 sm:px-6 py-3 text-right font-mono text-sm font-semibold">{formatCurrency(dn.total_amount, dn.currency)}</td>
                                        <td className="px-4 sm:px-6 py-3 text-right">
                                            <Link href={route('debit-notes.show', dn.id)} className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt hover:bg-cream">
                                                Open <Icons.ChevronRight />
                                            </Link>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan={6} className="px-6 py-16 text-center text-ink-muted text-sm">{search ? 'No debit notes match your search.' : 'No debit notes yet. Issue one from here or from a posted invoice.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
