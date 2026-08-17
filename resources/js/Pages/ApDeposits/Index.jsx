import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

export default function Index({ auth, deposits = [] }) {
    const [search, setSearch] = useState('');
    const filtered = deposits.filter((d) =>
        (d.reference || '').toLowerCase().includes(search.toLowerCase()) ||
        (d.supplier?.name || d.supplier_name || '').toLowerCase().includes(search.toLowerCase())
    );
    const unapplied = deposits.reduce((sum, d) => sum + Math.max(0, Number(d.amount || 0) - Number(d.applied_amount || 0)), 0);

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Supplier deposits</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">One bank payment, then knock off bills. Leftover stays as a prepaid deposit.</p>
                </div>
                {auth.permissions.includes('bills.record-payment') && (
                    <Link href={route('ap-deposits.create')} className="inline-flex items-center gap-2 px-4 sm:px-5 py-2.5 rounded-xl font-semibold text-white bg-terracotta shadow-lg">
                        <Icons.Plus /> New payment
                    </Link>
                )}
            </div>
        }>
            <Head title="Supplier deposits" />
            <div className="space-y-4 min-w-0">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="bg-terracotta text-white rounded-2xl p-4 shadow-lg">
                        <div className="flex items-center justify-between mb-1">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Supplier deposits</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-xl font-bold tabular-nums">{deposits.length}</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-4 border border-border-warm shadow-sm">
                        <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Unapplied</span>
                        <p className="text-lg font-bold text-terracotta font-mono tabular-nums mt-1">{formatCurrency(unapplied, 'MYR')}</p>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm flex items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 min-w-0 max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.MagnifyingGlass /></span>
                            <input type="text" placeholder="Search reference or supplier..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 w-full border border-border-warm rounded-xl py-2 px-4 text-sm font-medium focus:ring-2 focus:ring-terracotta" />
                        </div>
                        <span className="text-ink-muted text-sm ml-auto">{filtered.length} of {deposits.length}</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">Number</th>
                                    <th className="px-4 sm:px-6 py-3">Supplier</th>
                                    <th className="px-4 sm:px-6 py-3">Date</th>
                                    <th className="px-4 sm:px-6 py-3 text-right">Unapplied</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-28">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length > 0 ? filtered.map((d) => {
                                    const open = Math.max(0, Number(d.amount || 0) - Number(d.applied_amount || 0));
                                    return (
                                        <tr key={d.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                            <td className="px-4 sm:px-6 py-3">
                                                <Link href={route('ap-deposits.show', d.id)} className="font-semibold text-ink hover:text-terracotta">{d.reference || `DEP-${d.id}`}</Link>
                                            </td>
                                            <td className="px-4 sm:px-6 py-3 font-medium">{d.supplier?.name || d.supplier_name || '—'}</td>
                                            <td className="px-4 sm:px-6 py-3 text-ink-muted">{formatDate(d.payment_date)}</td>
                                            <td className="px-4 sm:px-6 py-3 text-right font-mono text-sm">{formatCurrency(open, 'MYR')}</td>
                                            <td className="px-4 sm:px-6 py-3 text-right">
                                                <Link href={route('ap-deposits.show', d.id)} className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt">
                                                    Open <Icons.ChevronRight />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr><td colSpan={5} className="px-6 py-16 text-center text-ink-muted text-sm">{search ? 'No supplier deposits match.' : 'No supplier deposits yet. Record a knock-off across bills from New payment.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
