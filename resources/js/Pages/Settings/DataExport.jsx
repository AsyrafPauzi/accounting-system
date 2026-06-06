import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const formatDateTime = (iso) => {
    if (!iso) return null;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleString();
};

export default function DataExport({ auth, lastExportedAt, rateLimitHours, rateLimited, nextAvailableAt, downloadUrl }) {
    const [busy, setBusy] = useState(false);

    // The download is a signed GET URL (see controller). We just point
    // the browser at it and let the streamed `Content-Disposition:
    // attachment` header do the work — no CSRF token, no Inertia
    // round-trip, no JSON-vs-binary content-type mismatch.
    const handleDownload = () => {
        if (rateLimited || busy || !downloadUrl) return;
        setBusy(true);
        window.location.href = downloadUrl;
        // The page reload after the download finishes will refresh
        // `lastExportedAt` — we leave `busy` true on purpose so the
        // button doesn't immediately become re-clickable while the
        // signed URL is in flight.
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Privacy</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                        Download my data
                    </h1>
                    <p className="text-ink-muted text-sm">
                        Get a copy of every piece of personal data we hold about you, ready for archive or porting.
                    </p>
                </div>
            }
        >
            <Head title="Download my data" />

            <div className="space-y-8 max-w-3xl">
                <section className="bg-surface border border-border-warm rounded-3xl p-6 sm:p-8 space-y-5">
                    <div>
                        <h2 className="font-display text-lg font-medium text-ink mb-2">What's in the export</h2>
                        <ul className="list-disc pl-5 text-sm text-ink-muted space-y-1">
                            <li>Your account profile, role, and consent timestamps.</li>
                            <li>Your organisation's metadata and active subscription.</li>
                            <li>One CSV per personal-data table inside your books — customers, suppliers, invoices, bills, journals, payments, audit log, and more.</li>
                            <li>A README that explains the layout.</li>
                        </ul>
                        <p className="text-xs text-ink-muted mt-3">
                            Receipt images and PDFs are <em>not</em> included — download those separately from the relevant bills if you need them.
                        </p>
                    </div>

                    <div className="border-t border-border-warm pt-5 space-y-2">
                        <p className="text-sm text-ink">
                            <strong>Last downloaded:</strong>{' '}
                            {lastExportedAt ? formatDateTime(lastExportedAt) : <span className="text-ink-muted">never</span>}
                        </p>
                        <p className="text-xs text-ink-muted">
                            For security and to keep our servers happy, you can request one export every {rateLimitHours} hours.
                            {rateLimited && nextAvailableAt && (
                                <> Next download available {formatDateTime(nextAvailableAt)}.</>
                            )}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={handleDownload}
                        disabled={rateLimited || busy || !downloadUrl}
                        className={`w-full sm:w-auto px-6 py-3 rounded-2xl font-semibold text-sm transition-colors ${
                            rateLimited || busy || !downloadUrl
                                ? 'bg-ink/20 text-ink/40 cursor-not-allowed'
                                : 'bg-ink text-cream hover:bg-ink-muted'
                        }`}
                    >
                        {busy
                            ? 'Building your archive…'
                            : rateLimited
                                ? 'Available again later'
                                : 'Download my data (.zip)'}
                    </button>
                </section>

                <section className="bg-mustard/10 border border-mustard/30 rounded-2xl px-6 py-5 text-sm text-ink">
                    <p>
                        Want to delete everything instead?{' '}
                        <Link href={route('settings.account_erase.show')} className="text-terracotta hover:text-terracotta-dark font-semibold">
                            Delete my account →
                        </Link>
                    </p>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
