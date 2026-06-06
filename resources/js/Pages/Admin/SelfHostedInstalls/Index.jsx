import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const HEALTH_BADGE = {
    pending:  { label: 'Pending first heartbeat', cls: 'bg-mustard/15 text-ink' },
    healthy:  { label: 'Healthy',                 cls: 'bg-sage/15 text-sage-dark' },
    degraded: { label: 'Degraded',                cls: 'bg-mustard/30 text-ink' },
    stale:    { label: 'Stale',                   cls: 'bg-terracotta/15 text-terracotta-dark' },
    revoked:  { label: 'Revoked',                 cls: 'bg-terracotta text-cream' },
};

const formatRelative = (iso) => {
    if (!iso) return '—';
    const ts = new Date(iso).getTime();
    const days = Math.round((Date.now() - ts) / 86_400_000);
    if (days <= 0) return 'today';
    if (days === 1) return 'yesterday';
    if (days < 30) return `${days}d ago`;
    if (days < 365) return `${Math.round(days / 30)}mo ago`;
    return `${Math.round(days / 365)}y ago`;
};

export default function Index({ installs, tiers }) {
    const flash = usePage().props.flash ?? {};
    const [showIssueForm, setShowIssueForm] = useState(false);
    const [confirmRevoke, setConfirmRevoke] = useState(null);

    // Tier presets — these decide what `features[]` ship in the
    // signed license payload. The runtime branches on these flags
    // (`Deployment::practiceConsoleEnabled()` etc.), so getting the
    // defaults right is what makes Standard vs Enterprise behave
    // differently. Operators can still hand-edit before signing.
    const TIER_DEFAULTS = {
        'self-hosted-standard':   { features: '',                                 max_tenants: 1 },
        'self-hosted-enterprise': { features: 'practice.console, tenants.create', max_tenants: 0 },
    };
    const initialTier = tiers[0] ?? 'self-hosted-standard';

    const issue = useForm({
        customer_id: '',
        customer_name: '',
        plan_tier: initialTier,
        max_users: 0,
        max_tenants: TIER_DEFAULTS[initialTier]?.max_tenants ?? 0,
        features: TIER_DEFAULTS[initialTier]?.features ?? '',
        expires_at: '',
    });

    const onTierChange = (tier) => {
        const defaults = TIER_DEFAULTS[tier] ?? {};
        issue.setData((prev) => ({
            ...prev,
            plan_tier: tier,
            features: defaults.features ?? prev.features,
            max_tenants: defaults.max_tenants ?? prev.max_tenants,
        }));
    };

    const revoke = useForm({ reason: '' });

    const submitIssue = (e) => {
        e.preventDefault();
        issue.post(route('admin.self-hosted.issue'), {
            onSuccess: () => { issue.reset(); setShowIssueForm(false); },
            preserveScroll: true,
        });
    };

    const submitRevoke = (id) => {
        revoke.post(route('admin.self-hosted.revoke', id), {
            onSuccess: () => { revoke.reset(); setConfirmRevoke(null); },
            preserveScroll: true,
        });
    };

    const submitUnrevoke = (id) => {
        // Use `router.post` here — `useForm` is a hook and may not be
        // called from inside an event handler. The previous version
        // silently no-op'd because React skipped the (illegal) hook.
        router.post(route('admin.self-hosted.unrevoke', id), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-3">
                    <h2 className="font-display text-xl font-medium text-ink">Self-hosted installs</h2>
                    <button
                        type="button"
                        onClick={() => setShowIssueForm((v) => !v)}
                        className="px-4 py-2 rounded-2xl text-sm font-semibold bg-ink text-cream hover:bg-ink-muted"
                    >
                        {showIssueForm ? 'Cancel' : 'Issue licence'}
                    </button>
                </div>
            }
        >
            <Head title="Self-hosted installs" />

            {flash.issued_license && (
                <div className="mb-6 px-4 py-4 rounded-2xl bg-sage/10 border border-sage/40 text-ink space-y-2">
                    <p className="text-sm font-semibold">License key — copy this and paste into the customer&apos;s <code>APP_LICENSE_KEY</code>:</p>
                    <textarea
                        readOnly
                        value={flash.issued_license}
                        onClick={(e) => e.target.select()}
                        className="w-full font-mono text-xs rounded-xl border-border-warm bg-cream/40 p-3"
                        rows={4}
                    />
                    <div className="flex items-center justify-between gap-3 flex-wrap">
                        <p className="text-xs text-ink-muted">
                            We don&apos;t persist the signed key. Re-show with un-revoke if you lose it.
                        </p>
                        <button
                            type="button"
                            onClick={async () => {
                                try {
                                    await navigator.clipboard.writeText(flash.issued_license);
                                } catch (_) { /* fallback: user can still select+copy */ }
                            }}
                            className="text-eyebrow uppercase font-semibold text-terracotta hover:text-terracotta-dark"
                        >
                            Copy to clipboard
                        </button>
                    </div>
                </div>
            )}

            {showIssueForm && (
                <form onSubmit={submitIssue} className="bg-surface border border-border-warm rounded-3xl p-6 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="md:col-span-2">
                        <h3 className="font-display text-base font-medium mb-1">Issue a new licence</h3>
                        <p className="text-xs text-ink-muted">Generates a signed key for this customer. They paste it into their install wizard.</p>
                    </div>

                    <div>
                        <InputLabel value="Customer ID" />
                        <TextInput
                            value={issue.data.customer_id}
                            onChange={(e) => issue.setData('customer_id', e.target.value)}
                            placeholder="acme-co"
                            className="mt-1 block w-full"
                        />
                        <InputError message={issue.errors.customer_id} />
                    </div>

                    <div>
                        <InputLabel value="Customer display name" />
                        <TextInput
                            value={issue.data.customer_name}
                            onChange={(e) => issue.setData('customer_name', e.target.value)}
                            placeholder="Acme Sdn Bhd"
                            className="mt-1 block w-full"
                        />
                        <InputError message={issue.errors.customer_name} />
                    </div>

                    <div>
                        <InputLabel value="Tier" />
                        <select
                            value={issue.data.plan_tier}
                            onChange={(e) => onTierChange(e.target.value)}
                            className="mt-1 block w-full rounded-xl border-border-warm focus:border-terracotta focus:ring-terracotta"
                        >
                            {tiers.map((t) => <option key={t}>{t}</option>)}
                        </select>
                        <p className="mt-1 text-xs text-ink-muted">
                            {issue.data.plan_tier === 'self-hosted-enterprise'
                                ? 'Enterprise: firm + multiple client tenants, Practice console enabled.'
                                : 'Standard: single tenant, Practice console disabled.'}
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Max users (0 = unlimited)" />
                        <TextInput
                            type="number"
                            min={0}
                            value={issue.data.max_users}
                            onChange={(e) => issue.setData('max_users', e.target.value)}
                            className="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel value="Max tenants (0 = unlimited)" />
                        <TextInput
                            type="number"
                            min={0}
                            value={issue.data.max_tenants}
                            onChange={(e) => issue.setData('max_tenants', e.target.value)}
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-xs text-ink-muted">
                            Standard tier should keep this at 1. Enterprise typically uses 0 (unlimited) or a contracted cap.
                        </p>
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel value="Features (comma-separated)" />
                        <TextInput
                            value={issue.data.features}
                            onChange={(e) => issue.setData('features', e.target.value)}
                            placeholder="practice.console, tenants.create"
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-xs text-ink-muted">
                            Auto-filled from tier. Runtime feature gates: <code>practice.console</code> unlocks the Accountant console; <code>tenants.create</code> lets the firm-owner provision client tenants.
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Expires (optional)" />
                        <TextInput
                            type="date"
                            value={issue.data.expires_at}
                            onChange={(e) => issue.setData('expires_at', e.target.value)}
                            className="mt-1 block w-full"
                        />
                        <InputError message={issue.errors.expires_at} />
                    </div>

                    <div className="md:col-span-2 flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={issue.processing}
                            className="px-5 py-2.5 rounded-2xl font-semibold text-sm bg-ink text-cream hover:bg-ink-muted disabled:opacity-50"
                        >
                            {issue.processing ? 'Signing…' : 'Issue & sign'}
                        </button>
                    </div>
                </form>
            )}

            <div className="bg-surface border border-border-warm rounded-3xl overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-eyebrow uppercase font-semibold text-ink-muted bg-cream/50">
                                <th className="px-4 py-3">Customer</th>
                                <th className="px-4 py-3">Tier</th>
                                <th className="px-4 py-3">Health</th>
                                <th className="px-4 py-3 text-right">Users</th>
                                <th className="px-4 py-3">Version</th>
                                <th className="px-4 py-3">Last heartbeat</th>
                                <th className="px-4 py-3">Expires</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {installs.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center text-ink-muted">No installs yet.</td></tr>
                            )}
                            {installs.map((i) => {
                                const badge = HEALTH_BADGE[i.health] ?? HEALTH_BADGE.pending;
                                return (
                                    <tr key={i.id} className="hover:bg-cream/30">
                                        <td className="px-4 py-3">
                                            <div className="font-semibold text-ink">{i.customer_name}</div>
                                            <div className="text-xs text-ink-muted">{i.customer_id}</div>
                                        </td>
                                        <td className="px-4 py-3 text-ink-muted">{i.plan_tier}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${badge.cls}`}>
                                                {badge.label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-tabular">{i.latest_user_count ?? '—'}{i.max_users ? <span className="text-ink-muted text-xs"> / {i.max_users}</span> : null}</td>
                                        <td className="px-4 py-3 text-ink-muted">{i.latest_version ?? '—'}</td>
                                        <td className="px-4 py-3 text-ink-muted">{formatRelative(i.latest_heartbeat_at)}</td>
                                        <td className="px-4 py-3 text-ink-muted">{i.expires_at ? new Date(i.expires_at).toLocaleDateString() : 'perpetual'}</td>
                                        <td className="px-4 py-3 text-right">
                                            {confirmRevoke === i.id ? (
                                                <div className="flex flex-col gap-2">
                                                    <input
                                                        type="text"
                                                        value={revoke.data.reason}
                                                        onChange={(e) => revoke.setData('reason', e.target.value)}
                                                        placeholder="Reason"
                                                        className="rounded-lg border-border-warm text-xs"
                                                    />
                                                    <div className="flex gap-2">
                                                        <button onClick={() => submitRevoke(i.id)} className="px-2 py-1 rounded-md text-xs font-semibold bg-terracotta text-cream">Confirm</button>
                                                        <button onClick={() => setConfirmRevoke(null)} className="px-2 py-1 rounded-md text-xs text-ink-muted">Cancel</button>
                                                    </div>
                                                </div>
                                            ) : i.revoked_at ? (
                                                <button onClick={() => submitUnrevoke(i.id)} className="text-xs font-semibold text-sage-dark hover:underline">
                                                    Un-revoke
                                                </button>
                                            ) : (
                                                <button onClick={() => setConfirmRevoke(i.id)} className="text-xs font-semibold text-terracotta hover:text-terracotta-dark">
                                                    Revoke
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
