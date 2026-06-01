import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border border-border-warm text-sm font-medium text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider';

const TYPE_OPTIONS = [
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'income', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

const SUB_TYPE_OPTIONS_BY_TYPE = {
    asset: [
        { value: '', label: '— None —' },
        { value: 'bank', label: 'Bank' },
        { value: 'cash', label: 'Cash' },
    ],
};

export default function Create({ auth, accounts = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        type: 'asset',
        sub_type: '',
        parent_id: '',
        description: '',
        is_active: true,
        display_order: '',
    });

    const subTypeOptions = SUB_TYPE_OPTIONS_BY_TYPE[data.type] || [];

    const handleTypeChange = (value) => {
        setData((prev) => ({
            ...prev,
            type: value,
            sub_type: SUB_TYPE_OPTIONS_BY_TYPE[value] ? prev.sub_type : '',
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('chart-of-accounts.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Add account</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">Create a new account in the chart of accounts</p>
                    </div>
                    <Link
                        href={route('chart-of-accounts.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors"
                    >
                        Back to chart
                    </Link>
                </div>
            }
        >
            <Head title="Add account" />

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">Account details</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Code</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="e.g. 1100"
                                required
                            />
                            {errors.code && <p className="text-terracotta text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Name</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. Accounts Receivable"
                                required
                            />
                            {errors.name && <p className="text-terracotta text-xs mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Type</label>
                            <select
                                className={inputClass}
                                value={data.type}
                                onChange={(e) => handleTypeChange(e.target.value)}
                                required
                            >
                                {TYPE_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                            {errors.type && <p className="text-terracotta text-xs mt-1">{errors.type}</p>}
                        </div>
                        {subTypeOptions.length > 0 && (
                            <div>
                                <label className={labelClass}>Subtype</label>
                                <select
                                    className={inputClass}
                                    value={data.sub_type}
                                    onChange={(e) => setData('sub_type', e.target.value)}
                                >
                                    {subTypeOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[11px] text-ink-muted mt-1">Tag asset accounts that hold money so they show in receipt and payment dropdowns.</p>
                                {errors.sub_type && <p className="text-terracotta text-xs mt-1">{errors.sub_type}</p>}
                            </div>
                        )}
                        <div>
                            <label className={labelClass}>Parent account</label>
                            <select
                                className={inputClass}
                                value={data.parent_id}
                                onChange={(e) => setData('parent_id', e.target.value)}
                            >
                                <option value="">— None (top-level) —</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.name}
                                    </option>
                                ))}
                            </select>
                            {errors.parent_id && <p className="text-terracotta text-xs mt-1">{errors.parent_id}</p>}
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Description</label>
                            <textarea
                                className={inputClass}
                                rows={3}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Optional description"
                            />
                            {errors.description && <p className="text-terracotta text-xs mt-1">{errors.description}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Display order</label>
                            <input
                                type="number"
                                min={0}
                                className={inputClass}
                                value={data.display_order}
                                onChange={(e) => setData('display_order', e.target.value)}
                                placeholder="Optional"
                            />
                            {errors.display_order && <p className="text-terracotta text-xs mt-1">{errors.display_order}</p>}
                        </div>
                        <div className="flex items-center gap-3 pt-2">
                            <input
                                type="checkbox"
                                id="is_active"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-border-warm text-terracotta focus:ring-terracotta"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-ink">
                                Active (available for posting)
                            </label>
                        </div>
                    </div>
                </div>
                <div className="flex justify-end gap-3">
                    <Link
                        href={route('chart-of-accounts.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Saving…' : 'Create account'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
