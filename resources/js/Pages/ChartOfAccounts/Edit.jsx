import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const inputClass =
    'block w-full rounded-xl border border-border-warm px-4 py-2.5 text-sm font-medium text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

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

const TYPE_HINTS = {
    asset: { title: 'Asset', code: '1000–1999', text: 'Use for money, receivables, deposits, stock, or things the business owns.' },
    liability: { title: 'Liability', code: '2000–2999', text: 'Use for payables, tax owed, loans, and money you still owe.' },
    equity: { title: 'Equity', code: '3000–3999', text: 'Use for owner funds, retained earnings, and drawings.' },
    income: { title: 'Revenue', code: '4000–4999', text: 'Use for sales and other income earned by the business.' },
    expense: { title: 'Expense', code: '5000–6999', text: 'Use for operating costs such as rent, salary, software, and utilities.' },
};

export default function Edit({ auth, account, accounts = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        code: account.code ?? '',
        name: account.name ?? '',
        type: account.type ?? 'asset',
        sub_type: account.sub_type ?? '',
        parent_id: account.parent_id ?? '',
        description: account.description ?? '',
        is_active: account.is_active ?? true,
        display_order: account.display_order ?? '',
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
        put(route('chart-of-accounts.update', account.id));
    };

    const currentTypeHint = TYPE_HINTS[data.type];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('chart-of-accounts.index')}
                    title="Edit account"
                    subtitle={`${account.code} — ${account.name}`}
                    formId="coa-edit-form"
                    processing={processing}
                    submitLabel="Update account"
                />
            }
        >
            <Head title={`Edit ${account.code}`} />

            <form id="coa-edit-form" onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
                    <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-6">
                        <div>
                            <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">Account details</h3>
                            <p className="text-sm text-ink-muted mt-1">Update the code, label, and behavior for this account.</p>
                        </div>

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
                            <div>
                                <label className={labelClass}>Subtype</label>
                                <select
                                    className={inputClass}
                                    value={data.sub_type}
                                    onChange={(e) => setData('sub_type', e.target.value)}
                                    disabled={subTypeOptions.length === 0}
                                >
                                    {(subTypeOptions.length > 0 ? subTypeOptions : [{ value: '', label: '— None —' }]).map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[11px] text-ink-muted mt-1">Only asset accounts can be tagged as bank or cash.</p>
                                {errors.sub_type && <p className="text-terracotta text-xs mt-1">{errors.sub_type}</p>}
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
                                {errors.parent_id && <p className="text-terracotta text-xs mt-1">{errors.parent_id}</p>}
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
                            <div className="md:col-span-2">
                                <label className={labelClass}>Description</label>
                                <textarea
                                    className={inputClass}
                                    rows={3}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Optional note about how this account should be used"
                                />
                                {errors.description && <p className="text-terracotta text-xs mt-1">{errors.description}</p>}
                            </div>
                            <div className="md:col-span-2">
                                <label className="inline-flex items-center gap-3 text-sm font-medium text-ink">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        checked={data.is_active}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                        className="rounded border-border-warm text-terracotta focus:ring-terracotta"
                                    />
                                    Active and available for posting
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Type guide</p>
                            <p className="mt-2 font-semibold text-ink">{currentTypeHint.title}</p>
                            <p className="text-xs text-terracotta font-mono mt-1">{currentTypeHint.code}</p>
                            <p className="text-sm text-ink-muted mt-2">{currentTypeHint.text}</p>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Editing reminder</p>
                            <ul className="mt-3 space-y-2 text-sm text-ink-muted">
                                <li>Changing the name is safe and common.</li>
                                <li>Changing the code affects how users recognize the account.</li>
                                <li>Bank and cash subtype only matters for receipt and payment flows.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}