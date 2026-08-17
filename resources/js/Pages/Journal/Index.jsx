import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const Icons = {
    Plus: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Journal: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>,
    CheckCircle: () => <svg className="w-5 h-5 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Clock: () => <svg className="w-5 h-5 text-mustard" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
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
                        <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta">
                            <Icons.Journal />
                        </span>
                        <div className="flex flex-col gap-1">
                            <p className="text-eyebrow font-semibold uppercase text-terracotta">Ledger</p>
                            <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Manual journal entries</h1>
                            <p className="text-ink-muted text-sm">Record adjustments and transactions the system can’t see.</p>
                        </div>
                    </div>
                    {can_create && (
                        <Link
                            href={route('journal.create')}
                            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta shadow-lg  transition-all duration-200"
                        >
                            <Icons.Plus /> New Journal Entry
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Manual Journals" />

            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                <th className="p-6">Date</th>
                                <th className="p-6">Reference</th>
                                <th className="p-6">Description</th>
                                <th className="p-6">Status</th>
                                <th className="p-6 text-right">Debit</th>
                                <th className="p-6 text-right">Credit</th>
                                <th className="p-6 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {journals.data.length > 0 ? (
                                journals.data.map((journal) => {
                                    const totalDebit = journal.items.reduce((sum, item) => sum + parseFloat(item.debit), 0);
                                    const totalCredit = journal.items.reduce((sum, item) => sum + parseFloat(item.credit), 0);
                                    
                                    return (
                                        <tr key={journal.id} className="group hover:bg-cream/50 transition-colors duration-200">
                                            <td className="p-6">
                                                <span className="font-display font-medium text-ink">
                                                    {new Date(journal.date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                </span>
                                            </td>
                                            <td className="p-6">
                                                <span className="font-mono text-sm text-ink-muted bg-surface-alt px-2 py-1 rounded">
                                                    {journal.reference_number || 'N/A'}
                                                </span>
                                            </td>
                                            <td className="p-6 max-w-xs">
                                                <div className="text-sm font-medium text-ink truncate" title={journal.description}>
                                                    {journal.description}
                                                </div>
                                                <div className="text-[10px] text-ink-muted mt-1 uppercase font-bold tracking-tighter">
                                                    {journal.items.length} items
                                                </div>
                                            </td>
                                            <td className="p-6">
                                                {journal.status === 'posted' ? (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-forest/10 text-forest border border-forest/30">
                                                        <Icons.CheckCircle /> Posted
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-mustard/15 text-mustard border border-mustard/40">
                                                        <Icons.Clock /> Draft
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-6 text-right font-mono font-display font-medium text-ink">
                                                {totalDebit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="p-6 text-right font-mono font-display font-medium text-ink">
                                                {totalCredit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="p-6 text-right">
                                                {journal.status === 'posted' ? (
                                                    <span className="text-[10px] text-ink-muted font-bold uppercase tracking-widest">Locked</span>
                                                ) : (
                                                    <RowActionsMenu items={[
                                                        { label: 'Edit', href: route('journal.edit', journal.id), icon: <ActionIcons.Pencil />, show: auth.permissions.includes('journal.edit') },
                                                        { label: 'Post to ledger', icon: <ActionIcons.Check />, show: auth.permissions.includes('journal.post'), onClick: () => handlePost(journal.id) },
                                                        { label: 'Delete draft', icon: <ActionIcons.Trash />, danger: true, show: auth.permissions.includes('journal.delete'), onClick: () => handleDelete(journal.id) },
                                                    ]} />
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan="7" className="p-12 text-center">
                                        <div className="max-w-xs mx-auto">
                                            <p className="text-ink-muted font-medium">No manual journal entries found.</p>
                                            {can_create && (
                                                <Link href={route('journal.create')} className="text-terracotta hover:underline text-sm font-bold mt-2 inline-block">
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
                    <div className="p-6 bg-cream border-t border-border-warm flex justify-center gap-1">
                        {journals.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || '#'}
                                className={`px-4 py-2 rounded-xl text-sm font-bold transition-all ${
                                    link.active 
                                        ? 'bg-terracotta text-white shadow-md ' 
                                        : 'bg-surface text-ink border border-border-warm hover:border-border-warm'
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
