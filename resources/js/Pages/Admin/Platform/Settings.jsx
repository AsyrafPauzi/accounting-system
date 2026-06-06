import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

/**
 * Platform-level patch broadcaster, accessible only to SaaS super-admin
 * (lives on bukucloud.com / internal.bukucloud.com).
 *
 * Operator workflow:
 *   1. Push a new Docker image / git tag in your CI.
 *   2. Bump the "Latest release version" field below to the new tag.
 *   3. Save. Every customer self-hosted install picks it up on its
 *      next daily heartbeat and shows an "Update available" banner.
 *
 * No file delivery happens here. The customer's deployment pulls the
 * image (`docker compose pull && docker compose up -d`, or Watchtower
 * auto-pull). This page is purely the *advertisement* mechanism.
 */
export default function PlatformSettings({ settings = {}, fleet = {} }) {
    const { data, setData, post, processing, recentlySuccessful, errors } = useForm({
        latest_release_version: settings.latest_release_version || '',
        update_notes:           settings.update_notes || '',
        latest_release_url:     settings.latest_release_url || '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.platform.update'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Platform</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                        Patch broadcaster
                    </h1>
                    <p className="text-ink-muted text-sm mt-1">
                        Tell every self-hosted install which version they should be on. They'll see the banner on next heartbeat.
                    </p>
                </div>
            }
        >
            <Head title="Patch broadcaster" />

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6 max-w-5xl">
                <FleetStat label="Total installs"  value={fleet.total_installs ?? 0} />
                <FleetStat label="On latest"        value={fleet.at_latest ?? 0} tone="ok" />
                <FleetStat label="Behind"           value={fleet.behind ?? 0} tone={fleet.behind > 0 ? 'warn' : 'default'} />
                <FleetStat label="No version yet"   value={fleet.unknown_version ?? 0} hint="not heartbeat'd" />
            </div>

            <form onSubmit={submit} className="bg-surface border border-border-warm rounded-3xl p-8 max-w-3xl space-y-5">
                <Field label="Latest release version" hint="e.g. 1.4.0 — match your Docker image tag" error={errors.latest_release_version}>
                    <input
                        type="text"
                        value={data.latest_release_version}
                        onChange={(e) => setData('latest_release_version', e.target.value)}
                        placeholder="1.4.0"
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta font-mono"
                    />
                </Field>

                <Field
                    label="Update notes (markdown allowed)"
                    hint="Shown to customers in the update banner — short summary of what's new."
                    error={errors.update_notes}
                >
                    <textarea
                        rows={5}
                        value={data.update_notes}
                        onChange={(e) => setData('update_notes', e.target.value)}
                        placeholder="• Faster recurring invoice generation&#10;• Fix: bill receipts now retain EXIF strip&#10;• New: cross-client overdue report"
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                    />
                </Field>

                <Field
                    label="Release notes URL (optional)"
                    hint="Link the banner button to your changelog / docs."
                    error={errors.latest_release_url}
                >
                    <input
                        type="url"
                        value={data.latest_release_url}
                        onChange={(e) => setData('latest_release_url', e.target.value)}
                        placeholder="https://docs.bukucloud.com/releases/1.4.0"
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                    />
                </Field>

                <div className="bg-cream/40 border border-border-warm rounded-xl px-4 py-3 text-xs text-ink-muted">
                    <p className="font-semibold text-ink">How customers receive the patch</p>
                    <ol className="mt-1.5 space-y-0.5 list-decimal pl-4">
                        <li>Their <code>self-hosted:heartbeat</code> command (runs daily) calls our publisher API.</li>
                        <li>The response includes this version + notes. Their instance stores it locally.</li>
                        <li>An "Update available" banner shows up across their UI until they upgrade.</li>
                        <li>Upgrade itself = `docker compose pull &amp;&amp; docker compose up -d` (or Watchtower auto-pull).</li>
                    </ol>
                </div>

                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-5 py-2.5 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark transition-colors disabled:opacity-50"
                    >
                        {processing ? 'Saving…' : 'Save & broadcast'}
                    </button>
                    {recentlySuccessful && (
                        <span className="text-sm text-forest">Broadcast updated.</span>
                    )}
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function FleetStat({ label, value, hint, tone = 'default' }) {
    const tones = {
        default: 'text-ink',
        ok: 'text-forest-dark dark:text-forest-light',
        warn: 'text-terracotta-dark dark:text-terracotta-light',
    };
    return (
        <div className="bg-surface border border-border-warm rounded-2xl p-4">
            <p className="text-eyebrow font-semibold uppercase text-ink-muted">{label}</p>
            <p className={`font-display text-2xl font-medium mt-1 ${tones[tone] ?? tones.default}`}>{value}</p>
            {hint && <p className="text-xs text-ink-muted mt-0.5">{hint}</p>}
        </div>
    );
}

function Field({ label, hint, error, children }) {
    return (
        <div>
            <label className="text-sm font-semibold text-ink">{label}</label>
            {children}
            {hint && <p className="text-xs text-ink-muted mt-1">{hint}</p>}
            {error && <p className="text-xs text-terracotta mt-1">{error}</p>}
        </div>
    );
}
