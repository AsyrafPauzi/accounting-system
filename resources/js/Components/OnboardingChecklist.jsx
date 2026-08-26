import { Link, router, usePage } from '@inertiajs/react';

export default function OnboardingChecklist() {
    const { onboarding_checklist: checklist } = usePage().props;

    if (! checklist?.visible) {
        return null;
    }

    const pct = checklist.total > 0
        ? Math.round((checklist.completed / checklist.total) * 100)
        : 0;

    return (
        <div className="rounded-2xl border border-terracotta/30 bg-terracotta/5 p-5 sm:p-6">
            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                <div>
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Getting started</p>
                    <h2 className="font-display text-lg font-medium text-ink mt-1">Your day-1 checklist</h2>
                    <p className="text-sm text-ink-muted mt-1">
                        {checklist.completed} of {checklist.total} complete ({pct}%)
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => router.post(route('onboarding.checklist.dismiss'))}
                    className="text-sm font-semibold text-ink-muted hover:text-ink self-start"
                >
                    Dismiss
                </button>
            </div>

            <ul className="space-y-2">
                {checklist.steps.map((step) => (
                    <li key={step.key}>
                        <Link
                            href={step.href}
                            className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition ${
                                step.done
                                    ? 'bg-surface/80 text-ink-muted line-through'
                                    : 'bg-surface hover:bg-white text-ink font-medium'
                            }`}
                        >
                            <span
                                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${
                                    step.done ? 'border-teal bg-teal text-white' : 'border-border-warm'
                                }`}
                                aria-hidden
                            >
                                {step.done ? '✓' : ''}
                            </span>
                            {step.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
