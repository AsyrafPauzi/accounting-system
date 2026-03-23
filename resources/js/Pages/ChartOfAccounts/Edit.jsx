import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border border-slate-200 text-sm font-medium text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500';
const labelClass = 'block text-[10px] font-semibold text-slate-400 uppercase tracking-wider';

const TYPE_OPTIONS = [
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'income', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

export default function Edit({ auth, account, accounts = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        code: account.code ?? '',
        name: account.name ?? '',
        type: account.type ?? 'asset',
        parent_id: account.parent_id ?? '',
        description: account.description ?? '',
        is_active: account.is_active ?? true,
        display_order: account.display_order ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('chart-of-accounts.update', account.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Edit account</h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            {account.code} — {account.name}
                        </p>
                    </div>
                    <Link
                        href={route('chart-of-accounts.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                    >
                        Back to chart
                    </Link>
                </div>
            }
        >
            <Head title={`Edit ${account.code}`} />

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider">Account details</h3>
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
                            {errors.code && <p className="text-rose-500 text-xs mt-1">{errors.code}</p>}
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
                            {errors.name && <p className="text-rose-500 text-xs mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Type</label>
                            <select
                                className={inputClass}
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                required
                            >
                                {TYPE_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                            {errors.type && <p className="text-rose-500 text-xs mt-1">{errors.type}</p>}
                        </div>
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
                            {errors.parent_id && <p className="text-rose-500 text-xs mt-1">{errors.parent_id}</p>}
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
                            {errors.description && <p className="text-rose-500 text-xs mt-1">{errors.description}</p>}
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
                            {errors.display_order && <p className="text-rose-500 text-xs mt-1">{errors.display_order}</p>}
                        </div>
                        <div className="flex items-center gap-3 pt-2">
                            <input
                                type="checkbox"
                                id="is_active"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-slate-200 text-blue-600 focus:ring-blue-500"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-slate-700">
                                Active (available for posting)
                            </label>
                        </div>
                    </div>
                </div>
                <div className="flex justify-end gap-3">
                    <Link
                        href={route('chart-of-accounts.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Saving…' : 'Update account'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );