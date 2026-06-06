import { Head, Link } from '@inertiajs/react';

export default function AlreadyInstalled() {
    return (
        <div className="min-h-screen bg-cream text-ink flex items-center justify-center px-6 py-12">
            <Head title="Already installed" />
            <div className="max-w-md text-center space-y-3">
                <p className="text-eyebrow font-semibold uppercase text-terracotta">Set up</p>
                <h1 className="font-display text-3xl font-medium tracking-tight">This BukuCloud install is already set up.</h1>
                <p className="text-ink-muted text-sm">
                    The installer only runs once. To change your licence key, edit <code>APP_LICENSE_KEY</code> in <code>.env</code>.
                    To reset the admin password, run <code>php artisan self-hosted:bootstrap --email=… --reset-password=…</code>.
                </p>
                <Link
                    href={route('login')}
                    className="inline-block mt-4 px-5 py-2.5 rounded-2xl font-semibold text-sm bg-ink text-cream hover:bg-ink-muted"
                >
                    Go to sign-in
                </Link>
            </div>
        </div>
    );
}
