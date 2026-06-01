import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function FeatureListEditor({ features, onChange }) {
    const [newFeature, setNewFeature] = useState('');

    const add = () => {
        const trimmed = newFeature.trim();
        if (!trimmed) return;
        onChange([...features, trimmed]);
        setNewFeature('');
    };

    const remove = (idx) => onChange(features.filter((_, i) => i !== idx));

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <input
                    type="text"
                    value={newFeature}
                    onChange={(e) => setNewFeature(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), add())}
                    placeholder="Add a feature bullet…"
                    className="flex-1 border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                />
                <button type="button" onClick={add} className="px-4 py-2 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm">
                    Add
                </button>
            </div>
            {features.length > 0 && (
                <ul className="space-y-1">
                    {features.map((f, i) => (
                        <li key={i} className="flex items-center gap-2 text-sm text-ink bg-cream rounded-xl px-3 py-1.5 border border-border-warm">
                            <span className="flex-1">{f}</span>
                            <button type="button" onClick={() => remove(i)} className="text-ink-muted hover:text-terracotta transition-colors">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function PermissionMatrix({ permissionsGrouped, selected, onChange }) {
    const toggle = (perm) => {
        if (selected.includes(perm)) {
            onChange(selected.filter((p) => p !== perm));
        } else {
            onChange([...selected, perm]);
        }
    };

    const toggleGroup = (perms) => {
        const allSelected = perms.every((p) => selected.includes(p));
        if (allSelected) {
            onChange(selected.filter((p) => !perms.includes(p)));
        } else {
            const toAdd = perms.filter((p) => !selected.includes(p));
            onChange([...selected, ...toAdd]);
        }
    };

    return (
        <div className="space-y-4">
            {Object.entries(permissionsGrouped).map(([group, perms]) => {
                const allSelected = perms.every((p) => selected.includes(p));
                const someSelected = perms.some((p) => selected.includes(p));
                return (
                    <div key={group} className="rounded-xl border border-border-warm overflow-hidden">
                        <div className="px-4 py-2 bg-cream flex items-center justify-between border-b border-border-warm">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    className="accent-indigo-600 w-3.5 h-3.5"
                                    checked={allSelected}
                                    ref={(el) => { if (el) el.indeterminate = !allSelected && someSelected; }}
                                    onChange={() => toggleGroup(perms)}
                                />
                                <span className="text-xs font-display font-medium text-ink uppercase tracking-wider">{group}</span>
                            </label>
                            <span className="text-[10px] text-ink-muted">{perms.filter((p) => selected.includes(p)).length}/{perms.length}</span>
                        </div>
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1 p-3">
                            {perms.map((perm) => (
                                <label key={perm} className="flex items-center gap-1.5 cursor-pointer text-xs text-ink hover:text-ink">
                                    <input
                                        type="checkbox"
                                        className="accent-indigo-600"
                                        checked={selected.includes(perm)}
                                        onChange={() => toggle(perm)}
                                    />
                                    <span className="font-mono">{perm}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function PlanForm({ auth, plan = null, permissionsGrouped = {} }) {
    const isEditing = Boolean(plan);

    const { data, setData, post, put, processing, errors } = useForm({
        name:             plan?.name ?? '',
        slug:             plan?.slug ?? '',
        price_monthly:    plan?.price_monthly ?? 0,
        price_yearly:     plan?.price_yearly ?? 0,
        users_included:   plan?.users_included ?? 1,
        extra_user_price: plan?.extra_user_price ?? 0,
        features:         plan?.features ?? [],
        is_active:        plan?.is_active ?? true,
        permissions:      plan?.permissions ?? [],
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('admin.plans.update', plan.id));
        } else {
            post(route('admin.plans.store'));
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-display font-medium text-ink">
                            {isEditing ? `Edit Plan: ${plan.name}` : 'New Plan'}
                        </h2>
                        {isEditing && (
                            <p className="text-ink-muted text-sm mt-1">Slug <span className="font-mono">{plan.slug}</span> is immutable after creation.</p>
                        )}
                    </div>
                    <Link href={route('admin.plans.index')} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                        Back to Plans
                    </Link>
                </div>
            }
        >
            <Head title={isEditing ? `Edit ${plan.name}` : 'New Plan'} />

            <form onSubmit={handleSubmit} className="space-y-6 max-w-3xl">
                {/* Basic info */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6 space-y-4">
                    <h3 className="font-semibold text-ink">Basic Information</h3>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-ink mb-1">Name</label>
                            <input
                                type="text"
                                className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            {errors.name && <p className="text-xs text-terracotta mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-ink mb-1">
                                Slug {isEditing && <span className="text-ink-muted font-normal">(read-only)</span>}
                            </label>
                            <input
                                type="text"
                                className={`w-full border border-border-warm rounded-xl px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-terracotta ${isEditing ? 'bg-cream text-ink-muted' : ''}`}
                                value={data.slug}
                                onChange={(e) => !isEditing && setData('slug', e.target.value)}
                                readOnly={isEditing}
                            />
                            {errors.slug && <p className="text-xs text-terracotta mt-1">{errors.slug}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-ink mb-1">Monthly (RM)</label>
                            <input type="number" min="0" step="0.01"
                                className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                                value={data.price_monthly}
                                onChange={(e) => setData('price_monthly', e.target.value)}
                            />
                            {errors.price_monthly && <p className="text-xs text-terracotta mt-1">{errors.price_monthly}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-ink mb-1">Yearly (RM)</label>
                            <input type="number" min="0" step="0.01"
                                className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                                value={data.price_yearly}
                                onChange={(e) => setData('price_yearly', e.target.value)}
                            />
                            {errors.price_yearly && <p className="text-xs text-terracotta mt-1">{errors.price_yearly}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-ink mb-1">Users Included</label>
                            <input type="number" min="1"
                                className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                                value={data.users_included}
                                onChange={(e) => setData('users_included', e.target.value)}
                            />
                            {errors.users_included && <p className="text-xs text-terracotta mt-1">{errors.users_included}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-ink mb-1">Extra User (RM)</label>
                            <input type="number" min="0" step="0.01"
                                className="w-full border border-border-warm rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-terracotta"
                                value={data.extra_user_price}
                                onChange={(e) => setData('extra_user_price', e.target.value)}
                            />
                            {errors.extra_user_price && <p className="text-xs text-terracotta mt-1">{errors.extra_user_price}</p>}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            id="is_active"
                            className="accent-indigo-600 w-4 h-4"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        <label htmlFor="is_active" className="text-sm font-medium text-ink">Active (visible in subscription UI)</label>
                    </div>
                </div>

                {/* Feature bullets */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6 space-y-3">
                    <h3 className="font-semibold text-ink">Marketing Features</h3>
                    <p className="text-xs text-ink-muted">These bullets appear on the subscription page.</p>
                    <FeatureListEditor
                        features={data.features}
                        onChange={(f) => setData('features', f)}
                    />
                </div>

                {/* Permission matrix */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm p-6 space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-ink">Feature Permissions</h3>
                        <span className="text-xs text-ink-muted">{data.permissions.length} selected</span>
                    </div>
                    <p className="text-xs text-ink-muted">These control which modules are accessible to tenants on this plan.</p>
                    {errors.permissions && <p className="text-xs text-terracotta">{errors.permissions}</p>}
                    <PermissionMatrix
                        permissionsGrouped={permissionsGrouped}
                        selected={data.permissions}
                        onChange={(p) => setData('permissions', p)}
                    />
                </div>

                {/* Submit */}
                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta transition-colors disabled:opacity-60"
                    >
                        {processing ? 'Saving…' : isEditing ? 'Update Plan' : 'Create Plan'}
                    </button>
                    <Link href={route('admin.plans.index')} className="px-5 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                        Cancel
                    </Link>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
