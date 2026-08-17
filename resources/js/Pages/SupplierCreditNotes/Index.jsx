import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

export default function Index({ auth, notes = [] }) {
    const [search, setSearch] = useState('');
    const filteredNotes = notes.filter((note) =>
        (note.scn_number || '').toLowerCase().includes(search.toLowerCase())
        || (note.supplier_name || '').toLowerCase().includes(search.toLowerCase())
    );

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Supplier credit notes</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Credits that reduce what you owe suppliers</p>
                </div>
                {auth.permissions.includes('bills.create') && (
                    <Link href={route('supplier-credit-notes.create')} className="inline-flex items-center px-4 py-2.5 rounded-xl bg-terracotta text-white text-sm font-semibold">
                        New credit note
                    </Link>
                )}
            </div>
        }>
            <Head title="Supplier credit notes" />
            <div className="space-y-6">
                <div className="bg-terracotta text-white rounded-2xl p-6 shadow-lg">
                    <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Total credit notes</span>
                    <p className="text-2xl font-bold tabular-nums mt-2">{notes.length} issued</p>
                </div>
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm flex flex-col sm:flex-row sm:items-center gap-3 bg-cream/50">
                        <input
                            type="search"
                            placeholder="Search number or supplier..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full sm:max-w-sm border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                        />
                        <span className="text-ink-muted text-sm font-medium">{filteredNotes.length} of {notes.length}</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-[10px] uppercase tracking-widest text-ink-muted bg-cream/80">
                                <tr>
                                    <th className="px-6 py-4 text-left">Number</th>
                                    <th className="px-6 py-4 text-left">Supplier</th>
                                    <th className="px-6 py-4 text-right">Amount</th>
                                    <th className="px-6 py-4 text-right">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredNotes.map((note) => (
                                    <tr key={note.id} className="border-t border-border-warm hover:bg-cream/80">
                                        <td className="px-6 py-4 font-semibold text-ink">{note.scn_number}</td>
                                        <td className="px-6 py-4">{note.supplier_name || '—'}</td>
                                        <td className="px-6 py-4 text-right font-mono text-terracotta">{formatCurrency(note.total_amount, note.currency || 'MYR')}</td>
                                        <td className="px-6 py-4 text-right"><Link className="text-xs font-semibold text-terracotta" href={route('supplier-credit-notes.show', note.id)}>Open →</Link></td>
                                    </tr>
                                ))}
                                {filteredNotes.length === 0 && (
                                    <tr><td colSpan={4} className="px-6 py-16 text-center text-ink-muted">{search ? 'No supplier credit notes match your search.' : 'No supplier credit notes yet.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
