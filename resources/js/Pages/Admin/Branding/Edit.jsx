import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const HEX = /^#[A-Fa-f0-9]{6}$/;

function ColorField({ label, name, value, onChange, defaultHex }) {
    const safe = HEX.test(value || '') ? value : defaultHex;
    return (
        <div className="space-y-2">
            <label className="block text-eyebrow font-semibold uppercase text-ink-muted">{label}</label>
            <div className="flex items-center gap-3">
                <input
                    type="color"
                    value={safe}
                    onChange={(e) => onChange(name, e.target.value.toUpperCase())}
                    className="h-12 w-14 rounded-xl border border-border-warm bg-surface cursor-pointer"
                />
                <input
                    type="text"
                    value={value || ''}
                    placeholder={defaultHex}
                    onChange={(e) => onChange(name, e.target.value.toUpperCase())}
                    className="flex-1 rounded-xl border-border-warm bg-surface text-sm font-mono text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                />
                <span
                    className="h-12 w-12 rounded-xl border border-border-warm"
                    style={{ background: safe }}
                    aria-hidden
                />
            </div>
            <p className="text-xs text-ink-muted">Default: <span className="font-mono">{defaultHex}</span></p>
        </div>
    );
}

export default function BrandingEdit({ brand, defaults }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        product_name: brand.product_name || '',
        product_tagline: brand.product_tagline || '',
        color_terracotta: brand.color_terracotta || '',
        color_forest: brand.color_forest || '',
        color_mustard: brand.color_mustard || '',
        logo: null,
        favicon: null,
        reset: false,
    });

    const [logoPreview, setLogoPreview] = useState(brand.logo_url);
    const [faviconPreview, setFaviconPreview] = useState(brand.favicon_url);

    const previewColors = {
        terracotta: HEX.test(data.color_terracotta) ? data.color_terracotta : defaults.color_terracotta,
        forest: HEX.test(data.color_forest) ? data.color_forest : defaults.color_forest,
        mustard: HEX.test(data.color_mustard) ? data.color_mustard : defaults.color_mustard,
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.branding.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                window.location.reload();
            },
        });
    };

    const resetToDefaults = () => {
        if (!confirm('Reset all branding to BukuCloud defaults? This clears logos and color overrides.')) return;
        setData('reset', true);
        post(route('admin.branding.update'), {
            preserveScroll: true,
            onSuccess: () => window.location.reload(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Admin</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Branding</h1>
                    <p className="text-ink-muted text-sm">
                        Customize the product name, accent palette and logos. Changes apply platform-wide and every tenant sees them on their next page load.
                    </p>
                </div>
            }
        >
            <Head title="Branding" />

            <form onSubmit={submit} className="max-w-3xl space-y-6" encType="multipart/form-data">
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                    <h2 className="font-display text-xl font-medium text-ink">Identity</h2>
                    <div>
                        <label className="block text-eyebrow font-semibold uppercase text-ink-muted">Product name</label>
                        <input
                            type="text"
                            className="mt-1.5 w-full rounded-xl border-border-warm bg-surface text-sm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                            value={data.product_name}
                            placeholder={defaults.product_name}
                            onChange={(e) => setData('product_name', e.target.value)}
                        />
                        {errors.product_name && <p className="text-terracotta text-xs mt-1">{errors.product_name}</p>}
                    </div>
                    <div>
                        <label className="block text-eyebrow font-semibold uppercase text-ink-muted">Tagline</label>
                        <input
                            type="text"
                            className="mt-1.5 w-full rounded-xl border-border-warm bg-surface text-sm text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                            value={data.product_tagline}
                            placeholder="Books made for the way you actually work."
                            onChange={(e) => setData('product_tagline', e.target.value)}
                        />
                        {errors.product_tagline && <p className="text-terracotta text-xs mt-1">{errors.product_tagline}</p>}
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                    <h2 className="font-display text-xl font-medium text-ink">Logos</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <label className="block text-eyebrow font-semibold uppercase text-ink-muted">Logo</label>
                            {logoPreview && (
                                <div className="p-4 bg-cream rounded-xl border border-border-warm">
                                    <img src={logoPreview} alt="Logo" className="h-12" />
                                </div>
                            )}
                            <input
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setData('logo', file);
                                    setLogoPreview(file ? URL.createObjectURL(file) : brand.logo_url);
                                }}
                                className="block w-full text-sm text-ink-muted file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-ink file:text-cream file:text-sm file:font-semibold hover:file:bg-ink-muted"
                            />
                            {errors.logo && <p className="text-terracotta text-xs mt-1">{errors.logo}</p>}
                        </div>
                        <div className="space-y-2">
                            <label className="block text-eyebrow font-semibold uppercase text-ink-muted">Favicon</label>
                            {faviconPreview && (
                                <div className="p-4 bg-cream rounded-xl border border-border-warm flex items-center gap-3">
                                    <img src={faviconPreview} alt="Favicon" className="h-12 w-12" />
                                    <span className="text-xs text-ink-muted">64×64 recommended</span>
                                </div>
                            )}
                            <input
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setData('favicon', file);
                                    setFaviconPreview(file ? URL.createObjectURL(file) : brand.favicon_url);
                                }}
                                className="block w-full text-sm text-ink-muted file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-ink file:text-cream file:text-sm file:font-semibold hover:file:bg-ink-muted"
                            />
                            {errors.favicon && <p className="text-terracotta text-xs mt-1">{errors.favicon}</p>}
                        </div>
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-6">
                    <div>
                        <h2 className="font-display text-xl font-medium text-ink">Accent colors</h2>
                        <p className="text-sm text-ink-muted mt-1">
                            Background, text and surface colors stay locked for legibility — these accents flow through buttons, status pills and section headings.
                        </p>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <ColorField
                            label="Terracotta · primary"
                            name="color_terracotta"
                            value={data.color_terracotta}
                            onChange={setData}
                            defaultHex={defaults.color_terracotta}
                        />
                        <ColorField
                            label="Forest · success"
                            name="color_forest"
                            value={data.color_forest}
                            onChange={setData}
                            defaultHex={defaults.color_forest}
                        />
                        <ColorField
                            label="Mustard · accent"
                            name="color_mustard"
                            value={data.color_mustard}
                            onChange={setData}
                            defaultHex={defaults.color_mustard}
                        />
                    </div>

                    <div className="rounded-2xl border border-border-warm p-6 bg-cream">
                        <p className="text-eyebrow font-semibold uppercase text-ink-muted">Live preview</p>
                        <div className="mt-3 flex flex-wrap items-center gap-4">
                            <button
                                type="button"
                                style={{ background: previewColors.terracotta }}
                                className="px-5 py-2.5 rounded-xl text-white text-sm font-semibold"
                            >
                                Primary action
                            </button>
                            <span
                                style={{ background: `${previewColors.forest}1A`, color: previewColors.forest, borderColor: `${previewColors.forest}4D` }}
                                className="px-3 py-1 rounded-full text-eyebrow font-semibold uppercase border"
                            >
                                Paid
                            </span>
                            <span
                                style={{ background: `${previewColors.mustard}26`, color: '#1A1A1A', borderColor: `${previewColors.mustard}66` }}
                                className="px-3 py-1 rounded-full text-eyebrow font-semibold uppercase border"
                            >
                                Premium
                            </span>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark dark:hover:bg-terracotta-light disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Saving…' : 'Save branding'}
                    </button>
                    <button
                        type="button"
                        onClick={resetToDefaults}
                        className="inline-flex items-center px-6 py-2.5 rounded-xl text-sm font-semibold text-ink border border-ink hover:bg-surface-alt transition-colors"
                    >
                        Reset to defaults
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
