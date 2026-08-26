import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const statusStyles = {
    submitted: 'bg-mustard/15 text-mustard border-mustard/40',
    accepted: 'bg-forest/10 text-forest border-forest/30',
    rejected: 'bg-terracotta/10 text-terracotta border-terracotta/30',
    error: 'bg-terracotta/10 text-terracotta border-terracotta/30',
};

function formatType(type) {
    return String(type || '').replace(/_/g, ' ');
}

export default function Submissions({ auth, submissions, selected = null, filters = {} }) {
    const [localFilters, setLocalFilters] = useState({
        status: filters.status || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(route('myinvois.submissions.index'), localFilters, { preserveState: true });
    };

    const clearFilters = () => {
        setLocalFilters({ status: '', date_from: '', date_to: '' });
        router.get(route('myinvois.submissions.index'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">MyInvois</p>
                        <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Submission vault</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Read-only audit trail of every LHDN submit attempt</p>
                    </div>
                    <div className="flex gap-2 text-sm">
                        <Link href={route('myinvois.consolidated.index')} className="px-3 py-2 rounded-xl border border-border-warm text-ink-muted hover:text-ink">
                            Consolidated
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="MyInvois submissions" />

            {selected ? (
                <div className="space-y-4 max-w-5xl">
                    <Link href={route('myinvois.submissions.index')} className="text-sm text-terracotta font-semibold hover:underline">
                        ← Back to list
                    </Link>
                    <div className="bg-surface rounded-2xl border p-4 sm:p-6 space-y-3">
                        <div className="flex flex-wrap gap-3 text-sm">
                            <span className={`px-2 py-0.5 rounded-full border text-xs font-semibold uppercase ${statusStyles[selected.status] || statusStyles.error}`}>
                                {selected.status}
                            </span>
                            <span className="text-ink-muted">{formatType(selected.document_type)} #{selected.document_id}</span>
                            {selected.http_status && <span className="text-ink-muted">HTTP {selected.http_status}</span>}
                            {selected.lhdn_uuid && <span className="font-mono text-xs text-ink-muted break-all">{selected.lhdn_uuid}</span>}
                        </div>
                        <p className="text-xs text-ink-muted">
                            Submitted {selected.submitted_at ? new Date(selected.submitted_at).toLocaleString() : '—'}
                        </p>
                    </div>
                    <div className="grid lg:grid-cols-2 gap-4">
                        <section className="bg-surface rounded-2xl border overflow-hidden">
                            <header className="px-4 py-3 border-b text-sm font-semibold text-ink">Request JSON</header>
                            <pre className="p-4 text-xs overflow-auto max-h-[32rem] bg-cream/40 text-ink-muted">{JSON.stringify(selected.request_json, null, 2)}</pre>
                        </section>
                        <section className="bg-surface rounded-2xl border overflow-hidden">
                            <header className="px-4 py-3 border-b text-sm font-semibold text-ink">Response JSON</header>
                            <pre className="p-4 text-xs overflow-auto max-h-[32rem] bg-cream/40 text-ink-muted">
                                {selected.response_json ? JSON.stringify(selected.response_json, null, 2) : '—'}
                            </pre>
                        </section>
                    </div>
                </div>
            ) : (
                <div className="space-y-4">
                    <form onSubmit={applyFilters} className="flex flex-wrap gap-3 items-end bg-surface rounded-2xl border p-4">
                        <label className="text-sm">
                            <span className="block text-ink-muted mb-1">Status</span>
                            <select
                                value={localFilters.status}
                                onChange={(e) => setLocalFilters((f) => ({ ...f, status: e.target.value }))}
                                className="border rounded-xl px-3 py-2 text-sm min-w-[9rem]"
                            >
                                <option value="">All</option>
                                <option value="submitted">Submitted</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                                <option value="error">Error</option>
                            </select>
                        </label>
                        <label className="text-sm">
                            <span className="block text-ink-muted mb-1">From</span>
                            <input type="date" value={localFilters.date_from} onChange={(e) => setLocalFilters((f) => ({ ...f, date_from: e.target.value }))} className="border rounded-xl px-3 py-2 text-sm" />
                        </label>
                        <label className="text-sm">
                            <span className="block text-ink-muted mb-1">To</span>
                            <input type="date" value={localFilters.date_to} onChange={(e) => setLocalFilters((f) => ({ ...f, date_to: e.target.value }))} className="border rounded-xl px-3 py-2 text-sm" />
                        </label>
                        <button type="submit" className="px-4 py-2 rounded-xl bg-terracotta text-white text-sm font-semibold">Filter</button>
                        <button type="button" onClick={clearFilters} className="px-4 py-2 rounded-xl border text-sm text-ink-muted">Clear</button>
                    </form>

                    <div className="bg-surface rounded-2xl border overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-cream/50 text-ink-muted text-left">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">When</th>
                                    <th className="px-4 py-3 font-semibold">Document</th>
                                    <th className="px-4 py-3 font-semibold">Status</th>
                                    <th className="px-4 py-3 font-semibold hidden sm:table-cell">LHDN UUID</th>
                                    <th className="px-4 py-3 font-semibold w-20" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {(submissions?.data || []).map((row) => (
                                    <tr key={row.id} className="hover:bg-cream/30">
                                        <td className="px-4 py-3 text-ink-muted whitespace-nowrap">
                                            {row.submitted_at ? new Date(row.submitted_at).toLocaleString() : '—'}
                                        </td>
                                        <td className="px-4 py-3 capitalize">{formatType(row.document_type)} #{row.document_id}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-full border text-xs font-semibold uppercase ${statusStyles[row.status] || statusStyles.error}`}>
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-ink-muted hidden sm:table-cell truncate max-w-[12rem]">
                                            {row.lhdn_uuid || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={route('myinvois.submissions.show', row.id)} className="text-terracotta font-semibold hover:underline">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {(submissions?.data || []).length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-10 text-center text-ink-muted italic">
                                            No submissions yet. Submit an e-invoice to populate the vault.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {submissions?.links?.length > 3 && (
                        <div className="flex flex-wrap gap-2 text-sm">
                            {submissions.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`px-3 py-1 rounded-lg border ${link.active ? 'bg-terracotta text-white border-terracotta' : 'text-ink-muted'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 text-ink-muted/50" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
