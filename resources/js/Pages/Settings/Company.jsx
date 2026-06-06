import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border-border-warm bg-surface text-sm font-medium text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta disabled:bg-surface-alt disabled:text-ink-muted';
const labelClass =
    'block text-eyebrow font-semibold text-ink-muted uppercase';

export default function Company({ auth, company, canEdit = false }) {
    const { available_locales = [] } = usePage().props;
    const planPermissions = auth?.planPermissions ?? {};
    const canViewTeam = auth?.teamPermissions?.view && planPermissions['users.view'];
    const { data, setData, patch, processing, errors } = useForm({
        legal_name: company.legal_name || '',
        display_name: company.display_name || '',
        tin: company.tin || '',
        brn: company.brn || '',
        street: company.street || '',
        city: company.city || '',
        state: company.state || '',
        postcode: company.postcode || '',
        country: company.country || 'Malaysia',
        phone: company.phone || '',
        email: company.email || '',
        website: company.website || '',
        base_currency: company.base_currency || 'MYR',
        financial_year_start_month: company.financial_year_start_month || 1,
        language: company.language || 'en',
    });

    // `canEdit` is the authoritative gate computed server-side. It
    // covers tenant admins / super-admins on their own org AND
    // firm-users acting on a client tenant with admin permission_level.
    // Falling back to the role check would lock firm-users out.
    const isAdmin = canEdit;

    const submit = (e) => {
        e.preventDefault();
        if (!isAdmin) return;
        patch(route('settings.company.update'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Settings</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Company
                        </h1>
                        <p className="text-ink-muted text-sm">
                            Legal, contact and accounting details that flow through your books.
                        </p>
                    </div>
                    {canViewTeam && (
                        <Link
                            href={route('settings.team.index')}
                            className="text-sm font-semibold text-terracotta hover:text-terracotta whitespace-nowrap"
                        >
                            Team & Roles →
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Company Settings" />

            {!isAdmin && (
                <div className="mb-6 bg-mustard/15 border border-mustard/40 rounded-2xl p-4 flex items-center gap-3 text-mustard">
                    <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <p className="text-sm font-medium">
                        Read-only: tenant admins (or firm-users with admin access) can modify company settings.
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                        Identity
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Legal name</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.legal_name}
                                onChange={(e) => setData('legal_name', e.target.value)}
                                required
                                disabled={!isAdmin}
                            />
                            {errors.legal_name && (
                                <p className="text-terracotta text-xs mt-1">{errors.legal_name}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>Display name</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.display_name}
                                onChange={(e) => setData('display_name', e.target.value)}
                                disabled={!isAdmin}
                            />
                            {errors.display_name && (
                                <p className="text-terracotta text-xs mt-1">{errors.display_name}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>LHDN TIN</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.tin}
                                onChange={(e) => setData('tin', e.target.value)}
                                disabled={!isAdmin}
                            />
                            {errors.tin && (
                                <p className="text-terracotta text-xs mt-1">{errors.tin}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>SSM BRN</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.brn}
                                onChange={(e) => setData('brn', e.target.value)}
                                disabled={!isAdmin}
                            />
                            {errors.brn && (
                                <p className="text-terracotta text-xs mt-1">{errors.brn}</p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                        Address & contact
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <label className={labelClass}>Street</label>
                            <textarea
                                className={`${inputClass} h-20 resize-none`}
                                value={data.street}
                                onChange={(e) => setData('street', e.target.value)}
                                disabled={!isAdmin}
                            />
                            {errors.street && (
                                <p className="text-terracotta text-xs mt-1">{errors.street}</p>
                            )}
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className={labelClass}>City</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.city}
                                    onChange={(e) => setData('city', e.target.value)}
                                    disabled={!isAdmin}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>State</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.state}
                                    onChange={(e) => setData('state', e.target.value)}
                                    disabled={!isAdmin}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Postcode</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.postcode}
                                    onChange={(e) => setData('postcode', e.target.value)}
                                    disabled={!isAdmin}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Country</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                    disabled={!isAdmin}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className={labelClass}>Phone</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    disabled={!isAdmin}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Company Email</label>
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    disabled={!isAdmin}
                                />
                                {errors.email && (
                                    <p className="text-terracotta text-xs mt-1">{errors.email}</p>
                                )}
                            </div>
                            <div>
                                <label className={labelClass}>Website</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    disabled={!isAdmin}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">
                        Accounting
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className={labelClass}>Base currency</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.base_currency}
                                onChange={(e) => setData('base_currency', e.target.value)}
                                disabled={!isAdmin}
                            />
                            {errors.base_currency && (
                                <p className="text-terracotta text-xs mt-1">{errors.base_currency}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>Financial year start (month)</label>
                            <select
                                className={inputClass}
                                value={data.financial_year_start_month}
                                onChange={(e) =>
                                    setData('financial_year_start_month', Number(e.target.value))
                                }
                                disabled={!isAdmin}
                            >
                                {[...Array(12)].map((_, idx) => (
                                    <option key={idx + 1} value={idx + 1}>
                                        {idx + 1}
                                    </option>
                                ))}
                            </select>
                            {errors.financial_year_start_month && (
                                <p className="text-terracotta text-xs mt-1">
                                    {errors.financial_year_start_month}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                    <h3 className="text-eyebrow font-semibold text-ink uppercase">Language</h3>
                    <p className="text-sm text-ink-muted">
                        Used across the app interface. Emails, invoices and PDFs stay in English so external recipients see consistent wording.
                    </p>
                    <div className="max-w-sm">
                        <label className={labelClass}>Display language</label>
                        <select
                            className={inputClass}
                            value={data.language}
                            onChange={(e) => setData('language', e.target.value)}
                            disabled={!isAdmin}
                        >
                            {available_locales.map((loc) => (
                                <option key={loc.code} value={loc.code}>{loc.label}</option>
                            ))}
                        </select>
                        {errors.language && (
                            <p className="text-terracotta text-xs mt-1">{errors.language}</p>
                        )}
                    </div>
                </div>

                {isAdmin && (
                    <div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark dark:hover:bg-terracotta-light disabled:opacity-50 transition-colors"
                        >
                            {processing ? 'Saving…' : 'Save company settings'}
                        </button>
                    </div>
                )}
            </form>
        </AuthenticatedLayout>
    );
}

