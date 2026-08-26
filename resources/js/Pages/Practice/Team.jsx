import PracticeLayout from '@/Layouts/PracticeLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border border-border-warm text-sm font-medium text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta';
const labelClass =
    'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider';

export default function Team({ firm, staff = [], seatStatus = {} }) {
    const { flash = {} } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('practice.team.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const removeMember = (member) => {
        if (! window.confirm(`Remove ${member.name} from ${firm.name}?`)) {
            return;
        }
        router.delete(route('practice.team.destroy', member.id), { preserveScroll: true });
    };

    const atLimit = seatStatus.can_add === false;

    return (
        <PracticeLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">{firm.name}</p>
                        <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                            Firm team
                        </h1>
                        <p className="text-ink-muted text-sm mt-1">
                            Invite colleagues who work at your firm — not your clients&apos; SME staff.
                        </p>
                    </div>
                    <Link
                        href={route('practice.dashboard')}
                        className="text-sm font-semibold text-terracotta hover:text-terracotta-dark"
                    >
                        ← Practice console
                    </Link>
                </div>
            }
        >
            <Head title="Firm team" />

            {(flash.success || flash.error) && (
                <div className="mb-6 space-y-2">
                    {flash.success && (
                        <div className="bg-forest/10 border border-forest/30 rounded-2xl px-5 py-3 text-sm text-forest-dark">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div className="bg-terracotta/10 border border-terracotta/40 rounded-2xl px-5 py-3 text-sm text-terracotta-dark">
                            {flash.error}
                        </div>
                    )}
                </div>
            )}

            <div className="max-w-4xl space-y-8">
                <div className="bg-surface border border-border-warm rounded-2xl p-5">
                    <p className="text-eyebrow font-semibold uppercase text-ink-muted text-[10px]">Seat usage</p>
                    <p className="mt-1 font-display text-xl text-ink">
                        {seatStatus.used ?? 0}
                        <span className="text-ink-muted text-base"> / {seatStatus.total_seats ?? 1} firm staff</span>
                    </p>
                    {seatStatus.plan_name && (
                        <p className="text-xs text-ink-muted mt-1">{seatStatus.plan_name} plan</p>
                    )}
                </div>

                <section className="bg-surface border border-border-warm rounded-3xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm">
                        <h2 className="font-display text-lg font-medium text-ink">Current team</h2>
                    </div>
                    <ul className="divide-y divide-border-warm">
                        {staff.map((member) => (
                            <li key={member.id} className="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p className="font-semibold text-ink">
                                        {member.name}
                                        {member.is_self && (
                                            <span className="ml-2 text-xs text-ink-muted">(you)</span>
                                        )}
                                    </p>
                                    <p className="text-sm text-ink-muted">{member.email}</p>
                                </div>
                                <div className="flex items-center gap-4">
                                    <span className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                        {member.is_owner ? 'Owner' : 'Staff'}
                                    </span>
                                    {! member.is_owner && ! member.is_self && (
                                        <button
                                            type="button"
                                            onClick={() => removeMember(member)}
                                            className="text-xs font-semibold text-terracotta hover:text-terracotta-dark"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="bg-surface border border-border-warm rounded-3xl p-6">
                    <h2 className="font-display text-lg font-medium text-ink mb-1">Invite firm staff</h2>
                    <p className="text-sm text-ink-muted mb-5">
                        They&apos;ll get their own login to the Practice console and can enter client books based on your firm permissions.
                    </p>

                    {atLimit && (
                        <div className="mb-5 p-4 rounded-xl border border-terracotta/40 bg-terracotta/10 text-sm text-terracotta">
                            <p className="font-semibold">All {seatStatus.total_seats} staff seats are in use.</p>
                            <p className="mt-1 text-ink-muted">
                                <Link href={route('settings.plan.index')} className="font-semibold text-terracotta underline hover:no-underline">
                                    Upgrade your plan
                                </Link>{' '}
                                to invite more colleagues.
                            </p>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4 max-w-lg">
                        <div>
                            <label htmlFor="name" className={labelClass}>Full name</label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={inputClass}
                                disabled={atLimit || processing}
                            />
                            {errors.name && <p className="text-xs text-terracotta mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label htmlFor="email" className={labelClass}>Email</label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className={inputClass}
                                disabled={atLimit || processing}
                            />
                            {errors.email && <p className="text-xs text-terracotta mt-1">{errors.email}</p>}
                        </div>
                        <div>
                            <label htmlFor="password" className={labelClass}>Temporary password</label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className={inputClass}
                                disabled={atLimit || processing}
                            />
                            {errors.password && <p className="text-xs text-terracotta mt-1">{errors.password}</p>}
                        </div>
                        <div>
                            <label htmlFor="password_confirmation" className={labelClass}>Confirm password</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className={inputClass}
                                disabled={atLimit || processing}
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={atLimit || processing}
                            className="px-4 py-2.5 rounded-xl bg-terracotta text-white font-semibold text-sm hover:bg-terracotta-dark disabled:opacity-60 transition-colors"
                        >
                            {processing ? 'Inviting…' : 'Invite staff member'}
                        </button>
                    </form>
                </section>
            </div>
        </PracticeLayout>
    );
}
