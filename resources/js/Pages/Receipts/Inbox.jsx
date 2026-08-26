import React, { useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import IndexFilterBar from '@/Components/IndexFilterBar';
import IndexPagination from '@/Components/IndexPagination';
import RowActionsMenu, { ActionIcons } from '@/Components/RowActionsMenu';

const STATUSES = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'ready', label: 'Ready' },
    { value: 'failed', label: 'Failed' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'discarded', label: 'Discarded' },
];

function statusBadge(status) {
    const styles = {
        pending: 'bg-surface-alt text-ink-muted',
        processing: 'bg-mustard/15 text-mustard-dark',
        ready: 'bg-forest/10 text-forest',
        failed: 'bg-red-100 text-red-700',
        confirmed: 'bg-terracotta/10 text-terracotta',
        discarded: 'bg-surface-alt text-ink-muted line-through',
    };
    return styles[status] || 'bg-surface-alt text-ink';
}

function formatMoney(n) {
    if (n == null || Number.isNaN(Number(n))) return '—';
    return Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Inbox({ auth, jobs, filters = {} }) {
    const rows = jobs?.data || [];
    const { search = '', status: statusFilter = '', per_page: perPageFilter = 25 } = filters;
    const [searchInput, setSearchInput] = useState(search);
    const fileInputRef = useRef(null);
    const { data, setData, post, processing, errors, reset } = useForm({ receipt: null });

    const applyFilters = (overrides = {}) => {
        router.get(route('receipts.index'), {
            search: overrides.search ?? searchInput,
            status: overrides.status ?? statusFilter,
            per_page: overrides.per_page ?? perPageFilter,
            page: overrides.page ?? 1,
        }, { preserveState: false });
    };

    const onFileSelected = (file) => {
        if (!file) return;
        setData('receipt', file);
        post(route('receipts.store'), {
            forceFormData: true,
            onSuccess: () => reset('receipt'),
            onFinish: () => {
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const discardJob = async (id) => {
        const ok = await confirm({
            title: 'Discard receipt?',
            text: 'This removes it from your inbox. The file stays in storage.',
            confirmText: 'Discard',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('receipts.discard', id));
    };

    const retryJob = (id) => router.post(route('receipts.retry', id));

    const currentPage = jobs?.current_page || 1;
    const lastPage = jobs?.last_page || 1;
    const from = jobs?.from || 0;
    const to = jobs?.to || 0;
    const total = jobs?.total || 0;

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <h2 className="text-xl sm:text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Receipt inbox</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Upload receipts, review OCR, and create bill drafts without opening a bill first.</p>
                </div>
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={processing}
                    className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-terracotta text-white text-sm font-semibold hover:bg-terracotta-dark transition-colors disabled:opacity-60"
                >
                    {processing ? 'Uploading…' : 'Upload receipt'}
                </button>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    className="hidden"
                    onChange={(e) => onFileSelected(e.target.files?.[0])}
                />
            </div>
        }>
            <Head title="Receipt inbox" />

            <div className="space-y-4 min-w-0">
                {errors.receipt && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{errors.receipt}</div>
                )}

                <div className="bg-terracotta text-white rounded-2xl p-4 shadow-lg max-w-sm">
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-[10px] font-semibold uppercase tracking-widest opacity-90">Inbox</span>
                    </div>
                    <p className="text-xl font-bold tabular-nums">{total}</p>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <IndexFilterBar
                        search={searchInput}
                        onSearchChange={setSearchInput}
                        searchPlaceholder="Search filename or vendor..."
                        status={statusFilter}
                        statuses={STATUSES}
                        perPage={perPageFilter}
                        onApply={applyFilters}
                        from={from}
                        to={to}
                        total={total}
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-4 sm:px-6 py-3">File</th>
                                    <th className="px-4 sm:px-6 py-3">Vendor</th>
                                    <th className="px-4 sm:px-6 py-3">Amount</th>
                                    <th className="px-4 sm:px-6 py-3">Status</th>
                                    <th className="px-4 sm:px-6 py-3 text-right w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length > 0 ? rows.map((job) => (
                                    <tr key={job.id} className="border-b border-border-warm last:border-0 hover:bg-cream/80">
                                        <td className="px-4 sm:px-6 py-4">
                                            <Link href={route('receipts.show', job.id)} className="text-sm font-medium text-ink hover:text-terracotta">
                                                {job.original_filename || job.file_path.split('/').pop()}
                                            </Link>
                                            <p className="text-xs text-ink-muted mt-0.5">{job.bill_date || '—'}</p>
                                        </td>
                                        <td className="px-4 sm:px-6 py-4 text-sm text-ink">{job.vendor_name || '—'}</td>
                                        <td className="px-4 sm:px-6 py-4 text-sm tabular-nums text-ink">{formatMoney(job.total_amount)}</td>
                                        <td className="px-4 sm:px-6 py-4">
                                            <span className={`inline-flex px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase tracking-wide ${statusBadge(job.status)}`}>
                                                {job.status}
                                            </span>
                                            {job.error_message && job.status === 'failed' && (
                                                <p className="text-xs text-red-600 mt-1 max-w-xs truncate" title={job.error_message}>{job.error_message}</p>
                                            )}
                                        </td>
                                        <td className="px-4 sm:px-6 py-4 text-right">
                                            <RowActionsMenu items={[
                                                { label: 'Review', icon: <ActionIcons.Open />, show: ['ready', 'failed', 'pending', 'processing'].includes(job.status), onClick: () => router.visit(route('receipts.show', job.id)) },
                                                { label: 'Open bill', icon: <ActionIcons.Pencil />, show: job.status === 'confirmed' && job.bill_id, onClick: () => router.visit(route('bills.edit', job.bill_id)) },
                                                { label: 'Retry OCR', icon: <ActionIcons.Return />, show: job.is_retryable, onClick: () => retryJob(job.id) },
                                                { label: 'Discard', icon: <ActionIcons.Trash />, show: job.status !== 'confirmed' && job.status !== 'discarded', onClick: () => discardJob(job.id), danger: true },
                                            ]} />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-sm text-ink-muted">
                                            No receipts yet. Upload one to get started.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <IndexPagination
                        currentPage={currentPage}
                        lastPage={lastPage}
                        onPageChange={(page) => applyFilters({ page })}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
