import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

function statusMessage(status) {
    if (status === 'pending' || status === 'processing') {
        return 'OCR is still running. This page refreshes automatically.';
    }
    if (status === 'failed') {
        return 'OCR failed — edit fields manually or retry extraction.';
    }
    return 'Review extracted fields, then confirm to create a bill draft.';
}

export default function Review({ auth, job, suppliers = [], expenseAccounts = [], taxCodes = [], defaults = {} }) {
    const defaultAccount = expenseAccounts[0]?.code || '5000';
    const initialItems = useMemo(() => {
        if (defaults.items?.length) {
            return defaults.items.map((item) => ({
                ...blankPurchaseLine(item.account_code || defaultAccount),
                description: item.description || '',
                quantity: item.quantity ?? 1,
                unit_price: item.unit_amount ?? 0,
                tax_code_id: item.tax_code_id ?? defaults.tax_code_id ?? null,
            }));
        }
        return [blankPurchaseLine(defaultAccount)];
    }, [defaults, defaultAccount]);

    const { data, setData, post, processing, errors } = useForm({
        supplier_id: '',
        vendor_name: defaults.vendor_name || '',
        bill_date: defaults.bill_date || new Date().toISOString().split('T')[0],
        due_date: defaults.due_date || '',
        reference: defaults.reference || '',
        tax_code_id: defaults.tax_code_id || '',
        tax_amount: defaults.tax_amount ?? 0,
        items: initialItems,
    });

    const [pollTick, setPollTick] = useState(0);

    useEffect(() => {
        if (!['pending', 'processing'].includes(job.status)) return undefined;
        const timer = setInterval(() => {
            router.reload({ only: ['job', 'defaults'], preserveScroll: true });
            setPollTick((t) => t + 1);
        }, 3000);
        return () => clearInterval(timer);
    }, [job.status, pollTick]);

    useEffect(() => {
        if (defaults.items?.length && ['ready', 'failed'].includes(job.status)) {
            setData((prev) => ({
                ...prev,
                vendor_name: defaults.vendor_name || prev.vendor_name,
                bill_date: defaults.bill_date || prev.bill_date,
                reference: defaults.reference || prev.reference,
                tax_code_id: defaults.tax_code_id || prev.tax_code_id,
                tax_amount: defaults.tax_amount ?? prev.tax_amount,
                items: defaults.items.map((item) => ({
                    ...blankPurchaseLine(item.account_code || defaultAccount),
                    description: item.description || '',
                    quantity: item.quantity ?? 1,
                    unit_price: item.unit_amount ?? 0,
                    tax_code_id: item.tax_code_id ?? defaults.tax_code_id ?? null,
                })),
            }));
        }
    }, [job.status, defaults, defaultAccount, setData]);

    const subtotal = useMemo(
        () => data.items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0),
        [data.items]
    );

    const submitConfirm = async (e) => {
        e.preventDefault();
        const ok = await confirm({
            title: 'Create bill draft?',
            text: 'A draft supplier bill will be created from these fields.',
            confirmText: 'Create draft',
            icon: 'question',
        });
        if (!ok) return;

        post(route('receipts.confirm', job.id), {
            preserveScroll: true,
            transform: (form) => ({
                ...form,
                items: form.items.map((item) => {
                    const qty = Number(item.quantity) || 0;
                    const unit = Number(item.unit_price) || 0;
                    return {
                        account_code: item.account_code,
                        description: item.description,
                        quantity: qty,
                        unit_amount: unit,
                        amount: qty * unit,
                        tax_code_id: item.tax_code_id || form.tax_code_id || null,
                    };
                }),
            }),
        });
    };

    const discard = async () => {
        const ok = await confirm({
            title: 'Discard receipt?',
            text: 'This removes it from your inbox.',
            confirmText: 'Discard',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('receipts.discard', job.id));
    };

    const retry = () => router.post(route('receipts.retry', job.id));

    const isPdf = (job.file_path || '').toLowerCase().endsWith('.pdf');

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <Link href={route('receipts.index')} className="text-xs font-semibold text-terracotta hover:underline">← Back to inbox</Link>
                    <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight mt-1">Review receipt</h2>
                    <p className="text-ink-muted text-sm mt-1">{statusMessage(job.status)}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {job.is_retryable && (
                        <button type="button" onClick={retry} className="px-4 py-2 rounded-xl border border-border-warm text-sm font-medium text-ink hover:bg-surface-alt">
                            Retry OCR
                        </button>
                    )}
                    <button type="button" onClick={discard} className="px-4 py-2 rounded-xl border border-red-200 text-sm font-medium text-red-700 hover:bg-red-50">
                        Discard
                    </button>
                </div>
            </div>
        }>
            <Head title="Review receipt" />

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="px-4 py-3 border-b border-border-warm bg-cream/60">
                        <p className="text-sm font-medium text-ink">{job.original_filename || 'Receipt'}</p>
                        <span className={`inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase ${job.status === 'ready' ? 'bg-forest/10 text-forest' : 'bg-surface-alt text-ink-muted'}`}>
                            {job.status}
                        </span>
                    </div>
                    <div className="p-4 min-h-[320px] flex items-center justify-center bg-cream/30">
                        {job.receipt_url ? (
                            isPdf ? (
                                <iframe src={job.receipt_url} title="Receipt PDF" className="w-full h-[480px] rounded-xl border border-border-warm bg-white" />
                            ) : (
                                <img src={job.receipt_url} alt="Receipt" className="max-h-[480px] max-w-full rounded-xl border border-border-warm object-contain" />
                            )
                        ) : (
                            <p className="text-sm text-ink-muted">Preview unavailable</p>
                        )}
                    </div>
                    {job.error_message && (
                        <div className="px-4 py-3 border-t border-border-warm bg-red-50 text-sm text-red-700">{job.error_message}</div>
                    )}
                </div>

                <form onSubmit={submitConfirm} className="space-y-4">
                    <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 sm:p-6 space-y-4">
                        <div>
                            <label className={labelClass}>Supplier</label>
                            <select
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', e.target.value)}
                                className={inputClass}
                            >
                                <option value="">— New / from receipt —</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                        </div>
                        {!data.supplier_id && (
                            <div>
                                <label className={labelClass}>Vendor name</label>
                                <input
                                    type="text"
                                    value={data.vendor_name}
                                    onChange={(e) => setData('vendor_name', e.target.value)}
                                    className={inputClass}
                                    required
                                />
                                {errors.vendor_name && <p className="text-xs text-red-600 mt-1">{errors.vendor_name}</p>}
                            </div>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bill date</label>
                                <input type="date" value={data.bill_date} onChange={(e) => setData('bill_date', e.target.value)} className={inputClass} required />
                            </div>
                            <div>
                                <label className={labelClass}>Due date</label>
                                <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className={inputClass} />
                            </div>
                        </div>

                        <div>
                            <label className={labelClass}>Reference</label>
                            <input type="text" value={data.reference} onChange={(e) => setData('reference', e.target.value)} className={inputClass} />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Tax code</label>
                                <select value={data.tax_code_id} onChange={(e) => setData('tax_code_id', e.target.value)} className={inputClass}>
                                    <option value="">— None —</option>
                                    {taxCodes.map((tc) => (
                                        <option key={tc.id} value={tc.id}>{tc.code} — {tc.name} ({tc.rate}%)</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={labelClass}>Tax amount (MYR)</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.tax_amount}
                                    onChange={(e) => setData('tax_amount', e.target.value)}
                                    className={inputClass}
                                />
                            </div>
                        </div>

                        <PurchasesDocLines
                            items={data.items}
                            onChange={(items) => setData('items', items)}
                            expenseAccounts={expenseAccounts}
                            showTax={false}
                        />

                        <div className="flex justify-between text-sm pt-2 border-t border-border-warm">
                            <span className="text-ink-muted">Subtotal</span>
                            <span className="font-semibold tabular-nums text-ink">{subtotal.toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-ink-muted">Tax</span>
                            <span className="font-semibold tabular-nums text-ink">{Number(data.tax_amount || 0).toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between text-base font-display font-medium">
                            <span className="text-ink">Total</span>
                            <span className="tabular-nums text-terracotta">{(subtotal + Number(data.tax_amount || 0)).toFixed(2)}</span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing || ['pending', 'processing'].includes(job.status)}
                        className="w-full sm:w-auto px-6 py-3 rounded-xl bg-terracotta text-white text-sm font-semibold hover:bg-terracotta-dark disabled:opacity-60 transition-colors"
                    >
                        {processing ? 'Creating…' : 'Confirm → create bill draft'}
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
