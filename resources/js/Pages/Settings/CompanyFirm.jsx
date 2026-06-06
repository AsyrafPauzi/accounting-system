import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border-border-warm bg-surface text-sm font-medium text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta disabled:bg-surface-alt disabled:text-ink-muted';
const labelClass =
    'block text-eyebrow font-semibold text-ink-muted uppercase';

/**
 * Firm-level company settings — what an accountancy firm-owner sees
 * when they hit /settings/company at the practice console (no client
 * open).
 *
 * The SME tenant form has many more fields (TIN, BRN, financial year,
 * base currency, etc.) because those flow into invoices and reports.
 * A firm has none of those concepts; it has a name, a contact email,
 * and a country. Keeping this surface tight prevents accidental
 * confusion between "my firm" and "my client's company".
 */
export default function CompanyFirm({ auth, firm, canEdit = false }) {
    const { flash = {} } = usePage().props;
    const { data, setData, patch, processing, errors } = useForm({
        name:          firm.name || '',
        contact_email: firm.contact_email || '',
        contact_phone: firm.contact_phone || '',
        country:       firm.country || 'Malaysia',
    });

    const submit = (e) => {
        e.preventDefault();
        if (!canEdit) return;
        patch(route('settings.company.update'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Practice</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Firm settings
                        </h1>
                        <p className="text-ink-muted text-sm">
                            Your accounting firm&apos;s display name and primary contact details.
                            Editing a client&apos;s books? <Link href={route('practice.dashboard')} className="text-terracotta font-semibold">Open the client first</Link> — this page edits your firm only.
                        </p>
                    </div>
                    <Link
                        href={route('practice.dashboard')}
                        className="text-sm font-semibold text-terracotta hover:text-terracotta whitespace-nowrap"
                    >
                        ← Practice console
                    </Link>
                </div>
            }
        >
            <Head title="Firm settings" />

            <div className="max-w-3xl space-y-6">
                {flash.success && (
                    <div className="bg-forest/10 border border-forest/30 rounded-2xl px-6 py-4 text-sm text-forest-dark">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-6 py-4 text-sm text-terracotta-dark">
                        {flash.error}
                    </div>
                )}

                {!canEdit && (
                    <div className="bg-mustard/10 border border-mustard/40 rounded-2xl px-6 py-4 text-sm text-ink">
                        Read-only — only the firm owner can edit these details. Ask them to make
                        changes if you need anything updated.
                    </div>
                )}

                <form onSubmit={submit} className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-6 sm:p-8 space-y-6">
                    <div>
                        <label htmlFor="name" className={labelClass}>Firm name</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={inputClass}
                            disabled={!canEdit}
                            required
                        />
                        {errors.name && <p className="mt-1 text-xs text-terracotta">{errors.name}</p>}
                        <p className="mt-1 text-xs text-ink-muted">
                            What clients see in invitation emails and on your practice console.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label htmlFor="contact_email" className={labelClass}>Contact email</label>
                            <input
                                id="contact_email"
                                type="email"
                                value={data.contact_email}
                                onChange={(e) => setData('contact_email', e.target.value)}
                                className={inputClass}
                                disabled={!canEdit}
                                placeholder="hello@yourfirm.com"
                            />
                            {errors.contact_email && <p className="mt-1 text-xs text-terracotta">{errors.contact_email}</p>}
                        </div>

                        <div>
                            <label htmlFor="contact_phone" className={labelClass}>Contact phone</label>
                            <input
                                id="contact_phone"
                                type="text"
                                value={data.contact_phone}
                                onChange={(e) => setData('contact_phone', e.target.value)}
                                className={inputClass}
                                disabled={!canEdit}
                                placeholder="+60 12 345 6789"
                            />
                            {errors.contact_phone && <p className="mt-1 text-xs text-terracotta">{errors.contact_phone}</p>}
                        </div>
                    </div>

                    <div>
                        <label htmlFor="country" className={labelClass}>Country</label>
                        <input
                            id="country"
                            type="text"
                            value={data.country}
                            onChange={(e) => setData('country', e.target.value)}
                            className={inputClass}
                            disabled={!canEdit}
                        />
                        {errors.country && <p className="mt-1 text-xs text-terracotta">{errors.country}</p>}
                    </div>

                    {canEdit && (
                        <div className="flex justify-end gap-3 pt-2 border-t border-border-warm/60">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-60 transition-colors"
                            >
                                {processing ? 'Saving…' : 'Save changes'}
                            </button>
                        </div>
                    )}
                </form>

                {/* Hint card pointing the user to where the SME-style
                    fields live. Lots of accountants will look here for
                    their *clients'* TIN/BRN — make the path obvious. */}
                <div className="bg-cream rounded-2xl border border-border-warm/50 p-5">
                    <p className="text-eyebrow font-semibold uppercase text-ink-muted mb-1">
                        Looking for client details?
                    </p>
                    <p className="text-sm text-ink">
                        TIN, business registration number, financial year, base currency and the
                        full address fields belong to each client&apos;s books. Open a client from the
                        practice console and revisit{' '}
                        <span className="font-semibold">Settings → Company</span>{' '}
                        — that&apos;s where those fields live.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
