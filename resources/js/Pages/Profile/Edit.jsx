import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import AppearanceForm from './Partials/AppearanceForm';

/**
 * Account-level profile settings. PDPA-mandated rights (export, erase)
 * and security controls (2FA) are surfaced as link cards here so a
 * user can find them in one place — the dedicated pages do the actual
 * heavy lifting (QR codes, multi-step jobs, password challenge).
 */
export default function Edit({ mustVerifyEmail, status, security = {} }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Profile</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">Your account</h1>
                    <p className="text-ink-muted text-sm">Manage how you sign in and how the app feels.</p>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="max-w-4xl space-y-6">
                <Section>
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </Section>

                <Section>
                    <AppearanceForm className="max-w-xl" />
                </Section>

                <Section>
                    <UpdatePasswordForm className="max-w-xl" />
                </Section>

                {/* Security & data-rights hub. PDPA section §11–13 says
                    each user must be able to find their export and erase
                    controls without our help — keeping them on the
                    main account page satisfies "easy access". */}
                <Section title="Security & data">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <ActionCard
                            title="Two-factor auth"
                            description={
                                security.two_factor_enabled
                                    ? "An authenticator app is required at sign-in."
                                    : "Add an authenticator app for an extra step at sign-in."
                            }
                            statusLabel={security.two_factor_enabled ? 'Enabled' : 'Not enabled'}
                            statusTone={security.two_factor_enabled ? 'ok' : 'warn'}
                            actionLabel={security.two_factor_enabled ? 'Manage' : 'Enable'}
                            href={route('settings.2fa.show')}
                        />
                        <ActionCard
                            title="Download my data"
                            description="Export everything we hold on you as a CSV/JSON archive."
                            statusLabel="PDPA right"
                            statusTone="default"
                            actionLabel="Open"
                            href={route('settings.data_export.show')}
                        />
                    </div>
                </Section>

                {/* Destructive section — kept distinct visually from the
                    rest. Walks the user through the password challenge
                    and erasure on a dedicated page. */}
                <Section title="Delete account" tone="danger">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p className="text-sm text-ink-muted">
                            Permanently remove your account and personal data. This cannot be undone.
                        </p>
                        <Link
                            href={route('settings.account_erase.show')}
                            className="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-semibold uppercase tracking-wide text-terracotta border border-terracotta/40 hover:bg-terracotta/10 transition-colors shrink-0"
                        >
                            Continue to delete
                        </Link>
                    </div>
                </Section>
            </div>
        </AuthenticatedLayout>
    );
}

function Section({ title, tone = 'default', children }) {
    const toneClasses = tone === 'danger'
        ? 'border-terracotta/30 bg-terracotta/5'
        : 'border-border-warm bg-surface';
    return (
        <div className={`p-6 sm:p-8 rounded-2xl border ${toneClasses}`}>
            {title && (
                <h3 className={`font-display text-lg font-medium tracking-tight mb-4 ${tone === 'danger' ? 'text-terracotta-dark dark:text-terracotta-light' : 'text-ink'}`}>
                    {title}
                </h3>
            )}
            {children}
        </div>
    );
}

function ActionCard({ title, description, statusLabel, statusTone = 'default', actionLabel, href }) {
    const tones = {
        ok:      'bg-forest/10 text-forest-dark dark:text-forest-light border-forest/30',
        warn:    'bg-mustard/15 text-ink border-mustard/40',
        default: 'bg-cream/40 text-ink-muted border-border-warm',
    };
    return (
        <div className="border border-border-warm rounded-xl p-4 flex flex-col gap-3 bg-cream/20">
            <div className="flex items-start justify-between gap-2">
                <h4 className="font-semibold text-ink">{title}</h4>
                {statusLabel && (
                    <span className={`text-eyebrow uppercase font-semibold px-2 py-0.5 rounded-full border ${tones[statusTone] ?? tones.default}`}>
                        {statusLabel}
                    </span>
                )}
            </div>
            <p className="text-sm text-ink-muted flex-1">{description}</p>
            <Link
                href={href}
                className="text-sm font-semibold text-terracotta hover:text-terracotta-dark dark:hover:text-terracotta-light"
            >
                {actionLabel} →
            </Link>
        </div>
    );
}
