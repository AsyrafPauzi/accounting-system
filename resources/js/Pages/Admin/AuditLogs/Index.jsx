import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

const eventColors = {
    created:      'bg-emerald-100 text-emerald-700',
    updated:      'bg-blue-100 text-blue-700',
    deleted:      'bg-rose-100 text-rose-700',
    soft_deleted: 'bg-amber-100 text-amber-700',
    restored:     'bg-violet-100 text-violet-700',
};

function DiffViewer({ old: oldValues, newVals }) {
    if (!oldValues && !newVals) return <span className="text-slate-400 italic text-xs">—</span>;

    const allKeys = [...new Set([
        ...Object.keys(oldValues ?? {}),
        ...Object.keys(newVals ?? {}),
    ])];

    if (allKeys.length === 0) return <span className="text-slate-400 italic text-xs">—</span>;

    return (
        <div className="text-[10px] font-mono space-y-0.5 max-h-24 overflow-y-auto">
            {allKeys.map((key) => {
                const oldVal = oldValues?.[key];
                const newVal = newVals?.[key];
                const changed = JSON.stringify(oldVal) !== JSON.stringify(newVal);
                return (
                    <div key={key} className={`flex gap-2 ${changed ? 'text-amber-700' : 'text-slate-500'}`}>
                        <span className="text-slate-400 shrink-0">{key}:</span>
                        {changed ? (
                            <span>
                                <span className="line-through text-rose-500">{String(oldVal ?? '—')}</span>
                                <span className="ml-1 text-emerald-600">{String(newVal ?? '—')}</span>
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
                <div>
                    <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Platform Audit Log</h2>
                    <p className="text-slate-500 text-sm font-medium mt-1">
                        All changes to subscriptions, plans, users, and tenants.
                    </p>
                </div>
            }
        >
            <Head title="Platform Audit Log" />

            {/* Filters */}
            <div className="mb-4 bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex flex-wrap gap-3 items-end">
                <div>
                    <label className="block text-xs font-semibold text-slate-600 mb-1">Model</label>
                    <select
                        value={localFilters.model ?? ''}
                        onChange={(e) => applyFilters({ model: e.target.value || undefined })}
                        className="border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        <option value="">All models</option>
                        {modelOptions.map((m) => (
                            <option key={m.value} value={m.value}>{m.label}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-semibold text-slate-600 mb-1">Event</label>
                    <select
                        value={localFilters.event ?? ''}
                        onChange={(e) => applyFilters({ event: e.target.value || undefined })}
                        className="border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        <option value="">All events</option>
                        {['created', 'updated', 'deleted', 'soft_deleted', 'restored'].map((ev) => (
                            <option key={ev} value={ev}>{ev}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-semibold text-slate-600 mb-1">From</label>
                    <input type="date" value={localFilters.from ?? ''}
                        onChange={(e) => applyFilters({ from: e.target.value || undefined })}
                        className="border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    />
                </div>
                <div>
                    <label className="block text-xs font-semibold text-slate-600 mb-1">To</label>
                    <input type="date" value={localFilters.to ?? ''}
                        onChange={(e) => applyFilters({ to: e.target.value || undefined })}
                        className="border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    />
                </div>
                {Object.values(localFilters).some(Boolean) && (
                    <button type="button" onClick={clearFilters} className="px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 self-end">
                        Clear filters
                    </button>
                )}
            </div>

            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                    <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">
                        Entries <span className="ml-2 text-slate-400 font-normal normal-case">({logs.total})</span>
                    </h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-50/80">
                                <th className="px-6 py-4">When</th>
                                <th className="px-6 py-4">User</th>
                                <th className="px-6 py-4">Event</th>
                                <th className="px-6 py-4">Model</th>
                                <th className="px-6 py-4">Changes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pageData.length === 0 && (
                                <tr><td colSpan={5} className="px-6 py-8 text-center text-slate-400 text-sm">No audit log entries found.</td></tr>
                            )}
                            {pageData.map((log) => (
                                <tr key={log.id} className="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="text-xs text-slate-700 font-mono block">{new Date(log.created_at).toLocaleDateString('en-MY')}</span>
                                        <span className="text-[10px] text-slate-400">{log.created_at_human}</span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="text-xs font-semibold text-slate-700">{log.user_name}</span>
                                        {log.user_id && <span className="block text-[10px] text-slate-400">#{log.user_id}</span>}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${eventColors[log.event] ?? 'bg-slate-100 text-slate-600'}`}>
                                            {log.event}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="text-xs font-semibold text-slate-700">{log.auditable_type}</span>
                                        <span className="block font-mono text-[10px] text-slate-400">#{log.auditable_id}</span>
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
                    <div className="px-6 py-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                        <span>Page {logs.current_page} of {logs.last_page}</span>
                        <div className="flex gap-2">
                            {logs.prev_page_url && (
                                <a href={logs.prev_page_url} className="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-semibold">Previous</a>
                            )}
                            {logs.next_page_url && (
                                <a href={logs.next_page_url} className="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-semibold">Next</a>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
