import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';

export default function Guest({ children }) {
    const { product_name: productName } = usePage().props;
    const displayName = productName || 'BukuCloud';

    return (
        <div className="min-h-screen w-full flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 px-4">
            <div className="relative w-full max-w-md">
                <div className="pointer-events-none absolute -top-16 -left-10 h-40 w-40 rounded-full bg-blue-500/15 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -right-10 h-48 w-48 rounded-full bg-indigo-500/15 blur-3xl" />

                <div className="relative flex flex-col items-center">
                    <Link href="/" className="mb-6">
                        <ApplicationLogo className="w-14 h-14 fill-current text-white" />
                    </Link>

                    <div className="w-full px-8 py-10 bg-white/95 backdrop-blur border border-slate-200/80 shadow-xl shadow-slate-900/30 rounded-2xl">
                        {children}
                    </div>
                    
                    <p className="mt-6 text-slate-400 text-xs">
                        &copy; {new Date().getFullYear()} {displayName}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    );
}