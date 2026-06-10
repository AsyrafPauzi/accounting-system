import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Consent screen — the user reviews exactly what the partner is asking
 * for, then clicks Authorize or Cancel. On Authorize, the backend mints
 * a one-time code and redirects to the partner's `redirect_uri`. On
 * Cancel, the partner gets `?error=access_denied&state=...`.
 *
 * No keys are shown here — they only ever appear in the partner's
 * server-side response from /api/oauth/token. This is by design:
 *
 *   - The user shouldn't have to copy/paste anything; this is the OAuth
 *     way, not the "go to settings, copy key, paste into partner".
 *   - The partner's backend, not the user's browser, holds the keys.
 *     This dramatically reduces accidental disclosure surface.
 */
export default function OAuthConsent({ partner, tenant, user }) {
    const approve = useForm({});
    const deny = useForm({});

    const onApprove = (e) => {
        e.preventDefault();
        approve.post(route('oauth.consent.approve'));
    };

    const onDeny = (e) => {
        e.preventDefault();
        deny.post(route('oauth.consent.deny'));
    };

    const scopeEntries = Object.entries(partner?.scopes ?? {});

    return (
        <GuestLayout>
            <Head title={`Authorize ${partner?.name ?? 'integration'}`} />

            <div className="space-y-6">
                <div className="text-center">
                    <p className="text-xs uppercase tracking-wider text-ink-muted">Authorization request</p>
                    <h1 className="mt-1 font-display text-2xl font-medium text-ink">
                        Allow {partner?.name ?? 'this app'} to access your BukuCloud data?
                    </h1>
                    {partner?.description && (
                        <p className="mt-2 text-sm text-ink-muted max-w-md mx-auto">{partner.description}</p>
                    )}
                </div>

                <div className="rounded-2xl border border-border-warm bg-cream/40 p-4 text-sm">
                    <div className="text-ink-muted">Signed in as</div>
                    <div className="font-medium text-ink">{user?.name ?? user?.email}</div>
                    <div className="text-xs text-ink-muted">{user?.email}</div>
                    <div className="mt-3 text-ink-muted">Granting access to</div>
                    <div className="font-medium text-ink">{tenant?.name ?? tenant?.id}</div>
                </div>

                {scopeEntries.length > 0 && (
                    <div className="rounded-2xl border border-border-warm p-4">
                        <p className="text-sm font-medium text-ink mb-3">{partner?.name ?? 'This app'} will be able to:</p>
                        <ul className="space-y-2">
                            {scopeEntries.map(([key, label]) => (
                                <li key={key} className="flex items-start gap-2 text-sm text-ink">
                                    <svg className="mt-0.5 h-4 w-4 flex-shrink-0 text-forest" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{label}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 text-xs text-ink-muted">
                            You can revoke this access anytime from <strong>Settings → API & Integrations</strong>.
                        </p>
                    </div>
                )}

                <div className="flex gap-3">
                    <form onSubmit={onDeny} className="flex-1">
                        <SecondaryButton
                            type="submit"
                            className="w-full justify-center py-3 rounded-xl"
                            disabled={approve.processing || deny.processing}
                        >
                            Cancel
                        </SecondaryButton>
                    </form>
                    <form onSubmit={onApprove} className="flex-1">
                        <PrimaryButton
                            type="submit"
                            className="w-full justify-center py-3 rounded-xl"
                            disabled={approve.processing || deny.processing}
                        >
                            {approve.processing ? 'Authorizing…' : 'Authorize'}
                        </PrimaryButton>
                    </form>
                </div>

                <p className="text-center text-xs text-ink-muted">
                    By authorizing, you allow {partner?.name ?? 'this app'} to access the data listed above on behalf of <strong>{tenant?.name ?? 'your account'}</strong>. BukuCloud never shares your password.
                </p>
            </div>
        </GuestLayout>
    );
}
