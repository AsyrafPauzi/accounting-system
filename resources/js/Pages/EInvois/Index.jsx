import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { confirm } from '@/utils/swal';

// ─── Icons ───────────────────────────────────────────────────────────────────
const Icons = {
    Shield:       () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>,
    Upload:       () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>,
    Refresh:      () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>,
    Cancel:       () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Dots:         () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>,
    Warning:      () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>,
    Document:     () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    CheckBadge:   () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>,
    Search:       () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>,
    ChevLeft:     () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    ChevRight:    () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    ExternalLink: () => <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>,
    Clock:        () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
};

// ─── LHDN Status config ───────────────────────────────────────────────────────
const LHDN_STATUS = {
    pending:   { label: 'Pending',   bg: 'bg-amber-100',   text: 'text-amber-700',   dot: 'bg-amber-500'  },
    submitted: { label: 'Submitted', bg: 'bg-blue-100',    text: 'text-blue-700',    dot: 'bg-blue-500'   },
    valid:     { label: 'Valid',     bg: 'bg-emerald-100', text: 'text-emerald-700', dot: 'bg-emerald-500' },
    invalid:   { label: 'Invalid',   bg: 'bg-rose-100',    text: 'text-rose-700',    dot: 'bg-rose-500'   },
    cancelled: { label: 'Cancelled', bg: 'bg-slate-100',   text: 'text-slate-500',   dot: 'bg-slate-400'  },
};

function LhdnBadge({ status }) {
    const cfg = LHDN_STATUS[status] ?? LHDN_STATUS.pending;
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${cfg.bg} ${cfg.text}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot}`} />
            {cfg.label}
        </span>
    );
}

function InvoiceStatusBadge({ status }) {
    const styles = {
        paid: 'bg-emerald-100 text-emerald-700',
        unpaid: 'bg-rose-100 text-rose-700',
        'partially paid': 'bg-blue-100 text-blue-700',
        draft: 'bg-slate-100 text-slate-500',
        void: 'bg-slate-200 text-slate-400',
    };
    return (
        <span className={`inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold uppercase ${styles[status] ?? 'bg-slate-100 text-slate-500'}`}>
            {status}
        </span>
    );
}

// ─── Stat card ────────────────────────────────────────────────────────────────
function StatCard({ label, value, icon: Icon, iconBg, valueColor, sub }) {
    return (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-start gap-4">
            <span className={`flex-shrink-0 p-3 rounded-xl ${iconBg}`}><Icon /></span>
            <div className="min-w-0">
                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{label}</p>
                <p className={`text-2xl font-extrabold mt-0.5 tabular-nums ${valueColor}`}>{value}</p>
                {sub && <p className="text-xs text-slate-400 mt-0.5">{sub}</p>}
            </div>
        </div>
    );
}

// ─── Actions dropdown per row ────────────────────────────────────────────────
function ActionsCell({ invoice, onSubmit, onRefresh, onCancel }) {
    const canSubmit  = !['submitted', 'valid', 'cancelled'].includes(invoice.lhdn_status) && invoice.status !== 'draft';
    const canRefresh = ['submitted'].includes(invoice.lhdn_status);
    const canCancel  = ['submitted', 'valid'].includes(invoice.lhdn_status);

    return (
        <Menu as="div" className="relative inline-block text-left">
            <MenuButton id={`einvois-actions-${invoice.id}`}
                className="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors">
                <Icons.Dots />
            </MenuButton>
            <MenuItems className="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black/5 focus:outline-none py-1.5">
                <div className="px-3 py-1.5 mb-1 border-b border-slate-100">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">LHDN Actions</p>
                </div>

                {canSubmit && (
                    <MenuItem>
                        <button id={`einvois-submit-${invoice.id}`} onClick={() => onSubmit(invoice)}
                            className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-indigo-700 hover:bg-indigo-50 font-medium">
                            <Icons.Upload /> Submit to LHDN
                        </button>
                    </MenuItem>
                )}

                {canRefresh && (
                    <MenuItem>
                        <button id={`einvois-refresh-${invoice.id}`} onClick={() => onRefresh(invoice)}
                            className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 font-medium">
                            <Icons.Refresh /> Refresh Status
                        </button>
                    </MenuItem>
                )}

                {canCancel && (
                    <MenuItem>
                        <button id={`einvois-cancel-${invoice.id}`} onClick={() => onCancel(invoice)}
                            className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 font-medium">
                            <Icons.Cancel /> Cancel at LHDN
                        </button>
                    </MenuItem>
                )}

                {!canSubmit && !canRefresh && !canCancel && (
                    <div className="px-4 py-3 text-xs text-slate-400 italic">
                        {invoice.status === 'draft' ? 'Post invoice to ledger first' : 'No actions available'}
                    </div>
                )}
            </MenuItems>
        </Menu>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function EInvoisIndex({
    auth, invoices = [], kpis = {}, paginator = {}, filters = {}, isConfigured = false, lhdnEnv = 'sandbox',
}) {
    const { current_page = 1, last_page = 1, per_page = 15, total = 0, from = 0, to = 0 } = paginator;
    const { search = '', lhdn_status: lhdnFilter = '', per_page: perPageFilter = 15 } = filters;

    const [searchInput, setSearchInput] = useState(search);

    const applyFilters = (overrides = {}) => {
        router.get(route('e-invois.index'), {
            search:      overrides.search      ?? searchInput,
            lhdn_status: overrides.lhdn_status ?? lhdnFilter,
            per_page:    overrides.per_page    ?? perPageFilter,
            page:        overrides.page        ?? 1,
        }, { preserveState: false });
    };

    const handleSubmit = async (invoice) => {
        const ok = await confirm({
            title:       'Submit to LHDN MyInvois?',
            text:        `Invoice ${invoice.invoice_number} will be submitted to the LHDN MyInvois API.`,
            confirmText: 'Submit',
            icon:        'question',
        });
        if (ok) router.post(route('e-invois.submit', invoice.id));
    };

    const handleRefresh = async (invoice) => {
        const ok = await confirm({
            title:       'Refresh LHDN Status?',
            text:        `Fetch the latest status from LHDN for invoice ${invoice.invoice_number}.`,
            confirmText: 'Refresh',
            icon:        'info',
        });
        if (ok) router.post(route('e-invois.refresh', invoice.id));
    };

    const handleCancel = async (invoice) => {
        const ok = await confirm({
            title:       'Cancel at LHDN?',
            text:        `This will cancel invoice ${invoice.invoice_number} at LHDN MyInvois. This cannot be undone.`,
            confirmText: 'Cancel Document',
            confirmColor: '#dc2626',
            icon:        'warning',
        });
        if (ok) router.post(route('e-invois.cancel', invoice.id), { reason: 'Cancelled by user' });
    };

    const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const fmtAmt  = (v) => `RM ${parseFloat(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div className="flex items-center gap-2 mb-1">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-indigo-100 text-indigo-700">
                            <span className={`w-1.5 h-1.5 rounded-full ${lhdnEnv === 'production' ? 'bg-emerald-500' : 'bg-amber-500'}`} />
                            {lhdnEnv === 'production' ? 'Production' : 'Sandbox'}
                        </span>
                        {isConfigured
                            ? <span className="text-[11px] text-emerald-600 font-semibold">● API Connected</span>
                            : <span className="text-[11px] text-amber-600 font-semibold">⚠ Credentials not set</span>
                        }
                    </div>
                    <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">E-Invois (LHDN MyInvois)</h2>
                    <p className="text-slate-500 text-sm font-medium mt-1">Submit, track and manage e-invoices with Pejabat LHDN Malaysia</p>
                </div>
                <a href="https://myinvois.hasil.gov.my" target="_blank" rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-sm text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                    <Icons.ExternalLink /> MyInvois Portal
                </a>
            </div>
        }>
            <Head title="E-Invois — LHDN MyInvois" />

            <div className="space-y-6">

                {/* Credentials callout */}
                {!isConfigured && (
                    <div className="flex items-start gap-4 p-5 rounded-2xl bg-amber-50 border border-amber-200">
                        <span className="flex-shrink-0 p-2 rounded-xl bg-amber-100 text-amber-600"><Icons.Warning /></span>
                        <div className="min-w-0">
                            <p className="font-bold text-amber-900 text-sm">LHDN API credentials not configured</p>
                            <p className="text-amber-700 text-sm mt-0.5 leading-relaxed">
                                To enable live submissions, add the following to your <code className="bg-amber-100 px-1 rounded">.env</code>:
                            </p>
                            <pre className="mt-3 p-3 bg-amber-900/10 rounded-xl text-xs text-amber-900 font-mono leading-relaxed overflow-x-auto">{`LHDN_ENV=sandbox
LHDN_CLIENT_ID=your-client-id
LHDN_CLIENT_SECRET=your-client-secret`}</pre>
                            <p className="text-amber-700 text-xs mt-2">
                                Register at{' '}
                                <a href="https://mytax.hasil.gov.my/" target="_blank" rel="noopener noreferrer"
                                    className="underline font-semibold">
                                    https://mytax.hasil.gov.my/
                                </a>
                                {' '}→ MyInvois → API Access → Create Application.
                            </p>
                            <div className="mt-3 p-3 bg-white/70 rounded-xl border border-amber-200 text-xs text-amber-800 space-y-1">
                                <p className="font-bold">How to get LHDN credentials:</p>
                                <ol className="list-decimal list-inside space-y-1 text-amber-700">
                                    <li>Log in to <strong>mytax.hasil.gov.my</strong> with your company MyTax account</li>
                                    <li>Navigate to <strong>MyInvois</strong> → <strong>Intermediary</strong> or <strong>Taxpayer</strong> → <strong>API Access</strong></li>
                                    <li>Click <strong>Create New Application</strong> and copy the Client ID &amp; Secret</li>
                                    <li>Use the <strong>sandbox/preprod</strong> portal first for testing</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                )}

                {/* KPI row */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                    <div className="col-span-2 sm:col-span-1 relative overflow-hidden bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-700 text-white rounded-2xl p-5 shadow-lg shadow-indigo-500/25">
                        <p className="text-[10px] font-bold uppercase tracking-widest opacity-80">Total Invoices</p>
                        <p className="text-3xl font-extrabold mt-1 tabular-nums">{kpis.total ?? 0}</p>
                        <span className="absolute right-4 bottom-4 opacity-20"><Icons.Document /></span>
                    </div>
                    <StatCard label="Submitted" value={kpis.submitted ?? 0} icon={Icons.Upload}
                        iconBg="bg-blue-100 text-blue-600" valueColor="text-blue-700"
                        sub="at LHDN" />
                    <StatCard label="Valid" value={kpis.valid ?? 0} icon={Icons.CheckBadge}
                        iconBg="bg-emerald-100 text-emerald-600" valueColor="text-emerald-700"
                        sub="LHDN accepted" />
                    <StatCard label="Pending" value={kpis.pending ?? 0} icon={Icons.Clock}
                        iconBg="bg-amber-100 text-amber-600" valueColor="text-amber-700"
                        sub="not yet submitted" />
                    <StatCard label="Invalid / Cancelled" value={kpis.invalid ?? 0} icon={Icons.Warning}
                        iconBg="bg-rose-100 text-rose-600" valueColor="text-rose-700"
                        sub="requires action" />
                </div>

                {/* Filter bar + table */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    {/* Filters */}
                    <div className="px-4 sm:px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50">
                        <div className="relative flex-1 min-w-[180px] max-w-xs">
                            <span className="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><Icons.Search /></span>
                            <input id="einvois-search" type="text" placeholder="Search invoice # or customer…"
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters({ page: 1 })}
                                onBlur={() => applyFilters({ page: 1 })}
                                className="pl-9 w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500 bg-white" />
                        </div>
                        <select id="einvois-lhdn-filter" value={lhdnFilter}
                            onChange={(e) => applyFilters({ lhdn_status: e.target.value, page: 1 })}
                            className="min-w-[180px] border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500 bg-white">
                            <option value="">All LHDN statuses</option>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="valid">Valid</option>
                            <option value="invalid">Invalid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <select id="einvois-per-page" value={perPageFilter}
                            onChange={(e) => applyFilters({ per_page: Number(e.target.value), page: 1 })}
                            className="w-[200px] flex-shrink-0 border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500 bg-white">
                            <option value={10}>10 / page</option>
                            <option value={15}>15 / page</option>
                            <option value={25}>25 / page</option>
                            <option value={50}>50 / page</option>
                        </select>
                        <span className="ml-auto text-sm text-slate-500 font-medium whitespace-nowrap">
                            {total > 0 ? `${from}–${to} of ${total}` : '0 results'}
                        </span>
                    </div>

                    {/* Desktop table */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full min-w-0">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 bg-slate-50/60">
                                    <th className="px-6 py-3">Invoice</th>
                                    <th className="px-6 py-3">Customer</th>
                                    <th className="px-6 py-3">Invoice Status</th>
                                    <th className="px-6 py-3">LHDN Status</th>
                                    <th className="px-6 py-3">Submitted</th>
                                    <th className="px-6 py-3 text-right">Amount</th>
                                    <th className="px-6 py-3 text-right w-24">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.length > 0 ? invoices.map((inv) => (
                                    <tr key={inv.id}
                                        className={`border-b border-slate-50 last:border-0 hover:bg-indigo-50/30 transition-colors ${inv.status === 'void' ? 'opacity-50' : ''}`}>
                                        <td className="px-6 py-4">
                                            <Link href={route('invoices.edit', inv.id)} className="group/lnk">
                                                <span className="font-bold text-slate-800 group-hover/lnk:text-indigo-600 text-sm">{inv.invoice_number}</span>
                                                <p className="text-xs text-slate-400 mt-0.5">{fmtDate(inv.issue_date)}</p>
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm font-medium text-slate-700">{inv.customer_name || 'Walk-in'}</div>
                                            {inv.customer_tin && <p className="text-xs text-slate-400">TIN: {inv.customer_tin}</p>}
                                        </td>
                                        <td className="px-6 py-4">
                                            <InvoiceStatusBadge status={inv.status} />
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex flex-col gap-1">
                                                <LhdnBadge status={inv.lhdn_status} />
                                                {inv.lhdn_long_id && (
                                                    <span className="text-[10px] text-slate-400 font-mono truncate max-w-[140px]" title={inv.lhdn_long_id}>
                                                        {inv.lhdn_long_id}
                                                    </span>
                                                )}
                                                {inv.lhdn_error_message && (
                                                    <span className="text-[10px] text-rose-500 font-medium leading-snug" title={inv.lhdn_error_message}>
                                                        ⚠ {inv.lhdn_error_message.slice(0, 60)}{inv.lhdn_error_message.length > 60 ? '…' : ''}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-500">
                                            {inv.lhdn_submitted_at ? (
                                                <div className="flex items-center gap-1.5">
                                                    <Icons.Clock />
                                                    <span>{fmtDate(inv.lhdn_submitted_at)}</span>
                                                </div>
                                            ) : '—'}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="font-mono font-semibold text-slate-800 text-sm">{fmtAmt(inv.total_amount)}</div>
                                            {parseFloat(inv.tax_amount) > 0 && (
                                                <p className="text-xs text-slate-400">Tax: {fmtAmt(inv.tax_amount)}</p>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <ActionsCell invoice={inv}
                                                onSubmit={handleSubmit}
                                                onRefresh={handleRefresh}
                                                onCancel={handleCancel} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-20 text-center">
                                            <div className="flex flex-col items-center gap-3">
                                                <span className="p-4 rounded-2xl bg-indigo-50 text-indigo-300"><Icons.Shield /></span>
                                                <p className="text-slate-400 font-medium">No invoices found</p>
                                                <p className="text-slate-400 text-sm">Adjust your filters or create an invoice first</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile cards */}
                    <div className="md:hidden divide-y divide-slate-100">
                        {invoices.length > 0 ? invoices.map((inv) => (
                            <div key={inv.id} className={`p-4 ${inv.status === 'void' ? 'opacity-50' : ''}`}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('invoices.edit', inv.id)}
                                            className="font-bold text-slate-800 hover:text-indigo-600 text-sm">
                                            {inv.invoice_number}
                                        </Link>
                                        <p className="text-xs text-slate-500 mt-0.5">{inv.customer_name || 'Walk-in'}</p>
                                        <p className="text-sm font-mono font-semibold text-slate-800 mt-1">{fmtAmt(inv.total_amount)}</p>
                                        <div className="flex flex-wrap items-center gap-2 mt-2">
                                            <InvoiceStatusBadge status={inv.status} />
                                            <LhdnBadge status={inv.lhdn_status} />
                                        </div>
                                        {inv.lhdn_error_message && (
                                            <p className="text-xs text-rose-500 font-medium mt-1">⚠ {inv.lhdn_error_message.slice(0, 80)}</p>
                                        )}
                                    </div>
                                    <ActionsCell invoice={inv}
                                        onSubmit={handleSubmit}
                                        onRefresh={handleRefresh}
                                        onCancel={handleCancel} />
                                </div>
                            </div>
                        )) : (
                            <div className="px-4 py-16 text-center text-slate-400 text-sm">No invoices found.</div>
                        )}
                    </div>

                    {/* Pagination */}
                    {last_page > 1 && (
                        <div className="px-4 sm:px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/30">
                            <p className="text-sm text-slate-600">Page {current_page} of {last_page}</p>
                            <div className="flex items-center gap-2">
                                <Link href={route('e-invois.index', {
                                    search: searchInput || undefined, lhdn_status: lhdnFilter || undefined,
                                    per_page: perPageFilter, page: Math.max(1, current_page - 1),
                                })} className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${current_page <= 1 ? 'pointer-events-none text-slate-300 border-slate-200' : 'text-slate-700 border-slate-200 hover:bg-slate-50'}`}>
                                    <Icons.ChevLeft /> Previous
                                </Link>
                                <Link href={route('e-invois.index', {
                                    search: searchInput || undefined, lhdn_status: lhdnFilter || undefined,
                                    per_page: perPageFilter, page: Math.min(last_page, current_page + 1),
                                })} className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${current_page >= last_page ? 'pointer-events-none text-slate-300 border-slate-200' : 'text-slate-700 border-slate-200 hover:bg-slate-50'}`}>
                                    Next <Icons.ChevRight />
                                </Link>
                            </div>
                        </div>
                    )}
                </div>

                {/* Info footer */}
                <div className="flex flex-wrap gap-4 text-xs text-slate-400">
                    <span>LHDN MyInvois API v1.1</span>
                    <span>·</span>
                    <a href="https://sdk.myinvois.hasil.gov.my" target="_blank" rel="noopener noreferrer"
                        className="hover:text-indigo-600 underline underline-offset-2">API Documentation</a>
                    <span>·</span>
                    <span>Only posted invoices (non-draft) can be submitted to LHDN</span>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
