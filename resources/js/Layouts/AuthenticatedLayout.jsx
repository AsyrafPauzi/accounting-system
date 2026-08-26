import React, { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import NavSidebar from '@/Components/NavSidebar';
import { Link, usePage } from '@inertiajs/react';
import MobileQuickAction from '@/Components/MobileQuickAction';
import WelcomeModal from '@/Components/WelcomeModal';
import AccountantCopilot from '@/Components/AccountantCopilot';
import VerifyEmailReminderModal from '@/Components/VerifyEmailReminderModal';
import { shouldShowVerifyReminder } from '@/utils/verifyReminder';

const Icons = {
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ShoppingCart: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>,
    DocumentCheck: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Sparkles: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5zM19 11l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z" /></svg>,
    Menu: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" /></svg>,
};

export default function Authenticated({ user: propUser, header, children }) {
    const page = usePage();
    const { flash, auth } = page.props;
    const user = propUser || auth?.user || {};
    const teamPermissions = auth?.teamPermissions ?? { view: false, create: false, edit: false, delete: false };
    const isAdmin = user?.role_name === 'super-admin';
    const isImpersonating = Boolean(auth?.impersonator_id);
    const practice = page.props.practice;
    const isFirmActingOnClient = Boolean(practice?.is_inside_client);

    const [sidebarOpen, setSidebarOpen] = useState(false);

    const [welcomeOpen, setWelcomeOpen] = useState(
        Boolean(user?.email_verified_at) && !user?.welcomed_at && !isImpersonating && !isAdmin
    );

    const [verifyReminderOpen, setVerifyReminderOpen] = useState(
        shouldShowVerifyReminder(user, isImpersonating)
    );

    const isRouteActive = (routeName) => {
        try {
            return route().current(routeName);
        } catch {
            return false;
        }
    };

    useEffect(() => {
        if (sidebarOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => { document.body.style.overflow = ''; };
    }, [sidebarOpen]);

    useEffect(() => {
        if (flash?.success || flash?.error || flash?.info) {
            const mainContent = document.querySelector('main');
            if (mainContent) mainContent.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [flash]);

    return (
        <div className="flex h-screen bg-cream overflow-hidden font-sans">
            <div
                aria-hidden="true"
                className={`fixed inset-0 z-30 bg-ink/40 backdrop-blur-sm transition-opacity duration-200 lg:hidden ${sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
                onClick={() => setSidebarOpen(false)}
            />

            <NavSidebar sidebarOpen={sidebarOpen} onSidebarOpenChange={setSidebarOpen} />

            <div className="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-transparent pb-20 lg:pb-0">
                <div className="lg:hidden flex-shrink-0 flex items-center justify-between px-4 py-3 bg-surface/90 backdrop-blur-lg border-b border-border-warm z-20 sticky top-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-cream">
                            <ApplicationLogo className="block h-6 w-6" />
                        </div>
                        <div className="min-w-0">
                            <span className="font-display font-medium text-ink tracking-tight truncate block text-sm">{page.props.product_name}</span>
                            {String(page.props.product_tagline ?? '').trim() !== '' && (
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase truncate block">{page.props.product_tagline}</span>
                            )}
                        </div>
                    </div>
                </div>

                {header && (
                    <header className="flex-shrink-0 sticky top-0 bg-surface/90 backdrop-blur-md border-b border-border-warm z-20">
                        <div className="page-app-header max-w-full mx-auto py-3 sm:py-4 px-4 sm:px-6 lg:px-10">
                            {header}
                        </div>
                    </header>
                )}

                <main className="flex-1 overflow-y-auto overflow-x-hidden p-4 lg:p-6 relative">
                    {isImpersonating && (
                        <div className="max-w-[90rem] mx-auto mb-4 px-4 py-3 rounded-xl bg-mustard/15 border border-mustard/40 text-ink text-sm font-medium flex items-center justify-between">
                            <span>You&apos;re impersonating another user. Actions affect that tenant only.</span>
                            <Link
                                href={route('admin.tenants.stop-impersonating')}
                                method="post"
                                as="button"
                                className="px-3 py-1.5 rounded-lg text-xs font-semibold text-ink bg-mustard/30 hover:bg-mustard/50"
                            >
                                Return to admin
                            </Link>
                        </div>
                    )}

                    {isFirmActingOnClient && (
                        <div className="max-w-[90rem] mx-auto mb-4 px-4 py-3 rounded-xl bg-terracotta/10 border border-terracotta/30 text-ink text-sm font-medium flex items-center justify-between gap-3">
                            <span>
                                <span className="text-eyebrow uppercase font-semibold text-terracotta mr-2">Practice</span>
                                Working in <strong>{practice.acting_client?.name}</strong>
                                {' '}— every change is logged against {practice.firm?.name}.
                            </span>
                            <Link
                                href={route('practice.exit')}
                                method="post"
                                as="button"
                                className="px-3 py-1.5 rounded-lg text-xs font-semibold text-cream bg-terracotta hover:bg-terracotta-dark dark:hover:bg-terracotta-light"
                            >
                                Back to firm
                            </Link>
                        </div>
                    )}

                    {page.props.self_hosted_update && (
                        <div className="max-w-[90rem] mx-auto mb-4 px-4 py-3 rounded-xl bg-mustard/15 border border-mustard/40 text-ink text-sm font-medium flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <span>
                                <span className="text-eyebrow uppercase font-semibold text-mustard-dark dark:text-mustard mr-2">Update available</span>
                                BukuCloud <strong>{page.props.self_hosted_update.available_version}</strong> is now released.
                                You&apos;re running <strong>{page.props.self_hosted_update.current_version}</strong>.
                                {page.props.self_hosted_update.notes && (
                                    <span className="block text-xs text-ink-muted mt-1 whitespace-pre-line">
                                        {page.props.self_hosted_update.notes}
                                    </span>
                                )}
                            </span>
                            {page.props.self_hosted_update.url && (
                                <a
                                    href={page.props.self_hosted_update.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold text-ink bg-mustard/40 hover:bg-mustard/60 shrink-0"
                                >
                                    View release notes
                                </a>
                            )}
                        </div>
                    )}

                    <div className="max-w-[90rem] mx-auto min-w-0">
                        {flash?.success && (
                            <div className="mb-6 p-4 rounded-2xl bg-forest/10 border border-forest/30 flex items-center gap-3 text-forest dark:text-forest-light animate-in fade-in slide-in-from-top-4 duration-300">
                                <div className="p-1.5 bg-forest/15 rounded-lg text-forest dark:text-forest-light">
                                    <Icons.DocumentCheck />
                                </div>
                                <p className="text-sm font-medium">{flash.success}</p>
                            </div>
                        )}

                        {flash?.error && (
                            <div className="mb-6 p-4 rounded-2xl bg-terracotta/10 border border-terracotta/30 flex items-center gap-3 text-terracotta animate-in fade-in slide-in-from-top-4 duration-300">
                                <div className="p-1.5 bg-terracotta/15 rounded-lg text-terracotta">
                                    <Icons.Exclamation />
                                </div>
                                <p className="text-sm font-medium">{flash.error}</p>
                            </div>
                        )}

                        {flash?.info && (
                            <div className="mb-6 p-4 rounded-2xl bg-mustard/15 border border-mustard/40 flex items-center gap-3 text-ink animate-in fade-in slide-in-from-top-4 duration-300">
                                <div className="p-1.5 bg-mustard/30 rounded-lg text-ink">
                                    <Icons.Sparkles />
                                </div>
                                <p className="text-sm font-medium">{flash.info}</p>
                            </div>
                        )}

                        {children}
                    </div>
                </main>
            </div>

            <div className={`lg:hidden fixed bottom-6 left-4 right-4 z-40 transition-transform duration-200 ${sidebarOpen ? 'translate-y-32' : 'translate-y-0'}`}>
                <nav className="bg-surface/90 backdrop-blur-md border border-border-warm rounded-[2rem] shadow-xl shadow-ink/10 p-2 flex items-center justify-between gap-1">
                    <Link
                        href={route('dashboard')}
                        className={`flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl transition-all duration-200 ${isRouteActive('dashboard') ? 'bg-terracotta/10 text-terracotta' : 'text-ink-muted active:bg-surface-alt'}`}
                    >
                        <Icons.ChartBar />
                        <span className="text-[10px] font-semibold uppercase tracking-wider">Home</span>
                    </Link>

                    <Link
                        href={route('invoices.index')}
                        className={`flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl transition-all duration-200 ${isRouteActive('invoices.index') ? 'bg-terracotta/10 text-terracotta' : 'text-ink-muted active:bg-surface-alt'}`}
                    >
                        <Icons.Document />
                        <span className="text-[10px] font-semibold uppercase tracking-wider">Sales</span>
                    </Link>

                    <div className="flex-shrink-0 -mt-8 px-1">
                        <MobileQuickAction permissions={teamPermissions} />
                    </div>

                    <Link
                        href={route('bills.index')}
                        className={`flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl transition-all duration-200 ${isRouteActive('bills.index') ? 'bg-terracotta/10 text-terracotta' : 'text-ink-muted active:bg-surface-alt'}`}
                    >
                        <Icons.ShoppingCart />
                        <span className="text-[10px] font-semibold uppercase tracking-wider">Bills</span>
                    </Link>

                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl text-ink-muted active:bg-surface-alt transition-all duration-200"
                    >
                        <Icons.Menu />
                        <span className="text-[10px] font-semibold uppercase tracking-wider">Menu</span>
                    </button>
                </nav>
            </div>

            <WelcomeModal
                show={welcomeOpen}
                isFirm={Boolean(user?.firm_id)}
                onClose={() => setWelcomeOpen(false)}
            />

            <AccountantCopilot />

            <VerifyEmailReminderModal
                show={verifyReminderOpen}
                onClose={() => setVerifyReminderOpen(false)}
            />
        </div>
    );
}
