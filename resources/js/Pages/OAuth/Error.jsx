import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

const REASON_TITLES = {
    unknown_client: 'Unknown application',
    invalid_redirect_uri: 'Invalid request',
    firm_user: 'Wrong account type',
    default: 'Couldn\'t complete the request',
};

/**
 * Generic error rendering for the OAuth handshake. We never echo the
 * partner-supplied redirect_uri here — even with reason=invalid_redirect_uri
 * we keep the message generic so an attacker who triggers this page can't
 * pivot the user toward a malicious URL.
 */
export default function OAuthError({ reason, detail }) {
    const title = REASON_TITLES[reason] ?? REASON_TITLES.default;
    return (
        <GuestLayout>
            <Head title={title} />
            <div className="text-center space-y-5">
                <h1 className="font-display text-2xl font-medium text-ink">{title}</h1>
                {detail && <p className="text-sm text-ink-muted">{detail}</p>}
                <div className="flex gap-3 justify-center">
                    <Link
                        href={route('login')}
                        className="inline-flex items-center justify-center rounded-xl border border-border-warm px-4 py-2.5 text-sm font-medium text-ink hover:bg-cream"
                    >
                        Back to login
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
