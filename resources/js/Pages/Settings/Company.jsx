import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border-slate-200 text-sm font-medium text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500';
const labelClass =
    'block text-[10px] font-semibold text-slate-400 uppercase tracking-wider';

export default function Company({ auth, company }) {
    const canViewTeam = auth?.teamPermissions?.view;
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
        website: company.website || '',
        base_currency: company.base_currency || 'MYR',
        financial_year_start_month: company.financial_year_start_month || 1,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('settings.company.update'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">
                            Company settings
                        </h2>
                        <p className="text-slate-500 text-sm font-medium mt-1">
                            Maintain legal, contact and accounting information for this tenant.
                        </p>
                    </div>
                    {canViewTeam && (
                        <Link
                            href={route('settings.team.index')}
                            className="text-sm font-semibold text-blue-600 hover:text-blue-700 whitespace-nowrap"
                        >
                            Team & roles →
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Company Settings" />

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider">
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
                            />
                            {errors.legal_name && (
                                <p className="text-rose-500 text-xs mt-1">{errors.legal_name}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>Display name</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.display_name}
                                onChange={(e) => setData('display_name', e.target.value)}
                            />
                            {errors.display_name && (
                                <p className="text-rose-500 text-xs mt-1">{errors.display_name}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>LHDN TIN</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.tin}
                                onChange={(e) => setData('tin', e.target.value)}
                            />
                            {errors.tin && (
                                <p className="text-rose-500 text-xs mt-1">{errors.tin}</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>SSM BRN</label>
                            <input
                                type="text"
                                className={inputClass}
                                value={data.brn}
                                onChange={(e) => setData('brn', e.target.value)}
                            />
                            {errors.brn && (
                                <p className="text-rose-500 text-xs mt-1">{errors.brn}</p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider">
                        Address & contact
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <label className={labelClass}>Street</label>
                            <textarea
                                className={`${inputClass} h-20 resize-none`}
                                value={data.street}
                                onChange={(e) => setData('street', e.target.value)}
                            />
                            {errors.street && (
                                <p className="text-rose-500 text-xs mt-1">{errors.street}</p>
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
                                />
                            </div>
                            <div>
                                <label className={labelClass}>State</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.state}
                                    onChange={(e) => setData('state', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Postcode</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.postcode}
                                    onChange={(e) => setData('postcode', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Country</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Phone</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Website</label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <h3 className="text-sm font-semibold text-slate-800 uppercase tracking-wider">
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
                            />
                            {errors.base_currency && (
                                <p className="text-rose-500 text-xs mt-1">{errors.base_currency}</p>
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
                            >
                                {[...Array(12)].map((_, idx) => (
                                    <option key={idx + 1} value={idx + 1}>
                                        {idx + 1}
                                    </option>
                                ))}
                            </select>
                            {errors.financial_year_start_month && (
                                <p className="text-rose-500 text-xs mt-1">
                                    {errors.financial_year_start_month}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25"
                    >
                        {processing ? 'Saving...' : 'Save company settings'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

