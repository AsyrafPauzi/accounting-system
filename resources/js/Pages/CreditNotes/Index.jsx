import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Currency: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    MagnifyingGlass: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

export default function Index({ auth, creditNotes = [] }) {
    const [search, setSearch] = useState('');

    const filteredNotes = creditNotes.filter(cn =>
        (cn.cn_number || '').toLowerCase().includes(search.toLowerCase()) ||
        (cn.customer_name || '').toLowerCase().includes(search.toLowerCase())
    );

    const totalValue = creditNotes.reduce((sum, cn) => sum + (parseFloat(cn.total_amount) || 0), 0);

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Credit Notes</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Refunds and invoice adjustments</p>
                    </div>
                    {auth.permissions.includes('credit-notes.create') && (
                        <Link href={route('credit-notes.create-standalone')} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-white bg-mustard hover:bg-mustard/90 text-sm">
                            Standalone credit
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Credit Notes" />

            <div className="space-y-6">
                {/* KPI row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="relative overflow-hidden bg-mustard text-white rounded-2xl p-6 shadow-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total Credit Notes</span>
                            <span className="p-2 rounded-xl bg-surface/10"><Icons.Document /></span>
                        </div>
                        <p className="text-2xl font-bold tabular-nums">{creditNotes.length} issued</p>
                        <p className="text-xs text-mustard mt-1">Adjustments and refunds</p>
                    </div>
                    <div className="bg-surface rounded-2xl p-6 border border-border-warm shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Total Value Credited</span>
                            <span className="p-2 rounded-xl bg-terracotta/10 text-terracotta"><Icons.Currency /></span>
                        </div>
                        <p className="text-xl font-bold text-terracotta font-mono tabular-nums">
                            RM {totalValue.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-ink-muted mt-1">Net credit issued</p>
                    </div>
                </div>

                {/* Table Card */}
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex items-center gap-3 bg-cream/50">
                        <div className="relative flex-1 max-w-sm">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-ink-muted">
                                <Icons.MagnifyingGlass />
                            </span>
                            <input 
                                type="text" 
                                placeholder="Search by CN # or customer..." 
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="pl-10 w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink placeholder-ink-muted/60 focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors"
                            />
                        </div>
                        <span className="text-ink-muted text-sm font-medium">
                            {filteredNotes.length} of {creditNotes.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Credit Note</th>
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4 hidden md:table-cell">Reason</th>
                                    <th className="px-6 py-4 text-right">Value</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredNotes.length > 0 ? filteredNotes.map((cn) => (
                                    <tr key={cn.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80 transition-colors group">
                                        <td className="px-6 py-4">
                                            <Link href={route('credit-notes.show', cn.id)} className="font-semibold text-ink hover:text-terracotta">{cn.cn_number}</Link>
                                            <p className="text-xs text-ink-muted mt-0.5">
                                                {cn.issue_date ? new Date(cn.issue_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                            </p>
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-ink">{cn.customer_name || '—'}</td>
                                        <td className="px-6 py-4 hidden md:table-cell text-xs text-ink-muted">{cn.reason_code || '—'}</td>
                                        <td className="px-6 py-4 text-right">
                                            <span className="font-mono text-sm font-semibold text-terracotta tabular-nums">
                                                - RM {parseFloat(cn.total_amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-mustard/15 text-mustard">
                                                {cn.status || 'posted'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link
                                                href={route('credit-notes.show', cn.id)}
                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt transition-colors"
                                            >
                                                Open <Icons.ChevronRight />
                                            </Link>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center">
                                            <p className="text-ink-muted text-sm font-medium">
                                                {search ? 'No credit notes match your search.' : 'No credit notes issued yet. Create one from an invoice.'}
                                            </p>
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