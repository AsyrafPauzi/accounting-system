/**
 * Billing-history timeline. Renders the array produced by
 * `App\Services\BillingHistoryService::forSubscription()` as a vertical
 * dotted-rail timeline that mirrors the visual style of the rest of the
 * Settings pages — cream surface, terracotta accents, eyebrow caps.
 *
 * Two stable contracts:
 *   - `events`: array<{ id, happened_at, type, icon, title, detail, actor }>
 *   - empty array → "No billing events yet" empty-state card
 *
 * The icons map keeps every icon defined inline so this component stays
 * self-contained — no extra dependency on a global icon set or a heavy
 * sprite import.
 */

const Icon = ({ name }) => {
    const path = ICONS[name] || ICONS.pencil;
    return (
        <svg
            className="w-4 h-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={path} />
        </svg>
    );
};

const ICONS = {
    'sparkles':   'M5 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5zM19 11l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z',
    'arrow-up':   'M5 10l7-7m0 0l7 7m-7-7v18',
    'arrow-down': 'M19 14l-7 7m0 0l-7-7m7 7V3',
    'check':      'M5 13l4 4L19 7',
    'x':          'M6 18L18 6M6 6l12 12',
    'clock':      'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'alert':      'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    'refresh':    'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    'calendar':   'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'undo':       'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
    'pencil':     'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    'trash':      'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3',
    'user-plus':  'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
};

const TONE = {
    'subscribed':         'bg-forest/15 text-forest',
    'reactivated':        'bg-forest/15 text-forest',
    'plan_changed':       'bg-terracotta/15 text-terracotta',
    'renewed':            'bg-mustard/20 text-ink',
    'change_scheduled':   'bg-mustard/20 text-ink',
    'change_cancelled':   'bg-ink/10 text-ink-muted',
    'extra_seat_added':   'bg-forest/15 text-forest',
    'extra_seat_failed':  'bg-terracotta/15 text-terracotta',
    'status_changed':     'bg-terracotta/15 text-terracotta',
    'updated':            'bg-ink/10 text-ink-muted',
    'deleted':            'bg-ink/10 text-ink-muted',
};

const formatWhen = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

export default function BillingHistory({ events = [] }) {
    return (
        <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm/80 shadow-sm">
            <div className="flex items-center justify-between mb-1">
                <p className="text-eyebrow font-semibold uppercase text-terracotta">Billing history</p>
                {events.length > 0 && (
                    <p className="text-eyebrow font-semibold uppercase text-ink-muted">
                        {events.length} event{events.length === 1 ? '' : 's'}
                    </p>
                )}
            </div>
            <h3 className="font-display text-xl text-ink tracking-tight mb-4">
                Plan &amp; payment timeline
            </h3>
            <p className="text-ink-muted text-sm mb-6">
                Every plan change, renewal, cancellation, and seat purchase shows up here for the audit trail.
            </p>

            {events.length === 0 ? (
                <div className="rounded-2xl bg-cream border border-border-warm/60 px-4 py-8 text-center">
                    <p className="text-ink-muted text-sm">
                        No billing events yet. The first one will show up here as soon as your plan changes
                        or your subscription is renewed.
                    </p>
                </div>
            ) : (
                <ol className="relative pl-7 space-y-5 before:absolute before:top-2 before:bottom-2 before:left-[11px] before:w-px before:bg-border-warm/80">
                    {events.map((ev) => {
                        const tone = TONE[ev.type] || TONE.updated;
                        return (
                            <li key={ev.id} className="relative">
                                <span
                                    className={`absolute -left-7 top-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full ${tone} ring-4 ring-surface`}
                                >
                                    <Icon name={ev.icon} />
                                </span>
                                <div className="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                                    <p className="text-sm font-semibold text-ink">{ev.title}</p>
                                    <p className="text-eyebrow font-semibold uppercase text-ink-muted whitespace-nowrap">
                                        {formatWhen(ev.happened_at)}
                                    </p>
                                </div>
                                {ev.detail && (
                                    <p className="text-ink-muted text-sm mt-1">{ev.detail}</p>
                                )}
                                {ev.actor && (
                                    <p className="text-eyebrow font-semibold uppercase text-ink-muted mt-1">
                                        by {ev.actor}
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}
