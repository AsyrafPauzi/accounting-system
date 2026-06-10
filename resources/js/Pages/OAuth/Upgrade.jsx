import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

/**
 * Shown when a logged-in user reaches the OAuth flow but their tenant
 * is on a plan that doesn't include `api.access` (i.e. Startup/Free).
 *
 * The integration's redirect URI is NOT echoed here — we don't want a
 * misconfigured partner to send the user to a malicious-looking URL.
 * If the user clicks "Choose a plan" we drop them straight onto the
 * billing page; once upgraded they re-initiate the connect flow from
 * the partner.
 */
export default function OAuthUpgrade() {
    return (
        <GuestLayout>
            <Head title="Plan upgrade required" />
            <div className="text-center space-y-5">
                <h1 className="font-display text-2xl font-medium text-ink">API access requires a paid plan</h1>
                <p className="text-sm text-ink-muted">
                    External integrations (like third-party finance apps) are available on the <strong>Solo</strong> plan and above. Your account is currently on the free Startup tier.
                </p>
                <div className="rounded-2xl border border-border-warm bg-cream/40 p-4 text-left text-sm space-y-2">
                    <p className="font-medium text-ink">What you'll unlock by upgrading:</p>
                    <ul className="text-ink-muted space-y-1 list-disc list-inside">
                        <li>"Connect to BukuCloud" with finance & analytics partners</li>
                        <li>Personal API key for read access to your books</li>
                        <li>Transaction signing key for secure write access</li>
                    </ul>
                </div>
                <div className="flex gap-3 justify-center">
                    <Link
                        href={route('subscription.index')}
                        className="inline-flex items-center justify-center rounded-xl bg-terracotta px-4 py-2.5 text-sm font-medium text-white hover:bg-terracotta-dark"
                    >
                        Choose a plan
                    </Link>
                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center justify-center rounded-xl border border-border-warm px-4 py-2.5 text-sm font-medium text-ink hover:bg-cream"
                    >
                        Back to dashboard
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
