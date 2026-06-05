import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { applyBrandPreview, clearPreview, readPreview, writePreview } from '@/utils/brandPreview';

const HEX = /^#[A-Fa-f0-9]{6}$/;

/**
 * Color picker that survives @tailwindcss/forms' preflight rules.
 *
 * The previous version used a styled <input type="color"> directly + a
 * separate preview <span>. Both got squashed to thin slivers because the
 * forms plugin overrides padding/appearance on color inputs, leaving the
 * native color swatch with no room to render.
 *
 * Instead we render a real swatch <label> sized exactly how we want, with
 * the actual <input type="color"> overlaid invisibly inside it. Clicking
 * the swatch still opens the OS color picker (because the label points to
 * the input via htmlFor).
 */
function ColorField({ label, name, value, onChange, defaultHex }) {
    const safe = HEX.test(value || '') ? value : defaultHex;
    const inputId = `color-${name}`;

    return (
        <div className="space-y-2">
            <label htmlFor={inputId} className="block text-eyebrow font-semibold uppercase text-ink-muted">
                {label}
            </label>
            <div className="flex items-center gap-3">
                <label
                    htmlFor={inputId}
                    className="relative h-11 w-11 flex-shrink-0 rounded-xl border border-border-warm overflow-hidden cursor-pointer shadow-sm hover:ring-2 hover:ring-terracotta/30 transition-shadow"
                    style={{ backgroundColor: safe }}
                    aria-label={`Pick ${label} color`}
                    title="Click to choose a color"
                >
                    <input
                        id={inputId}
                        type="color"
                        value={safe}
                        onChange={(e) => onChange(name, e.target.value.toUpperCase())}
                        className="absolute inset-0 h-full w-full opacity-0 cursor-pointer"
                    />
                </label>
                <input
                    type="text"
                    value={value || ''}
                    placeholder={defaultHex}
                    onChange={(e) => onChange(name, e.target.value.toUpperCase())}
                    className="flex-1 min-w-0 rounded-xl border-border-warm bg-surface text-sm font-mono text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                    spellCheck={false}
                    autoComplete="off"
                />
                {value && value !== defaultHex && (
                    <button
                        type="button"
                        onClick={() => onChange(name, '')}
                        className="text-xs text-ink-muted hover:text-terracotta underline-offset-2 hover:underline whitespace-nowrap"
                        title="Restore default color"
                    >
                        Reset
                    </button>
                )}
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

    // If the user navigated away without saving and is now back on this page,
    // hydrate the form from sessionStorage so the preview matches the in-form
    // values. Without this the form would re-init from `brand.color_*` (last
    // saved) and the next useEffect would overwrite their unsaved tweaks.
    const hydratedFromPreview = useRef(false);
    useEffect(() => {
        if (hydratedFromPreview.current) return;
        hydratedFromPreview.current = true;
        const stash = readPreview();
        if (!stash) return;
        if (stash.color_terracotta && stash.color_terracotta !== brand.color_terracotta) {
            setData('color_terracotta', stash.color_terracotta);
        }
        if (stash.color_forest && stash.color_forest !== brand.color_forest) {
            setData('color_forest', stash.color_forest);
        }
        if (stash.color_mustard && stash.color_mustard !== brand.color_mustard) {
            setData('color_mustard', stash.color_mustard);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    /**
     * Live preview that survives across pages: persist the in-form accent
     * values to sessionStorage and push them onto :root as inline CSS
     * variables. The <BrandPreviewWatcher /> mounted at the app root reads
     * the same sessionStorage entry on every Inertia visit, so the user can
     * navigate to Invoices / Dashboard / Reports and verify the look before
     * saving.
     *
     * Mustard / forest / terracotta each get THREE variables: base, -dark
     * (used by `hover:bg-terracotta-dark` etc.) and -light (dark-mode hover).
     * Variants are derived in HSL space so changing terracotta also changes
     * the hover/pressed states automatically — no extra fields needed.
     *
     * Tailwind's `bg-terracotta` resolves via
     *   rgb(var(--color-terracotta) / <alpha-value>)
     * so the sidebar highlight, primary buttons and pills repaint instantly.
     */
    useEffect(() => {
        const preview = {
            color_terracotta: data.color_terracotta,
            color_forest: data.color_forest,
            color_mustard: data.color_mustard,
        };
        writePreview(preview);
        applyBrandPreview(preview);
    }, [data.color_terracotta, data.color_forest, data.color_mustard]);

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.branding.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // The persisted Blade <style> now matches the form values, so
                // the preview is no longer needed. Clear it before reload so
                // we don't double-apply on the next visit.
                clearPreview();
                window.location.reload();
            },
        });
    };

    const resetToDefaults = () => {
        if (!confirm('Reset all branding to BukuCloud defaults? This clears logos and color overrides.')) return;
        setData('reset', true);
        post(route('admin.branding.update'), {
            preserveScroll: true,
            onSuccess: () => {
                clearPreview();
                window.location.reload();
            },
        });
    };

    const discardPreview = () => {
        // Revert form fields to last saved values; the useEffect above will
        // pick this up, write the (now-empty) preview back to sessionStorage
        // and remove the inline CSS overrides.
        setData('color_terracotta', brand.color_terracotta || '');
        setData('color_forest', brand.color_forest || '');
        setData('color_mustard', brand.color_mustard || '');
        clearPreview();
    };

    const isPreviewing =
        (data.color_terracotta || '') !== (brand.color_terracotta || '') ||
        (data.color_forest || '') !== (brand.color_forest || '') ||
        (data.color_mustard || '') !== (brand.color_mustard || '');

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

                    <div className="rounded-2xl border border-border-warm p-6 bg-cream space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <p className="text-eyebrow font-semibold uppercase text-ink-muted">Live preview</p>
                                <p className="text-xs text-ink-muted mt-0.5">
                                    These colors apply to <strong>every page</strong> while you're tweaking — open Dashboard or Invoices in another tab to see them in context. Hover states (darker / lighter shades) auto-derive from each base color.
                                </p>
                            </div>
                            {isPreviewing && (
                                <button
                                    type="button"
                                    onClick={discardPreview}
                                    className="text-xs text-ink-muted hover:text-terracotta underline-offset-2 hover:underline whitespace-nowrap"
                                    title="Revert to last saved colors"
                                >
                                    Discard preview
                                </button>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <button
                                type="button"
                                className="px-5 py-2.5 rounded-xl text-white text-sm font-semibold bg-terracotta hover:bg-terracotta-dark transition-colors"
                            >
                                Primary action
                            </button>
                            <span className="px-3 py-1 rounded-full text-eyebrow font-semibold uppercase border bg-forest/10 text-forest border-forest/30">
                                Paid
                            </span>
                            <span className="px-3 py-1 rounded-full text-eyebrow font-semibold uppercase border bg-mustard/15 text-ink border-mustard/40">
                                Premium
                            </span>
                            <button
                                type="button"
                                className="px-4 py-2 rounded-xl text-sm font-semibold border border-terracotta/40 text-terracotta hover:bg-terracotta/10 transition-colors"
                            >
                                Hover me
                            </button>
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
