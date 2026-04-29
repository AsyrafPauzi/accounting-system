import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';

const Icons = {
    Plus: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Journal: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>,
    CheckCircle: () => <svg className="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Clock: () => <svg className="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Trash: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
    Edit: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>,
};

export default function Index({ auth, journals, can_create }) {
    const { post, delete: destroy } = useForm();

    const handlePost = async (id) => {
        const ok = await confirm({
            title: 'Post to Ledger?',
            text: 'This will lock the journal entry and create ledger postings. This action cannot be undone.',
            confirmText: 'Post Entry',
            icon: 'question',
        });
        if (ok) post(route('journal.post', id));
    };

    const handleDelete = async (id) => {
        const ok = await confirm({
            title: 'Delete Draft?',
            text: 'Are you sure you want to delete this draft journal entry?',
            confirmText: 'Delete',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) destroy(route('journal.destroy', id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 rounded-xl bg-blue-100 text-blue-600">
                            <Icons.Journal />
                        </span>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Manual Journal Entries</h2>
                            <p className="text-slate-500 text-sm font-medium mt-1">Record non-system accounting transactions</p>
                        </div>
                    </div>
                    {can_create && (
                        <Link
                            href={route('journal.create')}
                            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/25 transition-all duration-200"
                        >
                            <Icons.Plus /> New Journal Entry
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Manual Journals" />

            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-slate-50/80 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                <th className="p-6">Date</th>
                                <th className="p-6">Reference</th>
                                <th className="p-6">Description</th>
                                <th className="p-6">Status</th>
                                <th className="p-6 text-right">Debit</th>
                                <th className="p-6 text-right">Credit</th>
                                <th className="p-6 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {journals.data.length > 0 ? (
                                journals.data.map((journal) => {
                                    const totalDebit = journal.items.reduce((sum, item) => sum + parseFloat(item.debit), 0);
                                    const totalCredit = journal.items.reduce((sum, item) => sum + parseFloat(item.credit), 0);
                                    
                                    return (
                                        <tr key={journal.id} className="group hover:bg-slate-50/50 transition-colors duration-200">
                                            <td className="p-6">
                                                <span className="font-bold text-slate-700">
                                                    {new Date(journal.date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                </span>
                                            </td>
                                            <td className="p-6">
                                                <span className="font-mono text-sm text-slate-500 bg-slate-100 px-2 py-1 rounded">
                                                    {journal.reference_number || 'N/A'}
                                                </span>
                                            </td>
                                            <td className="p-6 max-w-xs">
                                                <div className="text-sm font-medium text-slate-900 truncate" title={journal.description}>
                                                    {journal.description}
                                                </div>
                                                <div className="text-[10px] text-slate-400 mt-1 uppercase font-bold tracking-tighter">
                                                    {journal.items.length} items
                                                </div>
                                            </td>
                                            <td className="p-6">
                                                {journal.status === 'posted' ? (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                        <Icons.CheckCircle /> Posted
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100">
                                                        <Icons.Clock /> Draft
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-6 text-right font-mono font-bold text-slate-700">
                                                {totalDebit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="p-6 text-right font-mono font-bold text-slate-700">
                                                {totalCredit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="p-6">
                                                <div className="flex justify-center items-center gap-2">
                                                    {journal.status === 'draft' && (
                                                        <>
                                                            <Link
                                                                href={route('journal.edit', journal.id)}
                                                                className="p-2 rounded-xl text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                                                title="Edit"
                                                            >
                                                                <Icons.Edit />
                                                            </Link>
                                                            <button
                                                                onClick={() => handlePost(journal.id)}
                                                                className="p-2 rounded-xl text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-200"
                                                                title="Post to Ledger"
                                                            >
                                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(journal.id)}
                                                                className="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                                                title="Delete"
                                                            >
                                                                <Icons.Trash />
                                                            </button>
                                                        </>
                                                    )}
                                                    {journal.status === 'posted' && (
                                                        <span className="text-[10px] text-slate-300 font-bold uppercase tracking-widest">Locked</span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan="7" className="p-12 text-center">
                                        <div className="max-w-xs mx-auto">
                                            <p className="text-slate-400 font-medium">No manual journal entries found.</p>
                                            {can_create && (
                                                <Link href={route('journal.create')} className="text-blue-600 hover:underline text-sm font-bold mt-2 inline-block">
                                                    Create your first entry
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination (Simplified) */}
                {journals.links && journals.links.length > 3 && (
                    <div className="p-6 bg-slate-50 border-t border-slate-200 flex justify-center gap-1">
                        {journals.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || '#'}
                                className={`px-4 py-2 rounded-xl text-sm font-bold transition-all ${
                                    link.active 
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' 
                                        : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'
                                } ${!link.url ? 'opacity-50 cursor-default' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
