import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import React, { useState } from 'react';

const Icons = {
    History: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    User: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>,
    Tag: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>,
    Activity: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>,
};

export default function Index({ auth, logs }) {
    const [selectedLog, setSelectedLog] = useState(null);

    const getEventStyles = (event) => {
        switch (event.toLowerCase()) {
            case 'created': return 'bg-forest/10 text-forest border-forest/30';
            case 'updated': return 'bg-mustard/15 text-mustard border-mustard/40';
            case 'deleted': return 'bg-terracotta/10 text-terracotta border-terracotta/30';
            case 'soft_deleted': return 'bg-terracotta/10 text-terracotta border-terracotta/30 opacity-75';
            case 'restored': return 'bg-surface-alt text-terracotta border-border-warm';
            case 'voided': return 'bg-surface-alt text-ink border-border-warm';
            case 'posted': return 'bg-surface-alt text-terracotta border-border-warm';
            default: return 'bg-cream text-ink border-border-warm';
        }
    };

    const formatValue = (val) => {
        if (val === null) return <span className="text-ink-muted italic">null</span>;
        if (typeof val === 'boolean') return <span className={val ? 'text-forest font-bold' : 'text-terracotta font-bold'}>{val ? 'TRUE' : 'FALSE'}</span>;
        
        // Detect and format date strings (ISO or space-separated)
        if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/.test(val)) {
            const date = new Date(val.replace(' ', 'T')); // Ensure T for reliable parsing
            if (!isNaN(date.getTime())) {
                return (
                    <span className="text-terracotta font-semibold tracking-tight">
                        {date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })} {date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                );
            }
        }

        if (typeof val === 'object') return JSON.stringify(val);
        return String(val);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Audit</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Activity log</h1>
                        <p className="text-ink-muted text-sm">Every change in your books, who made it and when.</p>
                    </div>
                </div>
            }
        >
            <Head title="Audit Logs" />

            <div className="max-w-7xl mx-auto space-y-6 pb-20 mt-6">
                <div className="bg-surface/80 backdrop-blur-md rounded-3xl border border-border-warm/60 shadow-xl  overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border-warm/60">
                            <thead>
                                <tr className="bg-cream/80 text-left text-[11px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                    <th className="px-8 py-5">Timestamp</th>
                                    <th className="px-8 py-5">User</th>
                                    <th className="px-8 py-5">Action</th>
                                    <th className="px-8 py-5">Resource</th>
                                    <th className="px-8 py-5 text-right">Activity Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm/60 bg-surface">
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-surface-alt/30 transition-all duration-200 group">
                                        <td className="px-8 py-5 whitespace-nowrap">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-display font-medium text-ink">
                                                    {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                                <span className="text-[10px] font-display font-medium text-ink-muted uppercase tracking-tighter">
                                                    {new Date(log.created_at).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 rounded-xl bg-terracotta text-white flex items-center justify-center shadow-lg ">
                                                    <Icons.User />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-display font-medium text-ink">{log.user_name}</span>
                                                    <span className="text-[10px] text-ink-muted font-semibold uppercase tracking-wider">{log.user_role}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 whitespace-nowrap text-sm">
                                            <span className={`px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest border ${getEventStyles(log.event)}`}>
                                                {log.event.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-8 py-5 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="p-2 rounded-lg bg-surface-alt text-ink-muted">
                                                    <Icons.Tag />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-xs font-display font-medium text-ink uppercase tracking-tight">{log.auditable_type}</span>
                                                    <span className="text-[10px] text-ink-muted font-mono bg-cream px-1.5 py-0.5 rounded border border-border-warm">UUID: {log.auditable_id}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 whitespace-nowrap text-right">
                                            <button 
                                                onClick={() => setSelectedLog(log)}
                                                className="inline-flex items-center gap-2 text-xs font-black text-terracotta hover:text-white px-5 py-2.5 rounded-2xl bg-surface-alt hover:bg-terracotta transition-all duration-300 border border-border-warm active:scale-95"
                                            >
                                                <Icons.Activity />
                                                Inspect Changes
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {/* Empty State */}
                    {logs.data.length === 0 && (
                        <div className="py-20 text-center flex flex-col items-center justify-center">
                            <div className="w-16 h-16 bg-cream rounded-full flex items-center justify-center text-ink-muted mb-4">
                                <Icons.History />
                            </div>
                            <h3 className="text-ink font-bold">No logs found</h3>
                            <p className="text-ink-muted text-sm">System activity will appear here as updates occur.</p>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {logs.links && logs.links.length > 3 && (
                    <div className="flex items-center justify-center gap-2 pt-4">
                        {logs.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && (window.location.href = link.url)}
                                className={`px-4 py-2 rounded-xl text-xs font-bold transition-all ${
                                    link.active 
                                    ? 'bg-terracotta text-white shadow-lg ' 
                                    : 'bg-surface text-ink hover:bg-cream border border-border-warm'
                                } ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Change Detail Modal */}
            {selectedLog && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-ink/80 backdrop-blur-md animate-in fade-in duration-300">
                    <div className="bg-surface rounded-[2.5rem] w-full max-w-4xl shadow-[0_0_100px_rgba(0,0,0,0.2)] overflow-hidden animate-in slide-in-from-bottom-8 duration-500 ease-out">
                        <div className="px-10 py-8 border-b border-border-warm flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                            <div>
                                <div className="flex items-center gap-3 mb-1">
                                    <span className="px-3 py-1 rounded-full bg-terracotta text-white text-[10px] font-black uppercase tracking-tighter">Activity Detail</span>
                                    <h3 className="text-xl font-display font-semibold text-ink uppercase tracking-tight">System Inspection</h3>
                                </div>
                                <p className="text-xs text-ink-muted font-medium">
                                    {selectedLog.auditable_type} <span className="font-mono text-terracotta">#{selectedLog.auditable_id}</span> &bull; Actioned by <span className="font-display font-medium text-ink">{selectedLog.user_name}</span>
                                </p>
                            </div>
                            <button onClick={() => setSelectedLog(null)} className="p-3 bg-surface-alt hover:bg-terracotta/10 rounded-2xl transition-all duration-300 text-ink-muted hover:text-terracotta group">
                                <svg className="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <div className="p-10 max-h-[65vh] overflow-y-auto custom-scrollbar">
                            {(!selectedLog.old_values && !selectedLog.new_values) ? (
                                <div className="text-center py-10">
                                    <p className="text-ink-muted italic">No specific property changes recorded for this event.</p>
                                </div>
                            ) : (
                                <div className="space-y-8">
                                    {/* Diff View */}
                                    <div className="bg-cream rounded-[2rem] border border-border-warm overflow-hidden">
                                        <table className="w-full text-left border-collapse">
                                            <thead>
                                                <tr className="bg-surface-alt/50">
                                                    <th className="px-6 py-4 text-[10px] font-display font-semibold text-ink-muted uppercase tracking-widest w-1/3">Property</th>
                                                    <th className="px-6 py-4 text-[10px] font-black text-terracotta uppercase tracking-widest w-1/3">Previous State</th>
                                                    <th className="px-6 py-4 text-[10px] font-black text-forest uppercase tracking-widest w-1/3">New State</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border-warm">
                                                {Object.keys({...(selectedLog.old_values || {}), ...(selectedLog.new_values || {})}).map(key => (
                                                    <tr key={key} className="group hover:bg-surface transition-colors">
                                                        <td className="px-6 py-4">
                                                            <span className="text-xs font-display font-medium text-ink uppercase tracking-tighter bg-surface-alt/50 group-hover:bg-surface-alt px-2 py-1 rounded-md transition-colors">{key.replace('_', ' ')}</span>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="text-xs font-mono text-terracotta break-all leading-relaxed line-through decoration-rose-200">
                                                                {formatValue(selectedLog.old_values?.[key])}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 bg-forest/10/20 group-hover:bg-forest/10/40">
                                                            <div className="text-xs font-mono text-forest font-bold break-all leading-relaxed">
                                                                {formatValue(selectedLog.new_values?.[key])}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="px-10 py-8 bg-cream border-t border-border-warm flex items-center justify-between">
                            <div className="flex items-center gap-2 text-ink-muted">
                                <Icons.History />
                                <span className="text-[10px] font-bold uppercase tracking-widest">Logged on {selectedLog.created_at_human}</span>
                            </div>
                            <button 
                                onClick={() => setSelectedLog(null)}
                                className="px-10 py-4 bg-ink text-white text-xs font-black rounded-2xl hover:bg-ink transition-all active:scale-95 shadow-lg "
                            >
                                CLOSE INSPECTION
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <style dangerouslySetInnerHTML={{ __html: `
                .custom-scrollbar::-webkit-scrollbar {
                    width: 6px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #e2e8f0;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #cbd5e1;
                }
            `}} />
        </AuthenticatedLayout>
    );
}

