import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

export default function Form({ auth, asset = null }) {
    const isEdit = Boolean(asset?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: asset?.name ?? '',
        description: asset?.description ?? '',
        purchase_date: asset?.purchase_date ?? new Date().toISOString().slice(0, 10),
        cost: asset?.cost ?? '',
        salvage_value: asset?.salvage_value ?? 0,
        useful_life_months: asset?.useful_life_months ?? 60,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('fixed-assets.update', asset.id));
        } else {
            post(route('fixed-assets.store'));
        }
    };

    const field = (label, key, type = 'text', props = {}) => (
        <label className="block text-sm">
            <span className="font-medium text-ink">{label}</span>
            <input
                type={type}
                className="mt-1 w-full rounded-xl border border-border-warm px-3 py-2.5"
                value={data[key]}
                onChange={(e) => setData(key, type === 'number' ? e.target.value : e.target.value)}
                {...props}
            />
            {errors[key] && <span className="text-terracotta text-xs mt-1 block">{errors[key]}</span>}
        </label>
    );

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('fixed-assets.index')}
                    title={isEdit ? 'Edit fixed asset' : 'Register fixed asset'}
                    subtitle="Straight-line depreciation posts Dr 5810 Cr 1510"
                    formId="fixed-asset-form"
                    processing={processing}
                    submitLabel={isEdit ? 'Save changes' : 'Register asset'}
                />
            }
        >
            <Head title={isEdit ? 'Edit fixed asset' : 'Register fixed asset'} />

            <form id="fixed-asset-form" onSubmit={submit} className="max-w-xl space-y-4 bg-surface rounded-2xl border border-border-warm p-6">
                {field('Name', 'name', 'text', { required: true })}
                <label className="block text-sm">
                    <span className="font-medium text-ink">Description</span>
                    <textarea className="mt-1 w-full rounded-xl border border-border-warm px-3 py-2.5" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                </label>
                {field('Purchase date', 'purchase_date', 'date', { required: true })}
                {field('Cost (MYR)', 'cost', 'number', { required: true, min: 0.01, step: 0.01 })}
                {field('Salvage value (MYR)', 'salvage_value', 'number', { min: 0, step: 0.01 })}
                {field('Useful life (months)', 'useful_life_months', 'number', { required: true, min: 1 })}
            </form>
        </AuthenticatedLayout>
    );
}
