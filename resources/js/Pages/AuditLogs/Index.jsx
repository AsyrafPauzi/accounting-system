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
            case 'created': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
            case 'updated': return 'bg-amber-50 text-amber-700 border-amber-100';
            case 'deleted': return 'bg-rose-50 text-rose-700 border-rose-100';
            case 'soft_deleted': return 'bg-rose-50 text-rose-700 border-rose-100 opacity-75';
            case 'restored': return 'bg-indigo-50 text-indigo-700 border-indigo-100';
            case 'voided': return 'bg-slate-100 text-slate-700 border-slate-200';
            case 'posted': return 'bg-blue-50 text-blue-700 border-blue-100';
            default: return 'bg-gray-50 text-gray-700 border-gray-100';
        }
    };

    const formatValue = (val) => {
        if (val === null) return <span className="text-slate-400 italic">null</span>;
        if (typeof val === 'boolean') return <span className={val ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'}>{val ? 'TRUE' : 'FALSE'}</span>;
        
        // Detect and format date strings (ISO or space-separated)
        if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/.test(val)) {
            const date = new Date(val.replace(' ', 'T')); // Ensure T for reliable parsing
            if (!isNaN(date.getTime())) {
                return (
                    <span className="text-blue-600 font-semibold tracking-tight">
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
                        <h2 className="text-3xl font-bold text-slate-900 tracking-tight">Audit Logs</h2>
                        <div className="flex items-center gap-2">
                            <p className="text-slate-500 text-sm font-medium">Comprehensive system activity tracking</p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Audit Logs" />

            <div className="max-w-7xl mx-auto space-y-6 pb-20 mt-6">
                <div className="bg-white/80 backdrop-blur-md rounded-3xl border border-slate-200/60 shadow-xl shadow-slate-200/40 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100/60">
                            <thead>
                                <tr className="bg-slate-50/80 text-left text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                                    <th className="px-8 py-5">Timestamp</th>
                                    <th className="px-8 py-5">User</th>
                                    <th className="px-8 py-5">Action</th>
                                    <th className="px-8 py-5">Resource</th>
                                    <th className="px-8 py-5 text-right">Activity Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100/60 bg-white">
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-blue-50/30 transition-all duration-200 group">
                                        <td className="px-8 py-5 whitespace-nowrap">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-bold text-slate-800">
                                                    {new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">
                                                    {new Date(log.created_at).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-blue-200/50">
                                                    <Icons.User />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-bold text-slate-700">{log.user_name}</span>
                                                    <span className="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">{log.user_role}</span>
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
                                                <div className="p-2 rounded-lg bg-slate-100 text-slate-500">
                                                    <Icons.Tag />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-xs font-bold text-slate-600 uppercase tracking-tight">{log.auditable_type}</span>
                                                    <span className="text-[10px] text-slate-400 font-mono bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">UUID: {log.auditable_id}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 whitespace-nowrap text-right">
                                            <button 
                                                onClick={() => setSelectedLog(log)}
                                                className="inline-flex items-center gap-2 text-xs font-black text-blue-600 hover:text-white px-5 py-2.5 rounded-2xl bg-blue-50 hover:bg-blue-600 transition-all duration-300 border border-blue-100 active:scale-95"
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
                            <div className="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 mb-4">
                                <Icons.History />
                            </div>
                            <h3 className="text-slate-900 font-bold">No logs found</h3>
                            <p className="text-slate-500 text-sm">System activity will appear here as updates occur.</p>
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
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' 
                                    : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
                                } ${!link.url ? 'opacity-30 cursor-not-allowed' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Change Detail Modal */}
            {selectedLog && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-300">
                    <div className="bg-white rounded-[2.5rem] w-full max-w-4xl shadow-[0_0_100px_rgba(0,0,0,0.2)] overflow-hidden animate-in slide-in-from-bottom-8 duration-500 ease-out">
                        <div className="px-10 py-8 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                            <div>
                                <div className="flex items-center gap-3 mb-1">
                                    <span className="px-3 py-1 rounded-full bg-blue-600 text-white text-[10px] font-black uppercase tracking-tighter">Activity Detail</span>
                                    <h3 className="text-xl font-black text-slate-900 uppercase tracking-tight">System Inspection</h3>
                                </div>
                                <p className="text-xs text-slate-500 font-medium">
                                    {selectedLog.auditable_type} <span className="font-mono text-blue-600">#{selectedLog.auditable_id}</span> &bull; Actioned by <span className="font-bold text-slate-700">{selectedLog.user_name}</span>
                                </p>
                            </div>
                            <button onClick={() => setSelectedLog(null)} className="p-3 bg-slate-100 hover:bg-rose-50 rounded-2xl transition-all duration-300 text-slate-400 hover:text-rose-600 group">
                                <svg className="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <div className="p-10 max-h-[65vh] overflow-y-auto custom-scrollbar">
                            {(!selectedLog.old_values && !selectedLog.new_values) ? (
                                <div className="text-center py-10">
                                    <p className="text-slate-400 italic">No specific property changes recorded for this event.</p>
                                </div>
                            ) : (
                                <div className="space-y-8">
                                    {/* Diff View */}
                                    <div className="bg-slate-50 rounded-[2rem] border border-slate-100 overflow-hidden">
                                        <table className="w-full text-left border-collapse">
                                            <thead>
                                                <tr className="bg-slate-100/50">
                                                    <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/3">Property</th>
                                                    <th className="px-6 py-4 text-[10px] font-black text-rose-400 uppercase tracking-widest w-1/3">Previous State</th>
                                                    <th className="px-6 py-4 text-[10px] font-black text-emerald-500 uppercase tracking-widest w-1/3">New State</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {Object.keys({...(selectedLog.old_values || {}), ...(selectedLog.new_values || {})}).map(key => (
                                                    <tr key={key} className="group hover:bg-white transition-colors">
                                                        <td className="px-6 py-4">
                                                            <span className="text-xs font-bold text-slate-600 uppercase tracking-tighter bg-slate-200/50 group-hover:bg-blue-50 px-2 py-1 rounded-md transition-colors">{key.replace('_', ' ')}</span>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="text-xs font-mono text-rose-500 break-all leading-relaxed line-through decoration-rose-200">
                                                                {formatValue(selectedLog.old_values?.[key])}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 bg-emerald-50/20 group-hover:bg-emerald-50/40">
                                                            <div className="text-xs font-mono text-emerald-700 font-bold break-all leading-relaxed">
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

                        <div className="px-10 py-8 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                            <div className="flex items-center gap-2 text-slate-400">
                                <Icons.History />
                                <span className="text-[10px] font-bold uppercase tracking-widest">Logged on {selectedLog.created_at_human}</span>
                            </div>
                            <button 
                                onClick={() => setSelectedLog(null)}
                                className="px-10 py-4 bg-slate-900 text-white text-xs font-black rounded-2xl hover:bg-slate-800 transition-all active:scale-95 shadow-lg shadow-slate-200"
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

