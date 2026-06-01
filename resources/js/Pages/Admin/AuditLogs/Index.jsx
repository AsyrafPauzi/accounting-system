import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

const eventColors = {
    created:      'bg-forest/10 text-forest',
    updated:      'bg-surface-alt text-terracotta',
    deleted:      'bg-terracotta/10 text-terracotta',
    soft_deleted: 'bg-mustard/15 text-mustard',
    restored:     'bg-surface-alt text-terracotta',
};

function DiffViewer({ old: oldValues, newVals }) {
    if (!oldValues && !newVals) return <span className="text-ink-muted italic text-xs">—</span>;

    const allKeys = [...new Set([
        ...Object.keys(oldValues ?? {}),
        ...Object.keys(newVals ?? {}),
    ])];

    if (allKeys.length === 0) return <span className="text-ink-muted italic text-xs">—</span>;

    return (
        <div className="text-[10px] font-mono space-y-0.5 max-h-24 overflow-y-auto">
            {allKeys.map((key) => {
                const oldVal = oldValues?.[key];
                const newVal = newVals?.[key];
                const changed = JSON.stringify(oldVal) !== JSON.stringify(newVal);
                return (
                    <div key={key} className={`flex gap-2 ${changed ? 'text-mustard' : 'text-ink-muted'}`}>
                        <span className="text-ink-muted shrink-0">{key}:</span>
                        {changed ? (
                            <span>
                                <span className="line-through text-terracotta">{String(oldVal ?? '—')}</span>
                                <span className="ml-1 text-forest">{String(newVal ?? '—')}</span>
                            </span>
                        ) : (
                            <span>{String(newVal ?? oldVal ?? '—')}</span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export default function AdminAuditLogsIndex({ auth, logs, filters = {}, modelOptions = [] }) {
    const [localFilters, setLocalFilters] = useState(filters);

    const applyFilters = (newFilters) => {
        const merged = { ...localFilters, ...newFilters };
        setLocalFilters(merged);
        router.get(route('admin.audit-logs.index'), merged, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setLocalFilters({});
        router.get(route('admin.audit-logs.index'), {}, { replace: true });
    };

    const { data: pageData } = logs;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Admin</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Platform audit log</h1>
                    <p className="text-ink-muted text-sm">
                        Every change to subscriptions, plans, users, and tenants.
                    </p>
                </div>
            }
        >
            <Head title="Platform Audit Log" />

            {/* Filters */}
            <div className="mb-4 bg-surface rounded-2xl border border-border-warm shadow-sm p-4 flex flex-wrap gap-3 items-end">
                <div>
                    <label className="block text-xs font-semibold text-ink mb-1">Model</label>
                    <select
                        value={localFilters.model ?? ''}
                        onChange={(e) => applyFilters({ model: e.target.value || undefined })}
                        className="border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                    >
                        <option value="">All models</option>
                        {modelOptions.map((m) => (
                            <option key={m.value} value={m.value}>{m.label}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-semibold text-ink mb-1">Event</label>
                    <select
                        value={localFilters.event ?? ''}
                        onChange={(e) => applyFilters({ event: e.target.value || undefined })}
                        className="border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                    >
                        <option value="">All events</option>
                        {['created', 'updated', 'deleted', 'soft_deleted', 'restored'].map((ev) => (
                            <option key={ev} value={ev}>{ev}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-semibold text-ink mb-1">From</label>
                    <input type="date" value={localFilters.from ?? ''}
                        onChange={(e) => applyFilters({ from: e.target.value || undefined })}
                        className="border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                    />
                </div>
                <div>
                    <label className="block text-xs font-semibold text-ink mb-1">To</label>
                    <input type="date" value={localFilters.to ?? ''}
                        onChange={(e) => applyFilters({ to: e.target.value || undefined })}
                        className="border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                    />
                </div>
                {Object.values(localFilters).some(Boolean) && (
                    <button type="button" onClick={clearFilters} className="px-3 py-2 rounded-xl text-xs font-semibold text-ink bg-surface-alt hover:bg-surface-alt self-end">
                        Clear filters
                    </button>
                )}
            </div>

            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-border-warm bg-cream/80">
                    <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">
                        Entries <span className="ml-2 text-ink-muted font-normal normal-case">({logs.total})</span>
                    </h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest bg-cream/80">
                                <th className="px-6 py-4">When</th>
                                <th className="px-6 py-4">User</th>
                                <th className="px-6 py-4">Event</th>
                                <th className="px-6 py-4">Model</th>
                                <th className="px-6 py-4">Changes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pageData.length === 0 && (
                                <tr><td colSpan={5} className="px-6 py-8 text-center text-ink-muted text-sm">No audit log entries found.</td></tr>
                            )}
                            {pageData.map((log) => (
                                <tr key={log.id} className="border-b border-border-warm hover:bg-cream/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="text-xs text-ink font-mono block">{new Date(log.created_at).toLocaleDateString('en-MY')}</span>
                                        <span className="text-[10px] text-ink-muted">{log.created_at_human}</span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="text-xs font-semibold text-ink">{log.user_name}</span>
                                        {log.user_id && <span className="block text-[10px] text-ink-muted">#{log.user_id}</span>}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${eventColors[log.event] ?? 'bg-surface-alt text-ink'}`}>
                                            {log.event}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="text-xs font-semibold text-ink">{log.auditable_type}</span>
                                        <span className="block font-mono text-[10px] text-ink-muted">#{log.auditable_id}</span>
                                    </td>
                                    <td className="px-6 py-4 max-w-xs">
                                        <DiffViewer old={log.old_values} newVals={log.new_values} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="px-6 py-4 border-t border-border-warm flex items-center justify-between text-xs text-ink-muted">
                        <span>Page {logs.current_page} of {logs.last_page}</span>
                        <div className="flex gap-2">
                            {logs.prev_page_url && (
                                <a href={logs.prev_page_url} className="px-3 py-1.5 rounded-lg bg-surface-alt hover:bg-surface-alt font-semibold">Previous</a>
                            )}
                            {logs.next_page_url && (
                                <a href={logs.next_page_url} className="px-3 py-1.5 rounded-lg bg-surface-alt hover:bg-surface-alt font-semibold">Next</a>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
