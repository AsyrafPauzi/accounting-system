import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const STATUS_BADGE = {
    valid:        { label: 'Active',         cls: 'bg-sage/15 text-sage-dark border-sage/30' },
    expired:      { label: 'Expired',        cls: 'bg-terracotta/15 text-terracotta-dark border-terracotta/30' },
    revoked:      { label: 'Revoked',        cls: 'bg-terracotta text-cream border-terracotta' },
    missing:      { label: 'No license',     cls: 'bg-mustard/15 text-ink border-mustard/30' },
    unconfigured: { label: 'Unconfigured',   cls: 'bg-mustard/15 text-ink border-mustard/30' },
    malformed:    { label: 'Malformed key',  cls: 'bg-terracotta/15 text-terracotta-dark border-terracotta/30' },
    bad_signature:{ label: 'Bad signature',  cls: 'bg-terracotta/15 text-terracotta-dark border-terracotta/30' },
};

const TIER_LABEL = {
    'self-hosted-standard':   'Standard (single tenant)',
    'self-hosted-enterprise': 'Enterprise (firm + clients)',
};

const FEATURE_LABEL = {
    'practice.console': 'Accountant console (Practice)',
    'tenants.create':   'Create additional client tenants',
};

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};

const formatRelativeAgo = (iso) => {
    if (!iso) return 'Never';
    const ts = new Date(iso).getTime();
    const days = Math.round((Date.now() - ts) / 86_400_000);
    if (days <= 0) return 'today';
    if (days === 1) return 'yesterday';
    if (days < 30) return `${days} days ago`;
    if (days < 365) return `${Math.round(days / 30)} months ago`;
    return `${Math.round(days / 365)} years ago`;
};

const Stat = ({ label, value, hint }) => (
    <div className="flex flex-col gap-1">
        <p className="text-eyebrow uppercase font-semibold text-ink-muted">{label}</p>
        <p className="text-lg font-medium text-ink">{value}</p>
        {hint && <p className="text-xs text-ink-muted">{hint}</p>}
    </div>
);

export default function PlanSelfHosted({ auth, license, usage, version, renewal }) {
    const badge = STATUS_BADGE[license.status] ?? STATUS_BADGE.missing;

    // Three expiry states drive the colour of the headline card:
    //   1. perpetual         → neutral
    //   2. expires within 30 → mustard / warn
    //   3. expired           → terracotta / danger
    let expiryTone = 'border-border-warm bg-surface';
    let expiryHeadline = 'License is active';
    let expirySubline = '';
    if (license.is_expired) {
        expiryTone = 'border-terracotta/40 bg-terracotta/5';
        expiryHeadline = 'License has expired';
        expirySubline = license.days_left !== null
            ? `${Math.abs(license.days_left)} days overdue. Contact ${renewal.vendor_name} to reactivate.`
            : `Contact ${renewal.vendor_name} to reactivate.`;
    } else if (license.is_perpetual) {
        expiryHeadline = 'Perpetual license';
        expirySubline = 'No expiry date. Updates and support follow your contract terms.';
    } else if (license.days_left !== null) {
        if (license.days_left <= 30) {
            expiryTone = 'border-mustard/40 bg-mustard/10';
            expiryHeadline = `Renewal due in ${license.days_left} day${license.days_left === 1 ? '' : 's'}`;
            expirySubline = `Reach out to ${renewal.vendor_name} before ${formatDate(license.expires_at)} to avoid service interruption.`;
        } else {
            expiryHeadline = `Renews in ${license.days_left} days`;
            expirySubline = `Next renewal date: ${formatDate(license.expires_at)}.`;
        }
    }

    const heartbeatRel = formatRelativeAgo(license.last_heartbeat);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Self-hosted</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            License & usage
                        </h1>
                        <p className="text-ink-muted text-sm">
                            Your entitlement, expiry, and current usage. License changes are made by re-issuing — there&apos;s no in-app upgrade flow.
                        </p>
                    </div>
                    <Link
                        href={route('settings.company')}
                        className="text-sm font-semibold text-terracotta hover:text-terracotta"
                    >
                        ← Company settings
                    </Link>
                </div>
            }
        >
            <Head title="License & Usage" />

            <div className="max-w-5xl space-y-6">
                {/* Headline expiry / renewal card */}
                <div className={`rounded-2xl border p-6 sm:p-8 ${expiryTone}`}>
                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ${badge.cls}`}>
                                    {badge.label}
                                </span>
                                {license.plan_tier && (
                                    <span className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                        {TIER_LABEL[license.plan_tier] ?? license.plan_tier}
                                    </span>
                                )}
                            </div>
                            <h2 className="font-display text-xl font-medium text-ink">{expiryHeadline}</h2>
                            {expirySubline && (
                                <p className="text-sm text-ink-muted mt-1">{expirySubline}</p>
                            )}
                        </div>
                        {(renewal.contact_email || renewal.contact_url) && (
                            <div className="flex flex-col gap-2 shrink-0">
                                {renewal.contact_email && (
                                    <a
                                        href={`mailto:${renewal.contact_email}?subject=License%20renewal%20-%20${encodeURIComponent(license.customer_name ?? '')}`}
                                        className="px-4 py-2 rounded-2xl text-sm font-semibold bg-ink text-cream hover:bg-ink-muted text-center"
                                    >
                                        Contact {renewal.vendor_name}
                                    </a>
                                )}
                                {renewal.contact_url && (
                                    <a
                                        href={renewal.contact_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="px-4 py-2 rounded-2xl text-sm font-semibold border border-border-warm text-ink hover:bg-cream/40 text-center"
                                    >
                                        Open billing portal
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* License details */}
                <section className="bg-surface border border-border-warm rounded-2xl p-6 sm:p-8">
                    <h3 className="font-display text-base font-medium text-ink mb-5">License details</h3>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <Stat
                            label="Customer"
                            value={license.customer_name ?? '—'}
                            hint={license.customer_id ? `ID: ${license.customer_id}` : undefined}
                        />
                        <Stat label="Issued" value={formatDate(license.issued_at)} />
                        <Stat
                            label="Expires"
                            value={license.is_perpetual ? 'Never (perpetual)' : formatDate(license.expires_at)}
                            hint={!license.is_perpetual && license.days_left !== null
                                ? (license.days_left >= 0 ? `${license.days_left} days remaining` : `${Math.abs(license.days_left)} days overdue`)
                                : undefined}
                        />
                        <Stat
                            label="User cap"
                            value={license.max_users === 0 ? 'Unlimited' : license.max_users}
                            hint={`Currently using ${usage.user_count} user${usage.user_count === 1 ? '' : 's'}`}
                        />
                        <Stat
                            label="Tenant cap"
                            value={license.max_tenants === 0 ? 'Unlimited' : license.max_tenants}
                            hint={`Currently ${usage.tenant_count} tenant${usage.tenant_count === 1 ? '' : 's'} on this install`}
                        />
                        <Stat
                            label="Last heartbeat"
                            value={heartbeatRel}
                            hint="Used to confirm the install is still licensed."
                        />
                    </dl>
                </section>

                {/* Features unlocked by this license */}
                <section className="bg-surface border border-border-warm rounded-2xl p-6 sm:p-8">
                    <h3 className="font-display text-base font-medium text-ink mb-1">Entitlements</h3>
                    <p className="text-xs text-ink-muted mb-4">
                        Optional features that ship in your license. To enable additional ones, contact your vendor for a re-issue.
                    </p>
                    {license.features.length === 0 ? (
                        <p className="text-sm text-ink-muted italic">
                            Standard tier — core SME features only. No optional add-ons enabled.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {license.features.map((f) => (
                                <li key={f} className="flex items-center gap-3 text-sm">
                                    <span className="inline-block w-1.5 h-1.5 rounded-full bg-sage" />
                                    <span className="text-ink">{FEATURE_LABEL[f] ?? f}</span>
                                    <code className="text-xs text-ink-muted">{f}</code>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Version / update advertisement */}
                {version.latest && (
                    <section className={`rounded-2xl border p-6 sm:p-8 ${
                        version.is_behind ? 'border-mustard/40 bg-mustard/10' : 'border-border-warm bg-surface'
                    }`}>
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 className="font-display text-base font-medium text-ink">
                                    {version.is_behind ? `Update available: ${version.latest}` : 'Up to date'}
                                </h3>
                                <p className="text-sm text-ink-muted mt-1">
                                    Running version <code>{version.current}</code>
                                    {version.is_behind && <> · {renewal.vendor_name} ships patches via your license heartbeat.</>}
                                </p>
                            </div>
                        </div>
                    </section>
                )}

                {/* Renewal copy */}
                <section className="bg-surface border border-border-warm rounded-2xl p-6 sm:p-8">
                    <h3 className="font-display text-base font-medium text-ink mb-2">Need changes?</h3>
                    <p className="text-sm text-ink-muted">
                        There is no in-app upgrade flow on a self-hosted install — your entitlement is fixed for the term you purchased.
                        For more user seats, more client tenants, a longer term, or a tier change (Standard → Enterprise),
                        contact {renewal.vendor_name}. We&apos;ll re-issue a license that you paste into your install.
                    </p>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
