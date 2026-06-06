import { useState } from 'react';
import PracticeLayout from '@/Layouts/PracticeLayout';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * Add a client to the firm. Two flows live behind a top tab strip:
 *
 *   1. Create new      — provision a brand-new tenant + owner user
 *   2. Invite existing — send an in-app invite to an SME's email
 *
 * The page is gated by `firm.can_add` server-side; the UI mirrors that
 * with disabled buttons and a clear "upgrade your plan" CTA so it's
 * visually obvious why the form is locked.
 */
export default function AddClient({ firm }) {
    const [tab, setTab] = useState('new');

    const blocked = !firm.can_add;

    return (
        <PracticeLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">{firm.name}</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Add a client
                        </h1>
                        <p className="text-ink-muted text-sm mt-1">
                            Set up a brand-new client, or pull in someone who's already on BukuCloud.
                        </p>
                    </div>
                    <UsageBadge firm={firm} />
                </div>
            }
        >
            <Head title="Add client" />

            {blocked && (
                <div className="bg-terracotta/10 border border-terracotta/30 rounded-2xl px-6 py-4 mb-6 text-sm text-terracotta-dark dark:text-terracotta-light flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <p className="font-semibold">You've hit your plan's client cap.</p>
                        <p className="mt-0.5">
                            You're on <b>{firm.plan ?? 'no plan'}</b> with {firm.client_count}/{firm.client_cap} clients in use.
                            Upgrade to add more.
                        </p>
                    </div>
                    <Link
                        href={route('practice.plan')}
                        className="px-4 py-2 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark transition-colors"
                    >
                        See plans
                    </Link>
                </div>
            )}

            <div className="bg-surface border border-border-warm rounded-3xl overflow-hidden max-w-3xl">
                <div className="grid grid-cols-2 border-b border-border-warm">
                    <TabButton
                        active={tab === 'new'}
                        onClick={() => setTab('new')}
                        title="Create new client"
                        sub="They're not on BukuCloud yet"
                    />
                    <TabButton
                        active={tab === 'existing'}
                        onClick={() => setTab('existing')}
                        title="Invite existing"
                        sub="They already have an account"
                    />
                </div>

                <div className="p-8">
                    {tab === 'new' ? <CreateNewForm disabled={blocked} /> : <InviteExistingForm disabled={blocked} />}
                </div>
            </div>
        </PracticeLayout>
    );
}

function TabButton({ active, onClick, title, sub }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`text-left p-5 transition-colors ${
                active
                    ? 'bg-cream/40 text-ink'
                    : 'text-ink-muted hover:bg-cream/20 hover:text-ink'
            }`}
        >
            <p className={`font-semibold text-sm ${active ? 'text-terracotta' : ''}`}>{title}</p>
            <p className="text-xs mt-0.5">{sub}</p>
        </button>
    );
}

function UsageBadge({ firm }) {
    const cap = firm.client_cap;
    const count = firm.client_count;
    const unlimited = cap === null || cap === undefined;

    return (
        <div className="bg-surface border border-border-warm rounded-2xl px-4 py-2 text-sm">
            <p className="text-eyebrow font-semibold uppercase text-ink-muted">Clients used</p>
            <p className="font-display text-lg font-medium text-ink mt-0.5">
                {count}
                {unlimited ? (
                    <span className="text-ink-muted text-base"> / unlimited</span>
                ) : (
                    <span className="text-ink-muted text-base"> / {cap}</span>
                )}
            </p>
        </div>
    );
}

function CreateNewForm({ disabled }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        owner_name: '',
        owner_email: '',
        owner_password: '',
        owner_password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('practice.clients.create.new'), {
            onSuccess: () => reset('owner_password', 'owner_password_confirmation'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <p className="text-sm text-ink-muted">
                We'll create the tenant on the free <b>Startup</b> plan, link it to your firm, and set the owner up
                with the credentials you choose. You can switch into their books from the dashboard.
            </p>

            <Field label="Company / business name" error={errors.company_name}>
                <input
                    type="text"
                    value={data.company_name}
                    onChange={(e) => setData('company_name', e.target.value)}
                    className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                    placeholder="ABC Trading Sdn Bhd"
                    disabled={disabled}
                    required
                />
            </Field>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Owner full name" error={errors.owner_name}>
                    <input
                        type="text"
                        value={data.owner_name}
                        onChange={(e) => setData('owner_name', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                        disabled={disabled}
                        required
                    />
                </Field>
                <Field label="Owner email" error={errors.owner_email}>
                    <input
                        type="email"
                        value={data.owner_email}
                        onChange={(e) => setData('owner_email', e.target.value.toLowerCase())}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                        disabled={disabled}
                        required
                    />
                </Field>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Owner password" error={errors.owner_password}>
                    <input
                        type="password"
                        value={data.owner_password}
                        onChange={(e) => setData('owner_password', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        disabled={disabled}
                        required
                    />
                </Field>
                <Field label="Confirm password" error={errors.owner_password_confirmation}>
                    <input
                        type="password"
                        value={data.owner_password_confirmation}
                        onChange={(e) => setData('owner_password_confirmation', e.target.value)}
                        className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                        autoComplete="new-password"
                        disabled={disabled}
                        required
                    />
                </Field>
            </div>

            <p className="text-xs text-ink-muted">
                Make sure to share the password with the owner securely. They can change it after first login from
                their profile settings.
            </p>

            <button
                type="submit"
                disabled={disabled || processing}
                className="px-5 py-2.5 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark transition-colors disabled:bg-border-warm disabled:text-ink-muted disabled:cursor-not-allowed"
            >
                {processing ? 'Creating…' : 'Create client'}
            </button>
        </form>
    );
}

function InviteExistingForm({ disabled }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('practice.clients.invite'), {
            onSuccess: () => reset('email'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <p className="text-sm text-ink-muted">
                Already have a BukuCloud account? Type your client's email — they'll see a one-click accept inside
                their account, and once they accept, their books link to your firm.
            </p>

            <Field label="Client's BukuCloud email" error={errors.email}>
                <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value.toLowerCase())}
                    className="mt-1.5 block w-full rounded-xl border-border-warm text-ink focus:border-terracotta focus:ring-terracotta"
                    placeholder="finance@clientco.my"
                    disabled={disabled}
                    required
                />
            </Field>

            <div className="bg-cream/40 border border-border-warm rounded-xl px-4 py-3 text-xs text-ink-muted">
                <p className="font-semibold text-ink">How it works</p>
                <ol className="mt-1.5 space-y-0.5 list-decimal pl-4">
                    <li>We mark a pending invite on their account.</li>
                    <li>Next time they sign in they'll see your firm name and an "Accept" button in Settings.</li>
                    <li>On accept, their tenant links to your firm — instantly visible on your dashboard.</li>
                </ol>
            </div>

            <button
                type="submit"
                disabled={disabled || processing}
                className="px-5 py-2.5 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark transition-colors disabled:bg-border-warm disabled:text-ink-muted disabled:cursor-not-allowed"
            >
                {processing ? 'Sending invite…' : 'Send invite'}
            </button>
        </form>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="text-sm font-semibold text-ink">{label}</label>
            {children}
            {error && <p className="text-xs text-terracotta mt-1">{error}</p>}
        </div>
    );
}
