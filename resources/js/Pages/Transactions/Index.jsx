import React, { useMemo, useRef, useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import IndexPagination from '@/Components/IndexPagination';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    ArrowDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>,
    ArrowUp: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>,
    Document: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ChevronDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Search: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    Calendar: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>,
};

function formatDateShort(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function formatMonthGroup(value) {
    if (! value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
}

function groupByMonth(transactions) {
    const groups = [];
    let current = null;

    transactions.forEach((t) => {
        const key = formatMonthGroup(t.date);
        if (! current || current.key !== key) {
            current = { key, items: [] };
            groups.push(current);
        }
        current.items.push(t);
    });

    return groups;
}

function TransactionRow({ t, base_currency, showAccount, showBalance, onClick }) {
    const isIn = t.direction === 'in';

    return (
        <button
            type="button"
            onClick={onClick}
            className="w-full flex items-center gap-3 sm:gap-4 px-4 sm:px-5 py-3.5 hover:bg-cream/60 transition-colors text-left group"
        >
            <span className={`shrink-0 w-9 h-9 rounded-full flex items-center justify-center ${isIn ? 'bg-forest/10 text-forest' : 'bg-terracotta/10 text-terracotta'}`}>
                {isIn ? <Icons.ArrowDown /> : <Icons.ArrowUp />}
            </span>

            <div className="flex-1 min-w-0">
                <div className="flex items-baseline gap-2">
                    <span className="text-xs font-medium text-ink-muted tabular-nums shrink-0 w-12">{formatDateShort(t.date)}</span>
                    <p className="font-semibold text-ink truncate group-hover:text-terracotta transition-colors">{t.description}</p>
                </div>
                <p className="text-xs text-ink-muted mt-0.5 truncate pl-14 sm:pl-14">
                    {showAccount ? `${t.account_name} · ` : ''}{t.category}
                    {t.reference_number ? ` · ${t.reference_number}` : ''}
                </p>
            </div>

            <div className="shrink-0 text-right">
                <p className={`font-mono tabular-nums font-semibold text-sm ${isIn ? 'text-forest' : 'text-terracotta'}`}>
                    {isIn ? '+' : '−'}{formatCurrency(Math.abs(t.amount), base_currency)}
                </p>
                {showBalance && (
                    <p className="text-[11px] text-ink-muted font-mono tabular-nums mt-0.5">
                        {formatCurrency(t.running_balance ?? 0, base_currency)}
                    </p>
                )}
            </div>

            <Icons.ChevronRight />
        </button>
    );
}

export default function Index({
    auth,
    transactions = [],
    totals = {},
    filters = {},
    paginator = {},
    show_running_balance = false,
    bank_accounts = [],
    base_currency = 'MYR',
    can_create = false,
}) {
    const [search, setSearch] = useState(filters.search || '');
    const [account, setAccount] = useState(filters.account || '');
    const [start, setStart] = useState(filters.start_date || '');
    const [end, setEnd] = useState(filters.end_date || '');
    const [perPage, setPerPage] = useState(filters.per_page || 25);
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);

    const selectedAccount = useMemo(
        () => bank_accounts.find((a) => String(a.id) === String(account)),
        [bank_accounts, account]
    );

    const grouped = useMemo(() => groupByMonth(transactions), [transactions]);

    useEffect(() => {
        const handle = (e) => { if (menuRef.current && ! menuRef.current.contains(e.target)) setMenuOpen(false); };
        document.addEventListener('mousedown', handle);
        return () => document.removeEventListener('mousedown', handle);
    }, []);

    const apply = (next = {}) => {
        router.get(route('transactions.index'), {
            search: next.search ?? search,
            account: next.account !== undefined ? next.account : account,
            start_date: next.start_date ?? start,
            end_date: next.end_date ?? end,
            per_page: next.per_page ?? perPage,
            page: next.page ?? 1,
        }, { preserveState: true, replace: true });
    };

    const selectAccount = (id) => {
        setAccount(id);
        apply({ account: id, page: 1 });
    };

    const totalBalance = useMemo(
        () => bank_accounts.reduce((sum, a) => sum + (a.balance ?? 0), 0),
        [bank_accounts]
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta text-xs tracking-wider">Accounting</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Transactions</h1>
                        <p className="text-ink-muted text-sm mt-1">Bank &amp; cash movements</p>
                    </div>
                    {can_create && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Link
                                href={route('transactions.deposit.create')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-forest bg-forest/10 border border-forest/20 hover:bg-forest/15 transition-colors"
                            >
                                <Icons.ArrowDown /> Deposit
                            </Link>
                            <Link
                                href={route('transactions.withdrawal.create')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-terracotta bg-terracotta/10 border border-terracotta/20 hover:bg-terracotta/15 transition-colors"
                            >
                                <Icons.ArrowUp /> Withdraw
                            </Link>
                            <div className="relative" ref={menuRef}>
                                <button
                                    type="button"
                                    onClick={() => setMenuOpen((o) => !o)}
                                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark transition-colors"
                                >
                                    <Icons.Plus /> More <Icons.ChevronDown />
                                </button>
                                {menuOpen && (
                                    <div className="absolute right-0 mt-2 w-52 bg-surface rounded-xl shadow-lg border border-border-warm overflow-hidden z-20">
                                        <Link href={route('journal.create')} className="flex items-center gap-3 px-4 py-3 hover:bg-cream/60 text-sm font-medium text-ink">
                                            <Icons.Document /> Journal entry
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Transactions" />

            <div className="space-y-4 sm:space-y-5 min-w-0">
                {/* Account switcher — Wave/Bukku style pills */}
                <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 scrollbar-hide">
                    <button
                        type="button"
                        onClick={() => selectAccount('')}
                        className={`shrink-0 rounded-xl border px-4 py-3 text-left transition-all min-w-[140px] ${
                            ! account
                                ? 'border-terracotta bg-terracotta text-white shadow-md'
                                : 'border-border-warm bg-surface hover:border-terracotta/40'
                        }`}
                    >
                        <p className={`text-[10px] font-semibold uppercase tracking-wider ${! account ? 'text-white/80' : 'text-ink-muted'}`}>All accounts</p>
                        <p className={`mt-1 font-mono tabular-nums font-bold text-sm ${! account ? 'text-white' : 'text-ink'}`}>
                            {formatCurrency(totalBalance, base_currency)}
                        </p>
                    </button>
                    {bank_accounts.map((a) => {
                        const active = String(account) === String(a.id);
                        return (
                            <button
                                key={a.id}
                                type="button"
                                onClick={() => selectAccount(String(a.id))}
                                className={`shrink-0 rounded-xl border px-4 py-3 text-left transition-all min-w-[140px] ${
                                    active
                                        ? 'border-terracotta bg-terracotta text-white shadow-md'
                                        : 'border-border-warm bg-surface hover:border-terracotta/40'
                                }`}
                            >
                                <p className={`text-[10px] font-semibold uppercase tracking-wider truncate max-w-[160px] ${active ? 'text-white/80' : 'text-ink-muted'}`}>
                                    {a.name}
                                </p>
                                <p className={`mt-1 font-mono tabular-nums font-bold text-sm ${active ? 'text-white' : 'text-ink'}`}>
                                    {formatCurrency(a.balance ?? 0, base_currency)}
                                </p>
                            </button>
                        );
                    })}
                </div>

                {/* Period summary strip */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="grid grid-cols-3 divide-x divide-border-warm">
                        <div className="px-4 py-4 text-center sm:text-left sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Received</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-forest text-sm sm:text-base">
                                +{formatCurrency(totals.in || 0, base_currency)}
                            </p>
                        </div>
                        <div className="px-4 py-4 text-center sm:text-left sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Spent</p>
                            <p className="mt-1 font-mono tabular-nums font-bold text-terracotta text-sm sm:text-base">
                                −{formatCurrency(totals.out || 0, base_currency)}
                            </p>
                        </div>
                        <div className="px-4 py-4 text-center sm:text-left sm:px-6">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Net</p>
                            <p className={`mt-1 font-mono tabular-nums font-bold text-sm sm:text-base ${(totals.net || 0) >= 0 ? 'text-forest' : 'text-terracotta'}`}>
                                {(totals.net || 0) >= 0 ? '+' : '−'}{formatCurrency(Math.abs(totals.net || 0), base_currency)}
                            </p>
                        </div>
                    </div>
                    {selectedAccount && (
                        <div className="px-4 sm:px-6 py-3 border-t border-border-warm bg-cream/40 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-muted">
                            <span>
                                Showing <strong className="text-ink">{selectedAccount.name}</strong>
                                {show_running_balance && ' · running balance on each line'}
                            </span>
                            <Link
                                href={route('general-ledger.report', { account_code: selectedAccount.code, from: 'coa' })}
                                className="font-semibold text-terracotta hover:text-terracotta-dark"
                            >
                                View account ledger →
                            </Link>
                        </div>
                    )}
                </div>

                {/* Toolbar */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-3 sm:p-4">
                    <form
                        onSubmit={(e) => { e.preventDefault(); apply({ page: 1 }); }}
                        className="flex flex-col sm:flex-row gap-3"
                    >
                        <div className="relative flex-1 min-w-0">
                            <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted pointer-events-none"><Icons.Search /></span>
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search description, category, reference…"
                                className="w-full pl-10 pr-3 py-2.5 border border-border-warm rounded-xl text-sm text-ink focus:ring-2 focus:ring-terracotta"
                            />
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex items-center gap-2 rounded-xl border border-border-warm px-3 py-2 bg-cream/30">
                                <Icons.Calendar />
                                <input
                                    type="date"
                                    value={start}
                                    onChange={(e) => setStart(e.target.value)}
                                    className="border-0 bg-transparent text-sm text-ink focus:ring-0 p-0 w-[7.5rem]"
                                    aria-label="From date"
                                />
                                <span className="text-ink-muted text-xs">to</span>
                                <input
                                    type="date"
                                    value={end}
                                    onChange={(e) => setEnd(e.target.value)}
                                    className="border-0 bg-transparent text-sm text-ink focus:ring-0 p-0 w-[7.5rem]"
                                    aria-label="To date"
                                />
                            </div>
                            <select
                                value={perPage}
                                onChange={(e) => { setPerPage(Number(e.target.value)); apply({ per_page: Number(e.target.value), page: 1 }); }}
                                className="border border-border-warm rounded-xl py-2.5 pl-3 pr-8 text-sm text-ink focus:ring-2 focus:ring-terracotta"
                            >
                                <option value={10}>10 rows</option>
                                <option value={25}>25 rows</option>
                                <option value={50}>50 rows</option>
                                <option value={100}>100 rows</option>
                            </select>
                            <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark">
                                Apply
                            </button>
                        </div>
                    </form>
                    <p className="mt-2 text-xs text-ink-muted text-right">
                        {paginator.total > 0 ? `${paginator.from}–${paginator.to} of ${paginator.total} movements` : 'No movements'}
                    </p>
                </div>

                {/* Transaction feed */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    {transactions.length > 0 ? (
                        grouped.map((group) => (
                            <div key={group.key}>
                                <div className="sticky top-0 z-10 px-4 sm:px-5 py-2.5 bg-cream/90 backdrop-blur-sm border-b border-border-warm">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-ink-muted">{group.key}</p>
                                </div>
                                <div className="divide-y divide-border-warm/80">
                                    {group.items.map((t) => (
                                        <TransactionRow
                                            key={t.id}
                                            t={t}
                                            base_currency={base_currency}
                                            showAccount={! account}
                                            showBalance={show_running_balance}
                                            onClick={() => router.visit(t.source_route)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="px-6 py-16 text-center">
                            <div className="inline-flex w-14 h-14 items-center justify-center rounded-2xl bg-cream text-terracotta mb-4">
                                <Icons.Search />
                            </div>
                            <p className="font-semibold text-ink">No transactions in this range</p>
                            <p className="text-sm text-ink-muted mt-1 max-w-sm mx-auto">
                                Try a wider date range, pick another account, or record a deposit or withdrawal.
                            </p>
                            {can_create && (
                                <div className="flex flex-wrap justify-center gap-2 mt-5">
                                    <Link href={route('transactions.deposit.create')} className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta">
                                        Add deposit
                                    </Link>
                                    <Link href={route('transactions.withdrawal.create')} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink border border-border-warm bg-surface hover:bg-cream">
                                        Add withdrawal
                                    </Link>
                                </div>
                            )}
                        </div>
                    )}

                    <IndexPagination
                        currentPage={paginator.current_page || 1}
                        lastPage={paginator.last_page || 1}
                        onPage={(page) => apply({ page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
