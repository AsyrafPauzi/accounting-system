import React, { useState, useRef, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>,
    ArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>,
    ArrowUp: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>,
    Document: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>,
    Search: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Wallet: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>,
};

function formatDate(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

const typeBadge = {
    deposit:    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    withdrawal: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300',
    manual:     'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    system:     'bg-surface-alt text-ink-muted',
};

export default function Index({ auth, transactions = [], totals = {}, filters = {}, bank_accounts = [], base_currency = 'MYR', can_create = false }) {
    const [search, setSearch] = useState(filters.search || '');
    const [account, setAccount] = useState(filters.account || '');
    const [start, setStart] = useState(filters.start_date || '');
    const [end, setEnd] = useState(filters.end_date || '');
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const handle = (e) => { if (menuRef.current && ! menuRef.current.contains(e.target)) setMenuOpen(false); };
        document.addEventListener('mousedown', handle);
        return () => document.removeEventListener('mousedown', handle);
    }, []);

    const apply = (next = {}) => {
        router.get(route('transactions.index'), {
            search: next.search ?? search,
            account: next.account ?? account,
            start_date: next.start_date ?? start,
            end_date: next.end_date ?? end,
        }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Wallet />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Transactions</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">All bank and cash movements in one feed</p>
                        </div>
                    </div>
                    {can_create && (
                        <div className="relative" ref={menuRef}>
                            <button
                                type="button"
                                onClick={() => setMenuOpen(o => !o)}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark transition-colors"
                            >
                                <Icons.Plus /> Add transaction <Icons.ChevronDown />
                            </button>
                            {menuOpen && (
                                <div className="absolute right-0 mt-2 w-60 bg-surface rounded-xl shadow-lg border border-border-warm overflow-hidden z-20">
                                    <Link href={route('transactions.deposit.create')} className="flex items-start gap-3 px-4 py-3 hover:bg-cream/60 border-b border-border-warm">
                                        <span className="mt-0.5 p-1.5 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"><Icons.ArrowDown /></span>
                                        <div>
                                            <p className="text-sm font-semibold text-ink">Add deposit</p>
                                            <p className="text-[11px] text-ink-muted">Money into a bank or cash account</p>
                                        </div>
                                    </Link>
                                    <Link href={route('transactions.withdrawal.create')} className="flex items-start gap-3 px-4 py-3 hover:bg-cream/60 border-b border-border-warm">
                                        <span className="mt-0.5 p-1.5 rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"><Icons.ArrowUp /></span>
                                        <div>
                                            <p className="text-sm font-semibold text-ink">Add withdrawal</p>
                                            <p className="text-[11px] text-ink-muted">Money out of a bank or cash account</p>
                                        </div>
                                    </Link>
                                    <Link href={route('journal.create')} className="flex items-start gap-3 px-4 py-3 hover:bg-cream/60">
                                        <span className="mt-0.5 p-1.5 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"><Icons.Document /></span>
                                        <div>
                                            <p className="text-sm font-semibold text-ink">Add journal entry</p>
                                            <p className="text-[11px] text-ink-muted">Multi-line / advanced posting</p>
                                        </div>
                                    </Link>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Transactions" />

            <div className="space-y-6">
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Movements</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-ink">{totals.count || 0}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Money in</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-emerald-600">+{formatCurrency(totals.in || 0, base_currency)}</div>
                    </div>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">Money out</div>
                        <div className="mt-2 text-xl font-bold tabular-nums text-rose-600">-{formatCurrency(totals.out || 0, base_currency)}</div>
                    </div>
                    <div className="bg-ink dark:bg-surface-alt rounded-2xl border border-border-warm shadow-sm p-4">
                        <div className="text-[10px] font-display font-medium text-cream/70 dark:text-ink-muted uppercase tracking-widest">Net change</div>
                        <div className={`mt-2 text-xl font-bold tabular-nums ${(totals.net || 0) >= 0 ? 'text-emerald-400' : 'text-terracotta-light'}`}>
                            {(totals.net || 0) >= 0 ? '+' : '-'}{formatCurrency(Math.abs(totals.net || 0), base_currency)}
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="p-4 sm:p-5 border-b border-border-warm flex flex-wrap items-end gap-3">
                        <div>
                            <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">Account</label>
                            <select
                                value={account}
                                onChange={(e) => { setAccount(e.target.value); apply({ account: e.target.value }); }}
                                className="border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                            >
                                <option value="">All bank/cash accounts</option>
                                {bank_accounts.map(a => (
                                    <option key={a.id} value={a.id}>{a.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">From</label>
                            <input type="date" value={start} onChange={(e) => setStart(e.target.value)} onBlur={() => apply({ start_date: start })} className="border border-border-warm rounded-xl py-2 px-3 text-sm" />
                        </div>
                        <div>
                            <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">To</label>
                            <input type="date" value={end} onChange={(e) => setEnd(e.target.value)} onBlur={() => apply({ end_date: end })} className="border border-border-warm rounded-xl py-2 px-3 text-sm" />
                        </div>
                        <div className="flex-1 min-w-[200px]">
                            <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1">Search</label>
                            <div className="relative">
                                <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><Icons.Search /></span>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && apply({ search })}
                                    onBlur={() => apply({ search })}
                                    placeholder="Description, category, reference"
                                    className="w-full pl-9 pr-3 py-2 border border-border-warm rounded-xl text-sm focus:ring-2 focus:ring-terracotta"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-6 py-3">Date</th>
                                    <th className="px-6 py-3">Description</th>
                                    <th className="px-6 py-3">Account</th>
                                    <th className="px-6 py-3">Category</th>
                                    <th className="px-6 py-3">Type</th>
                                    <th className="px-6 py-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {transactions.length > 0 ? transactions.map((t) => (
                                    <tr key={t.id} className="hover:bg-cream/30 text-sm">
                                        <td className="px-6 py-3 text-xs">{formatDate(t.date)}</td>
                                        <td className="px-6 py-3">
                                            <p className="font-semibold text-ink">{t.description}</p>
                                            {t.reference_number && <p className="text-[11px] text-ink-muted">Ref: {t.reference_number}</p>}
                                        </td>
                                        <td className="px-6 py-3 font-mono text-xs">{t.account_code} <span className="text-ink-muted">— {t.account_name}</span></td>
                                        <td className="px-6 py-3 text-xs text-ink-muted">{t.category}</td>
                                        <td className="px-6 py-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold capitalize ${typeBadge[t.type] || typeBadge.system}`}>
                                                {t.type}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono tabular-nums font-semibold">
                                            <span className={t.direction === 'in' ? 'text-emerald-600' : 'text-rose-600'}>
                                                {t.direction === 'in' ? '+' : '-'}{formatCurrency(Math.abs(t.amount), base_currency)}
                                            </span>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3 text-ink-muted">
                                                <span className="p-4 bg-surface-alt rounded-xl text-terracotta"><Icons.Wallet /></span>
                                                <div>
                                                    <p className="font-semibold text-ink">No transactions in this range</p>
                                                    <p className="text-sm mt-1">Try widening the date range, or add a deposit / withdrawal to get started.</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {transactions.length === 500 && (
                        <div className="px-6 py-3 bg-amber-50 dark:bg-amber-900/20 text-xs text-amber-800 dark:text-amber-300 border-t border-border-warm">
                            Showing the most recent 500 movements. Narrow the date range to see older entries.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
