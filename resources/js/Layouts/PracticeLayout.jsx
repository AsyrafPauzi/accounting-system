import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import WelcomeModal from '@/Components/WelcomeModal';
import VerifyEmailReminderModal from '@/Components/VerifyEmailReminderModal';
import { shouldShowVerifyReminder } from '@/Utils/verifyReminder';

/**
 * Wrapper for the Practice console (firm-side). Deliberately *not*
 * using AuthenticatedLayout because that layout assumes the user is
 * inside a tenant, mounts tenant-scoped sidebar items, and pulls
 * tenant-scoped shared props (theme, branding, plan permissions) we
 * don't have at the firm level.
 *
 * The shape mirrors the SME app's layout enough that switching between
 * "practice console" and "inside a client" doesn't feel like a
 * different application — same colors, typography, top-bar position.
 */
export default function PracticeLayout({ children, header }) {
    const { auth, product_name: productName } = usePage().props;
    const user = auth?.user;
    const isImpersonating = Boolean(auth?.impersonator_id);
    const displayName = productName || 'BukuCloud';

    // Mirror AuthenticatedLayout's welcome-tour gate: firm-owner sees
    // the firm-flavoured tour the first time they hit the console
    // post-verification.
    const [welcomeOpen, setWelcomeOpen] = useState(
        Boolean(user?.email_verified_at) && !user?.welcomed_at && !isImpersonating
    );
    const [verifyReminderOpen, setVerifyReminderOpen] = useState(
        shouldShowVerifyReminder(user, isImpersonating)
    );

    return (
        <div className="min-h-screen bg-cream text-ink">
            <header className="bg-surface border-b border-border-warm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                    <div className="flex items-center gap-3">
                        <ApplicationLogo className="h-8 w-auto" />
                        <span className="text-eyebrow font-semibold uppercase text-terracotta tracking-wider">
                            Practice
                        </span>
                    </div>

                    <nav className="flex items-center gap-6">
                        <Link
                            href={route('practice.dashboard')}
                            className="text-sm font-semibold text-ink hover:text-terracotta transition-colors"
                        >
                            Dashboard
                        </Link>
                        {/*
                          * Lands on the rich firm Plan & usage page
                          * (Settings/PlanFirm.jsx) so the firm-owner
                          * sees their current plan, seat usage, and
                          * client-cap usage first. The "Change plan"
                          * CTA on that page deep-links to the actual
                          * picker at /practice/plan.
                          */}
                        <Link
                            href={route('settings.plan.index')}
                            className="text-sm text-ink-muted hover:text-ink transition-colors"
                        >
                            Plan & billing
                        </Link>
                        <Link
                            href={route('profile.edit')}
                            className="text-sm text-ink-muted hover:text-ink transition-colors"
                        >
                            {user?.name ?? 'Profile'}
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="text-sm text-ink-muted hover:text-terracotta transition-colors"
                        >
                            Sign out
                        </Link>
                    </nav>
                </div>
            </header>

            {header && (
                <div className="bg-surface border-b border-border-warm">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        {header}
                    </div>
                </div>
            )}

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>

            <footer className="border-t border-border-warm py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xs text-ink-muted text-center">
                    &copy; {new Date().getFullYear()} {displayName} · Practice Console
                </div>
            </footer>

            <WelcomeModal
                show={welcomeOpen}
                isFirm={true}
                onClose={() => setWelcomeOpen(false)}
            />

            <VerifyEmailReminderModal
                show={verifyReminderOpen}
                onClose={() => setVerifyReminderOpen(false)}
            />
        </div>
    );
}
