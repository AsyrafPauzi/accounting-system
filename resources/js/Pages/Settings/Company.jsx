import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

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
        msic_code: company.msic_code || '',
        sst_number: company.sst_number || '',
        invoice_brand_color: company.invoice_brand_color || '#0f172a',
        invoice_logo_url: company.invoice_logo_url || '',
        default_invoice_customer_notes: company.default_invoice_customer_notes || '',
        default_estimate_customer_notes: company.default_estimate_customer_notes || '',
        reminder_offsets: company.reminder_offsets || [-14, -7, -3, 0, 3, 7, 14],
        myinvois_client_id: company.myinvois_client_id || '',
        myinvois_client_secret: '',
        myinvois_environment: company.myinvois_environment || 'preprod',
        myinvois_id_type: company.myinvois_id_type || 'BRN',
        myinvois_id_value: company.myinvois_id_value || '',
        myinvois_cert: null,
        myinvois_cert_password: '',
        toyyibpay_category_code: company.toyyibpay_category_code || '',
        toyyibpay_secret_key: '',
        late_fee_percent: company.late_fee_percent ?? 1.5,
        show_goods_flow: company.show_goods_flow !== false,
        invoice_gateway: company.invoice_gateway || 'toyyibpay',
        billplz_collection_id: company.billplz_collection_id || '',
        billplz_secret_key: '',
        billplz_xsignature_key: '',
        billplz_sandbox: company.billplz_sandbox !== false,
        commercepay_username: company.commercepay_username || '',
        commercepay_password: '',
        commercepay_secret_key: '',
        commercepay_live: Boolean(company.commercepay_live),
    });

    // `canEdit` is the authoritative gate computed server-side. It
    // covers tenant admins / super-admins on their own org AND
    // firm-users acting on a client tenant with admin permission_level.
    // Falling back to the role check would lock firm-users out.
    const isAdmin = canEdit;

    const submit = (e) => {
        e.preventDefault();
        if (!isAdmin) return;
        patch(route('settings.company.update'), { forceFormData: true });
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
                    {isAdmin && (
                        <>
                            <Link
                                href={route('settings.document-numbers.edit')}
                                className="text-sm font-semibold text-terracotta hover:text-terracotta whitespace-nowrap"
                            >
                                Document numbering →
                            </Link>
                            <Link
                                href={route('settings.accounting-periods.index')}
                                className="text-sm font-semibold text-terracotta hover:text-terracotta whitespace-nowrap"
                            >
                                Accounting periods →
                            </Link>
                        </>
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

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <div>
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">Invoice PDF &amp; collections</h3>
                        <p className="text-sm text-ink-muted mt-1">Logo, colour, default notes on PDFs. Late fees and reminder days for unpaid invoices.</p>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Invoice brand colour</label>
                            <input type="text" className={inputClass} value={data.invoice_brand_color} onChange={(e) => setData('invoice_brand_color', e.target.value)} disabled={!isAdmin} placeholder="#0f172a" />
                        </div>
                        <div>
                            <label className={labelClass}>Logo URL</label>
                            <input type="text" className={inputClass} value={data.invoice_logo_url} onChange={(e) => setData('invoice_logo_url', e.target.value)} disabled={!isAdmin} placeholder="https://…" />
                        </div>
                        <div>
                            <label className={labelClass}>Late fee percent (overdue)</label>
                            <input type="number" step="0.01" min="0" max="100" className={inputClass} value={data.late_fee_percent} onChange={(e) => setData('late_fee_percent', e.target.value)} disabled={!isAdmin} />
                            <p className="mt-1 text-xs text-ink-muted">Used when you issue a late-interest invoice from an overdue invoice.</p>
                        </div>
                    </div>
                    <div>
                        <label className={labelClass}>Default invoice notes (on PDF)</label>
                        <textarea
                            className={`${inputClass} resize-y min-h-[5.5rem]`}
                            rows={3}
                            value={data.default_invoice_customer_notes}
                            onChange={(e) => setData('default_invoice_customer_notes', e.target.value)}
                            disabled={!isAdmin}
                            placeholder="Payment instructions, thank you message…"
                        />
                        <p className="mt-1 text-xs text-ink-muted">Pre-filled on every new invoice. You can still edit or clear it per invoice.</p>
                        {errors.default_invoice_customer_notes && (
                            <p className="text-terracotta text-xs mt-1">{errors.default_invoice_customer_notes}</p>
                        )}
                    </div>
                    <div>
                        <label className={labelClass}>Default estimate notes (on PDF)</label>
                        <textarea
                            className={`${inputClass} resize-y min-h-[5.5rem]`}
                            rows={3}
                            value={data.default_estimate_customer_notes}
                            onChange={(e) => setData('default_estimate_customer_notes', e.target.value)}
                            disabled={!isAdmin}
                            placeholder="Payment terms, delivery details, thank you message…"
                        />
                        <p className="mt-1 text-xs text-ink-muted">Pre-filled on every new estimate. You can still edit or clear it per quote.</p>
                        {errors.default_estimate_customer_notes && (
                            <p className="text-terracotta text-xs mt-1">{errors.default_estimate_customer_notes}</p>
                        )}
                    </div>
                    <div>
                        <p className={labelClass}>Payment reminders (days vs due date)</p>
                        <div className="mt-2 flex flex-wrap gap-3">
                            {[-14, -7, -3, 0, 3, 7, 14].map((offset) => (
                                <label key={offset} className="inline-flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={(data.reminder_offsets || []).map(Number).includes(offset)}
                                        onChange={(e) => {
                                            const current = (data.reminder_offsets || []).map(Number);
                                            setData('reminder_offsets', e.target.checked ? [...current, offset] : current.filter((n) => n !== offset));
                                        }}
                                        disabled={!isAdmin}
                                    />
                                    {offset === 0 ? 'On due' : offset < 0 ? `${Math.abs(offset)} days before` : `${offset} days after`}
                                </label>
                            ))}
                        </div>
                    </div>
                    <label className="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.show_goods_flow} onChange={(e) => setData('show_goods_flow', e.target.checked)} disabled={!isAdmin} />
                        Show sales orders and delivery orders (traders). Turn off for service businesses.
                    </label>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <div>
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">MyInvois</h3>
                        <p className="text-sm text-ink-muted mt-1">MSIC and SST are required before you can submit e-invoices. Preprod is LHDN sandbox; live needs production Client ID and Secret.</p>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>MSIC code</label>
                            <input type="text" className={inputClass} value={data.msic_code} onChange={(e) => setData('msic_code', e.target.value)} disabled={!isAdmin} placeholder="62011" />
                        </div>
                        <div>
                            <label className={labelClass}>SST number</label>
                            <input type="text" className={inputClass} value={data.sst_number} onChange={(e) => setData('sst_number', e.target.value)} disabled={!isAdmin} />
                        </div>
                        <div>
                            <label className={labelClass}>Client ID</label>
                            <input type="text" className={inputClass} value={data.myinvois_client_id} onChange={(e) => setData('myinvois_client_id', e.target.value)} disabled={!isAdmin} />
                        </div>
                        <div>
                            <label className={labelClass}>Client secret {company.myinvois_secret_set ? '(saved — leave blank to keep)' : ''}</label>
                            <input type="password" className={inputClass} value={data.myinvois_client_secret} onChange={(e) => setData('myinvois_client_secret', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                        </div>
                        <div>
                            <label className={labelClass}>ID type</label>
                            <select className={inputClass} value={data.myinvois_id_type} onChange={(e) => setData('myinvois_id_type', e.target.value)} disabled={!isAdmin}>
                                <option value="BRN">BRN (SSM)</option>
                                <option value="NRIC">NRIC</option>
                                <option value="PASSPORT">Passport</option>
                                <option value="ARMY">Army</option>
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>ID number</label>
                            <input type="text" className={inputClass} value={data.myinvois_id_value} onChange={(e) => setData('myinvois_id_value', e.target.value)} disabled={!isAdmin} placeholder={data.myinvois_id_type === 'PASSPORT' || data.myinvois_id_type === 'NRIC' ? 'NA if that is what MyInvois shows' : 'SSM number'} />
                        </div>
                        <div>
                            <label className={labelClass}>Signing certificate (.p12) {company.myinvois_cert_set ? '(saved — upload to replace)' : ''}</label>
                            <input type="file" accept=".p12,.pfx" className={inputClass} disabled={!isAdmin} onChange={(e) => setData('myinvois_cert', e.target.files?.[0] ?? null)} />
                            <p className="mt-1 text-xs text-ink-muted">Needed for e-Invoice v1.1. Leave empty to keep unsigned v1.0 on sandbox.</p>
                        </div>
                        <div>
                            <label className={labelClass}>Certificate password {company.myinvois_cert_set ? '(leave blank to keep)' : ''}</label>
                            <input type="password" className={inputClass} value={data.myinvois_cert_password} onChange={(e) => setData('myinvois_cert_password', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                        </div>
                    </div>
                    <p className="text-xs text-ink-muted">
                        Copy TIN and ID type from your{' '}
                        <a className="text-terracotta" href="https://preprod-profile.myinvois.hasil.gov.my/TaxpayerProfile" target="_blank" rel="noreferrer">taxpayer profile</a>
                        . Sole-prop IG TIN is often Passport / NA, not SSM BRN.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={!isAdmin}
                            onClick={() => setData('myinvois_environment', 'preprod')}
                            className={`px-4 py-2 rounded-xl text-sm font-semibold border ${data.myinvois_environment === 'preprod' ? 'bg-terracotta text-white border-terracotta' : 'bg-surface border-border-warm'}`}
                        >
                            Preprod (sandbox)
                        </button>
                        <button
                            type="button"
                            disabled={!isAdmin}
                            onClick={() => setData('myinvois_environment', 'production')}
                            className={`px-4 py-2 rounded-xl text-sm font-semibold border ${data.myinvois_environment === 'production' ? 'bg-terracotta text-white border-terracotta' : 'bg-surface border-border-warm'}`}
                        >
                            Live (production)
                        </button>
                        {isAdmin && (
                            <button
                                type="button"
                                className="px-4 py-2 rounded-xl text-sm font-semibold border border-border-warm bg-surface"
                                onClick={() => router.post(route('settings.company.myinvois-test'), { myinvois_environment: data.myinvois_environment })}
                            >
                                Test MyInvois login
                            </button>
                        )}
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm space-y-4">
                    <div>
                        <h3 className="text-sm font-semibold text-ink uppercase tracking-wider">Invoice Pay Now</h3>
                        <p className="text-sm text-ink-muted mt-1">
                            Customers pay from the invoice link. Settlement goes to <strong>your</strong> merchant account — not BukuCloud. Use one active gateway.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-4 text-sm">
                        {['toyyibpay', 'billplz', 'commercepay'].map((gw) => (
                            <label key={gw} className="inline-flex items-center gap-2">
                                <input type="radio" name="invoice_gateway" value={gw} checked={data.invoice_gateway === gw} onChange={() => setData('invoice_gateway', gw)} disabled={!isAdmin} />
                                {gw === 'toyyibpay' ? 'ToyyibPay' : gw === 'billplz' ? 'Billplz' : 'CommercePay'}
                            </label>
                        ))}
                    </div>
                    {data.invoice_gateway === 'toyyibpay' && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>ToyyibPay category code</label>
                                <input type="text" className={inputClass} value={data.toyyibpay_category_code} onChange={(e) => setData('toyyibpay_category_code', e.target.value)} disabled={!isAdmin} />
                            </div>
                            <div>
                                <label className={labelClass}>ToyyibPay secret key {company.toyyibpay_secret_set ? '(saved — leave blank to keep)' : ''}</label>
                                <input type="password" className={inputClass} value={data.toyyibpay_secret_key} onChange={(e) => setData('toyyibpay_secret_key', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                            </div>
                        </div>
                    )}
                    {data.invoice_gateway === 'billplz' && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Billplz collection ID</label>
                                <input type="text" className={inputClass} value={data.billplz_collection_id} onChange={(e) => setData('billplz_collection_id', e.target.value)} disabled={!isAdmin} />
                            </div>
                            <div>
                                <label className={labelClass}>Billplz secret key {company.billplz_secret_set ? '(saved — leave blank to keep)' : ''}</label>
                                <input type="password" className={inputClass} value={data.billplz_secret_key} onChange={(e) => setData('billplz_secret_key', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                            </div>
                            <div>
                                <label className={labelClass}>X-Signature key {company.billplz_xsignature_set ? '(saved — leave blank to keep)' : ''}</label>
                                <input type="password" className={inputClass} value={data.billplz_xsignature_key} onChange={(e) => setData('billplz_xsignature_key', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                            </div>
                            <label className="inline-flex items-center gap-2 text-sm mt-6">
                                <input type="checkbox" checked={data.billplz_sandbox} onChange={(e) => setData('billplz_sandbox', e.target.checked)} disabled={!isAdmin} />
                                Use Billplz sandbox
                            </label>
                        </div>
                    )}
                    {data.invoice_gateway === 'commercepay' && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>CommercePay username</label>
                                <input type="text" className={inputClass} value={data.commercepay_username} onChange={(e) => setData('commercepay_username', e.target.value)} disabled={!isAdmin} />
                            </div>
                            <div>
                                <label className={labelClass}>Password {company.commercepay_password_set ? '(saved — leave blank to keep)' : ''}</label>
                                <input type="password" className={inputClass} value={data.commercepay_password} onChange={(e) => setData('commercepay_password', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                            </div>
                            <div>
                                <label className={labelClass}>Secret key (cap-signature) {company.commercepay_secret_set ? '(saved — leave blank to keep)' : ''}</label>
                                <input type="password" className={inputClass} value={data.commercepay_secret_key} onChange={(e) => setData('commercepay_secret_key', e.target.value)} disabled={!isAdmin} autoComplete="new-password" />
                            </div>
                            <label className="inline-flex items-center gap-2 text-sm mt-6">
                                <input type="checkbox" checked={data.commercepay_live} onChange={(e) => setData('commercepay_live', e.target.checked)} disabled={!isAdmin} />
                                Live (production) API
                            </label>
                        </div>
                    )}
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

