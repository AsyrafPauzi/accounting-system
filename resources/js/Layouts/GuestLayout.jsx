import { Link, usePage } from '@inertiajs/react';

export default function Guest({ children }) {
    const { product_name: productName } = usePage().props;
    const displayName = productName || 'BukuCloud';

    return (
        <div className="min-h-screen w-full flex items-center justify-center bg-cream px-4 py-10">
            <div className="relative w-full max-w-md">
                <div className="pointer-events-none absolute -top-16 -left-10 h-40 w-40 rounded-full bg-terracotta/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -right-10 h-48 w-48 rounded-full bg-forest/10 blur-3xl" />

                <div className="relative flex flex-col items-center">
                    <Link href="/" className="mb-8" aria-label={displayName}>
                        <img
                            src="/images/bukucloud-logo.png"
                            alt={displayName}
                            className="h-14 w-auto object-contain"
                        />
                    </Link>

                    <div className="w-full px-8 py-10 bg-surface border border-border-warm shadow-xl shadow-ink/5 rounded-2xl">
                        {children}
                    </div>

                    <p className="mt-6 text-ink-muted text-xs font-tabular">
                        &copy; {new Date().getFullYear()} {displayName}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    );
}
