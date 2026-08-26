import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Modal from '@/Components/Modal';

/**
 * Post-signup welcome tour. Shown once per user, on the first dashboard
 * visit after they verify their email. Two paths dismiss it (both POST
 * to `onboarding.dismiss` which sets users.welcomed_at = now()):
 *
 *   - "Skip for now"          — header link + final-step button
 *   - "Get started"           — completes the tour from the last step
 *
 * Content is role-aware. Firm users (auth.user.firm_id is set) see the
 * Practice console flow; SME users see the books-for-my-business flow.
 * The lists are deliberately short — three things per step — because
 * onboarding tours longer than three steps get skipped.
 */
export default function WelcomeModal({ show, onClose, isFirm = false }) {
    const page = usePage();
    const userName = page.props.auth?.user?.name ?? '';
    const productName = page.props.product_name ?? 'BukuCloud';
    // SME signups land on a 14-day Solo trial — auth.trial is
    // populated for them by HandleInertiaRequests. Firm users never get
    // a trial today, so the firm path always reads the non-trial copy.
    const trial = !isFirm ? page.props.auth?.trial ?? null : null;

    const [step, setStep] = useState(0);
    const [submitting, setSubmitting] = useState(false);

    const dismiss = () => {
        if (submitting) return;
        setSubmitting(true);
        router.post(
            route('onboarding.dismiss'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setSubmitting(false);
                    onClose?.();
                },
            },
        );
    };

    const steps = isFirm ? firmSteps(productName) : smeSteps(productName, trial);
    const last = step === steps.length - 1;
    const current = steps[step];

    return (
        <Modal show={show} maxWidth="2xl" closeable={false} onClose={() => {}}>
            <div className="bg-cream">
                <div className="px-6 sm:px-8 pt-6 pb-4 flex items-start justify-between gap-4 border-b border-border-warm">
                    <div className="min-w-0">
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">
                            {isFirm ? 'For your firm' : 'Welcome aboard'}
                        </p>
                        <h2 className="font-display text-2xl sm:text-3xl text-ink tracking-tight mt-1">
                            {current.title}
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={dismiss}
                        disabled={submitting}
                        className="text-eyebrow font-semibold uppercase text-ink-muted hover:text-ink transition-colors flex-shrink-0 mt-1 disabled:opacity-50"
                    >
                        Skip for now
                    </button>
                </div>

                <div className="px-6 sm:px-8 py-6">
                    {step === 0 && (
                        <p className="text-ink leading-relaxed">
                            Hi <strong>{userName.split(' ')[0] || 'there'}</strong> — your email is verified and your account is ready.
                            Take 30 seconds to see what you can do, or skip ahead and explore on your own.
                        </p>
                    )}

                    {step > 0 && (
                        <ul className="space-y-3">
                            {current.items.map((item, idx) => (
                                <li
                                    key={idx}
                                    className="flex items-start gap-3 p-3 rounded-xl bg-surface border border-border-warm"
                                >
                                    <span className="flex-shrink-0 w-7 h-7 rounded-lg bg-terracotta/10 text-terracotta flex items-center justify-center font-mono text-xs font-semibold mt-0.5">
                                        {idx + 1}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="font-semibold text-ink text-sm">{item.title}</p>
                                        <p className="text-ink-muted text-sm mt-0.5">{item.body}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    {current.footnote && (
                        <p className="text-ink-muted text-xs mt-4 italic">{current.footnote}</p>
                    )}
                </div>

                <div className="px-6 sm:px-8 py-4 bg-surface-alt border-t border-border-warm flex items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5">
                        {steps.map((_, idx) => (
                            <span
                                key={idx}
                                className={`h-1.5 rounded-full transition-all ${
                                    idx === step
                                        ? 'w-6 bg-terracotta'
                                        : idx < step
                                            ? 'w-1.5 bg-terracotta/50'
                                            : 'w-1.5 bg-border-warm'
                                }`}
                            />
                        ))}
                        <span className="ml-2 text-eyebrow font-semibold text-ink-muted uppercase">
                            {step + 1} / {steps.length}
                        </span>
                    </div>

                    <div className="flex items-center gap-2">
                        {step > 0 && (
                            <button
                                type="button"
                                onClick={() => setStep((s) => Math.max(0, s - 1))}
                                disabled={submitting}
                                className="px-4 py-2 rounded-xl text-sm font-semibold text-ink hover:bg-cream transition-colors disabled:opacity-50"
                            >
                                Back
                            </button>
                        )}
                        {!last ? (
                            <button
                                type="button"
                                onClick={() => setStep((s) => s + 1)}
                                className="px-5 py-2 rounded-xl text-sm font-semibold bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors"
                            >
                                Next →
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={dismiss}
                                disabled={submitting}
                                className="px-5 py-2 rounded-xl text-sm font-semibold bg-terracotta text-white hover:bg-terracotta-dark dark:hover:bg-terracotta-light transition-colors disabled:opacity-50"
                            >
                                {submitting ? 'Saving…' : 'Get started'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </Modal>
    );
}

function smeSteps(productName, trial = null) {
    // Trial users land on the configured trial plan (Solo by default)
    // for 14 days. Their step 1 welcome and step 3 plan copy both need
    // to mention the trial explicitly so they understand both (a) why
    // they have access to Solo features on day 1 and (b) what happens
    // when the timer hits zero. Non-trial copy is the historical
    // free-from-day-one flow (preserved for the trial-disabled config).
    if (trial) {
        const daysLabel = trial.days_left === 0
            ? 'today'
            : `${trial.days_left} day${trial.days_left === 1 ? '' : 's'}`;
        const fallbackName = trial.fallback_name || 'Startup (Free)';
        const planName = trial.plan_name || 'Solo';
        return [
            {
                title: `Welcome to ${productName}.`,
                footnote: `You're on a ${planName} free trial — core paid bookkeeping features unlocked, no card required.`,
            },
            {
                title: 'Here\'s what you can do.',
                items: [
                    {
                        title: 'Send invoices and track payments',
                        body: 'Create estimates and invoices, email them to customers, and record payments. Customers and credit notes live in the same place.',
                    },
                    {
                        title: 'Record bills and pay suppliers',
                        body: 'Add suppliers, capture bills with the OCR receipt scanner, and track what you owe in Accounts Payable — all unlocked during your trial.',
                    },
                    {
                        title: 'See the numbers that matter',
                        body: 'Open the Dashboard for cash position, then Reports for P&L and sales summaries. Higher-tier reports unlock if you upgrade later.',
                    },
                ],
            },
            {
                title: `${daysLabel === 'today' ? 'Trial ends today.' : daysLabel + ' left in your free trial.'} Pick a plan to keep the paid features.`,
                items: [
                    {
                        title: `Auto-switch to ${fallbackName} when the trial ends`,
                        body: `On day 15 we automatically move your account to ${fallbackName}. Your data stays put — Solo-only features just become disabled or hidden until you upgrade.`,
                    },
                    {
                        title: 'Upgrade any time from Settings → Plan',
                        body: 'Pick Solo (RM 49/mo), Growth (RM 99/mo), or Corporate (RM 219/mo). Pay via Toyyibpay (FPX / card / e-wallet); monthly or yearly (~17% off).',
                    },
                    {
                        title: 'Need more than 5 users or a self-hosted deployment?',
                        body: 'Talk to sales — Enterprise quotes include white-label, SLAs, and the self-hosted option for keeping data on your own infra.',
                    },
                ],
                footnote: 'You can also invite your accountant from Settings → Invite my accountant.',
            },
        ];
    }

    return [
        {
            title: `Welcome to ${productName}.`,
        },
        {
            title: 'Here\'s what you can do.',
            items: [
                {
                    title: 'Send invoices and track payments',
                    body: 'Create and post invoices, record payments, and add customers — included on Startup (Free).',
                },
                {
                    title: 'Upgrade for bills and credit notes',
                    body: 'Supplier bills, credit notes, OCR receipt scanning, and full reports unlock on Solo and above.',
                },
                {
                    title: 'See the numbers that matter',
                    body: 'Open the Dashboard for cash position. P&L and balance sheet reports unlock on paid plans.',
                },
            ],
        },
        {
            title: 'Free covers the basics. Upgrade when you outgrow it.',
            items: [
                {
                    title: 'Free tier banner in the sidebar',
                    body: 'You\'ll see a "You\'re on the Free tier" badge top-left. Click "Upgrade" to compare Solo (RM 49/mo), Growth (RM 99/mo), and Corporate (RM 219/mo).',
                },
                {
                    title: 'Pay via Toyyibpay (FPX, card, e-wallet)',
                    body: 'Subscriptions are monthly or yearly (~17% off). You can downgrade or cancel any time from Settings → Plan & Usage.',
                },
                {
                    title: 'Need more than 5 users?',
                    body: 'Talk to sales — Enterprise quotes include white-label, SLAs, and the self-hosted option for keeping data on your own infra.',
                },
            ],
            footnote: 'You can also invite your accountant from Settings → Invite my accountant.',
        },
    ];
}

function firmSteps(productName) {
    return [
        {
            title: `Welcome to ${productName} Practice.`,
        },
        {
            title: 'Run your whole practice from one console.',
            items: [
                {
                    title: 'Add or invite client books',
                    body: 'From the Practice console, click "Add Client" to provision a new client tenant, or invite an existing SME by email so they hand you the keys.',
                },
                {
                    title: 'Switch into a client in one click',
                    body: 'Every change you make inside a client is logged against your firm. "Back to firm" returns you to the console — no second login.',
                },
                {
                    title: 'Cross-client reports (Practice Growth and up)',
                    body: 'Once you upgrade, the AR aging and SST reports span every client at once. Bulk-action toolbar lets you act on many at a time.',
                },
            ],
        },
        {
            title: 'You\'re on Practice Free. Upgrade when you add a 2nd client.',
            items: [
                {
                    title: 'Practice plan badge in the sidebar',
                    body: 'You\'ll see a "Practice Free" badge top-left. The "Upgrade" link takes you to /practice/plan to compare Starter, Growth, and Firm tiers.',
                },
                {
                    title: 'Pricing scales with client count',
                    body: 'Starter (RM 99/mo, 5 clients) → Growth (RM 249/mo, 25 clients, cross-client reports) → Firm (RM 599/mo, unlimited, custom branding).',
                },
                {
                    title: 'Need self-hosted or unlimited seats?',
                    body: 'Talk to sales — Practice Self-hosted runs the same product on your infrastructure with a license-based entitlement.',
                },
            ],
            footnote: 'Tip: paid → paid upgrades go through Toyyibpay automatically — you never need to "talk to sales" for tier moves.',
        },
    ];
}
