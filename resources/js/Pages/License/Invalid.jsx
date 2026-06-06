import { Head } from '@inertiajs/react';

const REASONS = {
    expired:         { title: 'License expired', body: 'Your BukuCloud licence has expired. Please contact your account manager to renew.' },
    revoked:         { title: 'License revoked', body: 'This installation\'s licence has been revoked. Get in touch with BukuCloud support to resolve.' },
    bad_signature:   { title: 'License invalid', body: 'The licence key on this installation can\'t be verified. Re-paste the key issued to your firm.' },
    malformed:       { title: 'License malformed', body: 'The licence key on this installation is in the wrong format. Re-paste the key issued to your firm.' },
    heartbeat_stale: { title: 'No recent connection', body: 'We haven\'t heard from this installation in over 14 days. Reconnect it to the internet so the daily licence check can run.' },
    unknown:         { title: 'License problem', body: 'Something\'s wrong with this installation\'s licence. Please contact support.' },
};

export default function Invalid({ reason }) {
    const r = REASONS[reason] ?? REASONS.unknown;

    return (
        <div className="min-h-screen bg-cream text-ink flex items-center justify-center px-6 py-12">
            <Head title={r.title} />
            <div className="max-w-lg text-center space-y-4">
                <p className="text-eyebrow font-semibold uppercase text-terracotta">License</p>
                <h1 className="font-display text-3xl lg:text-4xl font-medium tracking-tight">{r.title}</h1>
                <p className="text-ink-muted text-sm leading-relaxed">{r.body}</p>
                <p className="text-xs text-ink-muted pt-4">
                    Need help? Email{' '}
                    <a href="mailto:support@bukucloud.io" className="text-terracotta hover:underline">support@bukucloud.io</a>{' '}
                    with your customer ID.
                </p>
            </div>
        </div>
    );
}
