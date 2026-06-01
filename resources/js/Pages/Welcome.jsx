import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="BukuCloud" />
            <div className="min-h-screen bg-cream text-ink relative overflow-hidden">
                <div className="absolute -top-40 -right-40 w-96 h-96 bg-terracotta/10 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-40 -left-40 w-96 h-96 bg-forest/10 rounded-full blur-3xl pointer-events-none" />

                <div className="relative max-w-5xl mx-auto px-6 py-12">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <ApplicationLogo className="h-9 w-9" />
                            <span className="font-display text-xl font-medium text-ink">BukuCloud</span>
                        </div>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-xl px-4 py-2 bg-terracotta text-white text-sm font-semibold hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-xl px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-alt transition-colors"
                                    >
                                        Sign in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-xl px-4 py-2 bg-terracotta text-white text-sm font-semibold hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors"
                                    >
                                        Create account
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <main className="mt-24 text-center max-w-2xl mx-auto">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">BukuCloud</p>
                        <h1 className="font-display text-4xl sm:text-5xl lg:text-6xl font-medium text-ink tracking-tight mt-4">
                            Books made for the way you actually work.
                        </h1>
                        <p className="mt-6 text-ink-muted text-lg leading-relaxed">
                            Invoices, expenses and reports for Malaysian SMEs — clear, current, and ready when LHDN asks.
                        </p>
                        <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                            {!auth.user && (
                                <>
                                    <Link
                                        href={route('register')}
                                        className="px-6 py-3 rounded-2xl bg-terracotta text-white font-semibold hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors"
                                    >
                                        Start free
                                    </Link>
                                    <Link
                                        href={route('login')}
                                        className="px-6 py-3 rounded-2xl border border-ink text-ink font-semibold hover:bg-surface-alt transition-colors"
                                    >
                                        Sign in
                                    </Link>
                                </>
                            )}
                        </div>
                    </main>

                    <footer className="absolute bottom-6 left-0 right-0 text-center text-sm text-ink-muted">
                        © {new Date().getFullYear()} BukuCloud
                    </footer>
                </div>
            </div>
        </>
    );
}
