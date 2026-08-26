import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const SECTION_META = {
    added: { label: 'Added', badge: 'bg-forest/10 text-forest' },
    fixed: { label: 'Fixed', badge: 'bg-blue-50 text-blue-800' },
    improved: { label: 'Improved', badge: 'bg-violet-100 text-violet-800' },
    planned: { label: 'Planned', badge: 'bg-amber-50 text-amber-800' },
};

function formatDate(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return iso;
    }
}

function ReleaseCard({ release, defaultOpen }) {
    const [open, setOpen] = useState(defaultOpen);
    const sectionKeys = Object.keys(release.sections || {}).filter((k) => (release.sections[k] || []).length > 0);
    const itemCount = sectionKeys.reduce((n, k) => n + release.sections[k].length, 0);

    return (
        <article className="rounded-2xl border border-border-warm/80 bg-surface shadow-sm overflow-hidden">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="w-full flex items-start justify-between gap-4 px-5 sm:px-6 py-5 text-left hover:bg-cream/50 transition-colors"
            >
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-display text-lg font-medium text-ink">{release.label}</h3>
                        <span className="text-xs font-medium text-ink-muted tabular-nums">{formatDate(release.date)}</span>
                    </div>
                    {release.summary && (
                        <p className="mt-1 text-sm text-ink-muted leading-relaxed">{release.summary}</p>
                    )}
                    <p className="mt-2 text-xs text-ink-muted">{itemCount} item{itemCount === 1 ? '' : 's'}</p>
                </div>
                <svg
                    className={`w-5 h-5 text-ink-muted shrink-0 mt-1 transition-transform ${open ? 'rotate-180' : ''}`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {open && (
                <div className="px-5 sm:px-6 pb-6 space-y-5 border-t border-border-warm/60">
                    {sectionKeys.map((key) => {
                        const meta = SECTION_META[key] || { label: key, badge: 'bg-cream text-ink-muted' };
                        return (
                            <div key={key}>
                                <span className={`inline-flex text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md ${meta.badge}`}>
                                    {meta.label}
                                </span>
                                <ul className="mt-3 space-y-2">
                                    {release.sections[key].map((item) => (
                                        <li key={item} className="flex gap-2.5 text-sm text-ink leading-relaxed">
                                            <span className="text-terracotta mt-1.5 shrink-0">•</span>
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        );
                    })}
                </div>
            )}
        </article>
    );
}

export default function Changelog({ auth, meta = {}, releases = [] }) {
    const [filter, setFilter] = useState('all');

    const filtered = useMemo(() => {
        if (filter === 'all') return releases;
        return releases
            .map((r) => ({
                ...r,
                sections: Object.fromEntries(
                    Object.entries(r.sections || {}).filter(([k]) => k === filter),
                ),
            }))
            .filter((r) => Object.values(r.sections).some((items) => items.length > 0));
    }, [releases, filter]);

    const totalItems = useMemo(
        () => releases.reduce((n, r) => n + Object.values(r.sections || {}).flat().length, 0),
        [releases],
    );

    const sinceLabel = meta.first_commit
        ? new Date(meta.first_commit).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' })
        : null;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Settings</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            What&apos;s new
                        </h1>
                        <p className="text-ink-muted text-sm mt-1">
                            Full {meta.product || 'BukuCloud'} history
                            {sinceLabel ? ` since ${sinceLabel}` : ''}
                            {' '}— {releases.length} releases, {totalItems} entries.
                        </p>
                    </div>
                    <Link
                        href={route('settings.company')}
                        className="text-sm font-semibold text-terracotta hover:text-terracotta whitespace-nowrap"
                    >
                        ← Company settings
                    </Link>
                </div>
            }
        >
            <Head title="What's new" />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                <div className="flex flex-wrap gap-2">
                    {[
                        { id: 'all', label: 'All' },
                        { id: 'added', label: 'Added' },
                        { id: 'fixed', label: 'Fixed' },
                        { id: 'improved', label: 'Improved' },
                        { id: 'planned', label: 'Planned' },
                    ].map((opt) => (
                        <button
                            key={opt.id}
                            type="button"
                            onClick={() => setFilter(opt.id)}
                            className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-colors ${
                                filter === opt.id
                                    ? 'bg-terracotta text-white border-terracotta'
                                    : 'bg-surface text-ink border-border-warm hover:bg-cream'
                            }`}
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>

                <div className="space-y-4">
                    {filtered.length > 0 ? filtered.map((release, i) => (
                        <ReleaseCard key={release.id} release={release} defaultOpen={i === 0} />
                    )) : (
                        <p className="text-sm text-ink-muted text-center py-12">No entries match this filter.</p>
                    )}
                </div>

                <p className="text-xs text-ink-muted text-center pb-4">
                    History compiled from the first commit ({meta.first_commit || '2026-03-10'}) through today.
                    Update <code className="text-[11px] bg-cream px-1 py-0.5 rounded">config/changelog.php</code> when shipping user-visible changes.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
