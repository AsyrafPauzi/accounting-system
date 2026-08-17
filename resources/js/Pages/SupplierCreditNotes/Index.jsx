import React, { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';
import useClientIndexFilters from '@/hooks/useClientIndexFilters';

const SEARCH_KEYS = ['scn_number', 'supplier_name'];

export default function Index({ auth, notes = [] }) {
    const filters = useClientIndexFilters(notes, { searchKeys: SEARCH_KEYS });
    const statuses = useMemo(() => {
        const seen = [...new Set(notes.map((note) => note.status).filter(Boolean))];
        return seen.map((value) => ({ value, label: String(value).replace(/_/g, ' ') }));
    }, [notes]);

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
                    <IndexFilterBar
                        search={filters.searchInput}
                        onSearchChange={filters.setSearchInput}
                        searchPlaceholder="Search number or supplier..."
                        status={filters.status}
                        statuses={statuses}
                        perPage={filters.perPage}
                        onApply={filters.apply}
                        from={filters.from}
                        to={filters.to}
                        total={filters.total}
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-[10px] uppercase tracking-widest text-ink-muted bg-cream/80">
                                <tr>
                                    <th className="px-6 py-4 text-left">Number</th>
                                    <th className="px-6 py-4 text-left">Supplier</th>
                                    <th className="px-6 py-4 text-right">Amount</th>
                                    <th className="px-6 py-4 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filters.items.map((note) => (
                                    <tr
                                        key={note.id}
                                        className="border-t border-border-warm hover:bg-cream/80 cursor-pointer"
                                        onClick={() => router.visit(route('supplier-credit-notes.show', note.id))}
                                    >
                                        <td className="px-6 py-4">
                                            <Link
                                                href={route('supplier-credit-notes.show', note.id)}
                                                className="font-semibold text-terracotta hover:underline"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                {note.scn_number}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4">{note.supplier_name || '—'}</td>
                                        <td className="px-6 py-4 text-right font-mono text-terracotta">{formatCurrency(note.total_amount, note.currency || 'MYR')}</td>
                                        <td className="px-6 py-4 text-right" onClick={(e) => e.stopPropagation()}>
                                            <RowActionsMenu items={[
                                                { label: 'Open', href: route('supplier-credit-notes.show', note.id), icon: <ActionIcons.Open /> },
                                                { label: 'Download PDF', href: route('supplier-credit-notes.pdf', note.id), external: true, icon: <ActionIcons.Pdf /> },
                                            ]} />
                                        </td>
                                    </tr>
                                ))}
                                {filters.items.length === 0 && (
                                    <tr><td colSpan={4} className="px-6 py-16 text-center text-ink-muted">{filters.searchInput || filters.status ? 'No supplier credit notes match your filters.' : 'No supplier credit notes yet.'}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <IndexPagination currentPage={filters.currentPage} lastPage={filters.lastPage} onPage={(page) => filters.apply({ page })} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
