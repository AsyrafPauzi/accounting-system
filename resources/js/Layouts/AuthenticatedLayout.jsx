import React, { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';
import MobileQuickAction from '@/Components/MobileQuickAction';
import { toastSuccess, toastError } from '@/utils/swal';

const Icons = {
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ReceiptRefund: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>,
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    ShoppingCart: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>,
    BuildingOffice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    Folder: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>,
    BookOpen: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>,
    ChartPie: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /></svg>,
    Scale: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    DocumentCheck: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Sparkles: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5zM19 11l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z" /></svg>,
    Menu: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" /></svg>,
    X: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>,
    ChevronDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
};

const navConfig = [
    { group: 'Main', links: [{ name: 'Dashboard', route: 'dashboard', Icon: Icons.ChartBar }] },
    { group: 'Sales (Revenue)', links: [
        { name: 'Invoices', route: 'invoices.index', Icon: Icons.Document, planPermission: 'invoices.view', userPermission: 'invoices.view' },
        { name: 'Credit Notes', route: 'credit-notes.index', Icon: Icons.ReceiptRefund, planPermission: 'credit-notes.view', userPermission: 'credit-notes.view' },
        { name: 'Customers', route: 'customers.index', Icon: Icons.Users, planPermission: 'customers.view', userPermission: 'customers.view' },
    ]},
    { group: 'Purchases (Expenses)', links: [
        { name: 'Suppliers', route: 'suppliers.index', Icon: Icons.BuildingOffice, planPermission: 'suppliers.view', userPermission: 'suppliers.view' },
        { name: 'Bills / Purchases', route: 'bills.index', Icon: Icons.ShoppingCart, planPermission: 'bills.view', userPermission: 'bills.view' },
        { name: 'Accounts Payable', route: 'accounts-payable.index', Icon: Icons.Document, planPermission: 'reports.aged-reports', userPermission: 'reports.aged-reports', subtitle: 'Outstanding and aging' },
    ]},
    { group: 'Accounting', links: [
        { name: 'Chart of Accounts', route: 'chart-of-accounts.index', Icon: Icons.Folder, planPermission: 'accounts.view', userPermission: 'accounts.view', subtitle: 'Accounts used in postings and reports' },
        { name: 'General Ledger', route: 'general-ledger.index', Icon: Icons.BookOpen, planPermission: 'general-ledger.view', userPermission: 'general-ledger.view', subtitle: 'By journal entry' },
        { name: 'Manual Journal Entry', route: 'journal.index', Icon: Icons.Scale, planPermission: 'journal.create', userPermission: 'journal.view', subtitle: 'Post custom journal entries' },
        { name: 'Trial Balance', route: 'trial-balance.index', Icon: Icons.Scale, planPermission: 'general-ledger.view', userPermission: 'general-ledger.view', subtitle: 'Verify account balances' },
    ]},
    { group: 'Reports', links: [
        { name: 'Reports', route: 'reports.index', Icon: Icons.ChartPie, planPermission: 'reports.view', userPermission: 'reports.view', subtitle: 'Financial statements & analysis', activeRoutes: ['reports.index', 'general-ledger.report', 'profit-and-loss.index', 'reports.sales.index', 'balance-sheet.index', 'cashflow-summary.index', 'aged-receivables.index'] },
    ]},
];


export default function Authenticated({ user: propUser, header, children }) {
    const page = usePage();
    const { flash, auth } = page.props;
    const url = page.url;
    const user = propUser || auth?.user || {};
    const hasActiveSubscription = auth?.hasActiveSubscription ?? false;
    const teamPermissions = auth?.teamPermissions ?? { view: false, create: false, edit: false, delete: false };
    const isAdmin = user?.role_name === 'super-admin';
    const isImpersonating = Boolean(auth?.impersonator_id);
    const planPermissions = auth?.planPermissions ?? {};
    const permissions = auth?.permissions ?? [];

    const hasPermission = (p) => permissions.includes(p) || isAdmin;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [openGroups, setOpenGroups] = useState({});

    // Toggle group open/close
    const toggleGroup = (groupName) => {
        setOpenGroups(prev => ({
            ...prev,
            [groupName]: !prev[groupName]
        }));
    };

    // Auto-expand active group on load
    useEffect(() => {
        const initialOpenGroups = {};
        navConfig.forEach(section => {
            const hasActive = section.links.some(link => 
                link.activeRoutes 
                    ? link.activeRoutes.some(r => isRouteActive(r))
                    : isRouteActive(link.route)
            );
            if (hasActive) {
                initialOpenGroups[section.group] = true;
            }
        });
        
        // Also check Admin and Company groups
        if (isRouteActive('admin.tenants.index')) initialOpenGroups['Admin'] = true;
        if (isRouteActive('settings.company') || isRouteActive('settings.team.index') || isRouteActive('audit-logs.index') || isRouteActive('settings.plan.index')) {
            initialOpenGroups['Company'] = true;
        }

        setOpenGroups(initialOpenGroups);
    }, [url]);

    // Close mobile sidebar on route change (e.g. after clicking a link)
    useEffect(() => {
        setSidebarOpen(false);
    }, [url]);

    // Prevent body scroll when mobile sidebar is open
    useEffect(() => {
        if (sidebarOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => { document.body.style.overflow = ''; };
    }, [sidebarOpen]);

    // Helper to check if route exists and is active
    const isRouteActive = (routeName) => {
        try {
            return route().current(routeName);
        } catch (e) {
            return false;
        }
    };

    // Helper to get safe URL
    const getSafeRoute = (routeName) => {
        try {
            return route(routeName);
        } catch (e) {
            return '#'; 
        }
    };

    // Flash messages are now handled via static banners in the main content area.
    useEffect(() => {
        if (flash?.success || flash?.error || flash?.info) {
            // Scroll to top to ensure the user sees the persistent banner
            const mainContent = document.querySelector('main');
            if (mainContent) mainContent.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [flash]);

    return (
        <div className="flex h-screen bg-gradient-to-br from-slate-100 via-indigo-50/70 to-violet-100/80 overflow-hidden font-sans">
            {/* Mobile overlay when sidebar is open */}
            <div
                aria-hidden="true"
                className={`fixed inset-0 z-30 bg-indigo-950/40 backdrop-blur-sm transition-opacity duration-200 lg:hidden ${sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
                onClick={() => setSidebarOpen(false)}
            />

            {/* LEFT COLUMN: SIDEBAR — drawer on mobile, static on lg */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-72 flex flex-col border-r border-indigo-200 bg-white shadow-xl shadow-indigo-200/30 custom-scrollbar transform transition-transform duration-200 ease-out lg:relative lg:z-auto lg:translate-x-0 lg:flex-shrink-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}
            >
                {/* Brand + mobile close */}
                <div className="p-4 sm:p-6 flex items-center justify-between gap-3 border-b border-indigo-100/80 bg-white">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 via-violet-600 to-indigo-700 shadow-lg shadow-indigo-500/35 ring-2 ring-white/80">
                            <ApplicationLogo className="block h-6 w-auto fill-current text-white" />
                        </div>
                        <div className="min-w-0">
                            <span className="font-bold text-indigo-950 tracking-tight text-base block truncate">Accounter</span>
                            <span className="block text-[10px] font-semibold text-indigo-600/80 uppercase tracking-wider">Malaysia Edition</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(false)}
                        className="lg:hidden p-2 -m-2 rounded-xl text-indigo-600 hover:text-indigo-900 hover:bg-indigo-100/80 transition-colors"
                        aria-label="Close menu"
                    >
                        <Icons.X />
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 py-5 overflow-y-auto px-3 bg-white">
                    {!hasActiveSubscription && !isAdmin && (
                        <div className="mb-4 mx-1 px-3 py-2 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/80 text-amber-900 text-[11px] font-medium flex items-center justify-between shadow-sm shadow-amber-100/50">
                            <span>You&apos;re on Free tier.</span>
                            <Link
                                href={getSafeRoute('subscription.index')}
                                className="ml-2 text-[10px] font-bold uppercase tracking-widest text-amber-800 underline-offset-2 hover:underline"
                            >
                                Upgrade
                            </Link>
                        </div>
                    )}
                    {navConfig.map((section, idx) => {
                        // Hide all standard groups for Super Admin (Main, Sales, etc.)
                        if (isAdmin && section.group !== 'Admin') return null;

                        const visibleLinks = section.links.filter(link => {
                            const planOk = !link.planPermission || planPermissions[link.planPermission];
                            const userOk = !link.userPermission || hasPermission(link.userPermission);
                            return planOk && userOk;
                        });
                        
                        if (visibleLinks.length === 0) return null;
                        const isOpen = openGroups[section.group];

                        return (
                            <div key={idx} className="mb-2">
                                <button 
                                    onClick={() => toggleGroup(section.group)}
                                    className="w-full flex items-center justify-between px-3 py-2 text-[10px] font-bold text-indigo-600/90 uppercase tracking-widest hover:bg-indigo-50/50 rounded-lg transition-colors group"
                                >
                                    <span>{section.group}</span>
                                    <span className={`transition-transform duration-200 ${isOpen ? 'rotate-0' : '-rotate-90 text-indigo-300'}`}>
                                        <Icons.ChevronDown />
                                    </span>
                                </button>
                                
                                <div className={`mt-1 space-y-0.5 overflow-hidden transition-all duration-300 ${isOpen ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                    {visibleLinks.map((link) => {
                                        const Icon = link.Icon;
                                        const active = link.activeRoutes
                                            ? link.activeRoutes.some((r) => isRouteActive(r))
                                            : isRouteActive(link.route);
                                        const isPaidOnly = link.requirePaid;
                                        const disabled = isPaidOnly && !hasActiveSubscription;
                                        return (
                                            <Link
                                                key={link.name}
                                                href={disabled ? route('subscription.index') : getSafeRoute(link.route)}
                                                className={`flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                                    active
                                                        ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                                        : disabled
                                                            ? 'text-slate-400 hover:text-slate-600 hover:bg-white/60'
                                                            : 'text-slate-700 hover:bg-indigo-100/70 hover:text-indigo-950'
                                                }`}
                                            >
                                                <span className={active ? 'text-white' : 'text-indigo-500/80'}>
                                                    <Icon />
                                                </span>
                                                <span className="flex-1 min-w-0">
                                                    <span className="block truncate">{link.name}</span>
                                                    {link.subtitle && (
                                                        <span className={`block text-[10px] font-normal mt-0.5 truncate ${active ? 'text-indigo-100' : 'text-slate-500'}`}>
                                                            {link.subtitle}
                                                        </span>
                                                    )}
                                                </span>
                                                {active && (
                                                    <span className="w-1.5 h-1.5 rounded-full bg-white/90 flex-shrink-0 shadow-sm" />
                                                )}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    {isAdmin && (
                        <div className="mb-2">
                            <button 
                                onClick={() => toggleGroup('Admin')}
                                className="w-full flex items-center justify-between px-3 py-2 text-[10px] font-bold text-indigo-600/90 uppercase tracking-widest hover:bg-indigo-50/50 rounded-lg transition-colors group"
                            >
                                <span>Admin</span>
                                <span className={`transition-transform duration-200 ${openGroups['Admin'] ? 'rotate-0' : '-rotate-90 text-indigo-300'}`}>
                                    <Icons.ChevronDown />
                                </span>
                            </button>
                            <div className={`mt-1 space-y-0.5 overflow-hidden transition-all duration-300 ${openGroups['Admin'] ? 'max-h-[200px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                <Link
                                    href={getSafeRoute('admin.tenants.index')}
                                    className={`flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                        isRouteActive('admin.tenants.index')
                                            ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                            : 'text-slate-700 hover:bg-indigo-100/70 hover:text-indigo-950'
                                    }`}
                                >
                                    <span className={isRouteActive('admin.tenants.index') ? 'text-white' : 'text-indigo-500/80'}><Icons.BuildingOffice /></span>
                                    <span className="flex-1">Tenant Admin</span>
                                    {isRouteActive('admin.tenants.index') && <span className="w-1.5 h-1.5 rounded-full bg-white/90" />}
                                </Link>
                            </div>
                        </div>
                    )}

                    {/* Only show Company settings group if NOT a super-admin */}
                    {!isAdmin && (
                        <div className="mb-2">
                            <button 
                                onClick={() => toggleGroup('Company')}
                                className="w-full flex items-center justify-between px-3 py-2 text-[10px] font-bold text-indigo-600/90 uppercase tracking-widest hover:bg-indigo-50/50 rounded-lg transition-colors group"
                            >
                                <span>Company</span>
                                <span className={`transition-transform duration-200 ${openGroups['Company'] ? 'rotate-0' : '-rotate-90 text-indigo-300'}`}>
                                    <Icons.ChevronDown />
                                </span>
                            </button>
                            <div className={`mt-1 space-y-0.5 overflow-hidden transition-all duration-300 ${openGroups['Company'] ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                <Link
                                    href={getSafeRoute('settings.company')}
                                    className={`flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                        isRouteActive('settings.company')
                                            ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                            : 'text-slate-700 hover:bg-indigo-100/70 hover:text-indigo-950'
                                    }`}
                                >
                                    <span className={isRouteActive('settings.company') ? 'text-white' : 'text-indigo-500/80'}>
                                        <Icons.BuildingOffice />
                                    </span>
                                    <span className="flex-1">Company settings</span>
                                </Link>
                                {hasPermission('users.view') && planPermissions['users.view'] && (
                                    <Link
                                        href={getSafeRoute('settings.team.index')}
                                        className={`flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                            isRouteActive('settings.team.index')
                                                ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                                : 'text-slate-700 hover:bg-indigo-100/70 hover:text-indigo-950'
                                        }`}
                                    >
                                        <span className={isRouteActive('settings.team.index') ? 'text-white' : 'text-indigo-500/80'}>
                                            <Icons.Users />
                                        </span>
                                        <span className="flex-1">Team & Roles</span>
                                    </Link>
                                )}
                                {hasPermission('audit-logs.view') && planPermissions['audit-logs.view'] && (
                                    <Link
                                        href={getSafeRoute('audit-logs.index')}
                                        className={`flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                            isRouteActive('audit-logs.index')
                                                ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                                : 'text-slate-700 hover:bg-indigo-100/70 hover:text-indigo-950'
                                        }`}
                                    >
                                        <span className={isRouteActive('audit-logs.index') ? 'text-white' : 'text-indigo-500/80'}>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </span>
                                        <span className="flex-1">Audit Logs</span>
                                    </Link>
                                )}
                                {(user.role_name === 'admin' || user.role_name === 'super-admin') && (
                                    <Link
                                        href={getSafeRoute('settings.plan.index')}
                                        className={`flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                            isRouteActive('settings.plan.index')
                                                ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                                : 'text-slate-700 hover:bg-indigo-100/70 hover:text-indigo-950'
                                        }`}
                                    >
                                        <span className={isRouteActive('settings.plan.index') ? 'text-white' : 'text-indigo-500/80'}>
                                            <Icons.Sparkles />
                                        </span>
                                        <span className="flex-1">Plan & Usage</span>
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </nav>

                {/* User block */}
                <div className="p-4 pb-15 lg:pb-4 border-t border-indigo-100/90 bg-white">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center text-white text-sm font-bold shadow-md shadow-indigo-400/40 ring-2 ring-white/90">
                            {(user.name || 'U').charAt(0)}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-bold text-indigo-950 truncate">{user.name || 'User'}</p>
                            <p className="text-[10px] font-semibold text-indigo-600/75 uppercase tracking-wider truncate">
                                {isImpersonating ? 'Impersonating' : (user.role_name?.replace('-', ' ') || 'User')}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <Link
                            href={route('profile.edit')}
                            className="py-2 rounded-lg text-center text-xs font-bold text-indigo-900 bg-white border border-indigo-200/80 hover:bg-indigo-50 hover:border-indigo-300 transition-colors shadow-sm"
                        >
                            Settings
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="py-2 rounded-lg text-center text-xs font-bold text-rose-700 bg-gradient-to-br from-rose-50 to-orange-50 border border-rose-200/90 hover:from-rose-100 hover:to-orange-50 transition-colors"
                        >
                            Logout
                        </Link>
                    </div>
                </div>
            </aside>

            {/* RIGHT COLUMN: MAIN CONTENT */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-transparent pb-20 lg:pb-0">
                {/* Mobile: top bar with hamburger */}
                <div className="lg:hidden flex-shrink-0 flex items-center justify-between px-4 py-3 bg-white/80 backdrop-blur-lg border-b border-indigo-100/80 z-20 sticky top-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 shadow-sm shadow-indigo-500/20">
                            <ApplicationLogo className="block h-4 w-auto fill-current text-white" />
                        </div>
                        <span className="font-bold text-indigo-950 tracking-tight truncate">Accounter</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-[10px] font-bold text-indigo-600/60 uppercase tracking-widest bg-indigo-50 px-2 py-0.5 rounded-md">MY</span>
                    </div>
                </div>

                {header && (
                    <header className="flex-shrink-0 bg-white/80 backdrop-blur-md border-b border-indigo-100/90 shadow-sm shadow-indigo-100/30 z-10">
                        <div className="max-w-full mx-auto py-4 sm:py-6 px-4 sm:px-6 lg:px-10">
                            {header}
                        </div>
                    </header>
                )}

                <main className="flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6 lg:p-8 relative">
                    {/* Flash messages */}
                    {isImpersonating && (
                        <div className="max-w-7xl mx-auto mb-4 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium flex items-center justify-between">
                            <span>
                                You are impersonating another user. Actions you take affect that tenant only.
                            </span>
                            <Link
                                href={route('admin.tenants.stop-impersonating')}
                                method="post"
                                as="button"
                                className="px-3 py-1.5 rounded-lg text-xs font-semibold text-amber-900 bg-amber-200 hover:bg-amber-300"
                            >
                                Return to admin
                            </Link>
                        </div>
                    )}

                    <div className="max-w-7xl mx-auto min-w-0">
                        {/* Static Flash Banners (Reverted from toasts) */}
                        {flash?.success && (
                            <div className="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center gap-3 text-emerald-800 animate-in fade-in slide-in-from-top-4 duration-300 shadow-sm shadow-emerald-100">
                                <div className="p-1.5 bg-emerald-100 rounded-lg text-emerald-600">
                                    <Icons.DocumentCheck />
                                </div>
                                <p className="text-sm font-bold">{flash.success}</p>
                            </div>
                        )}

                        {flash?.error && (
                            <div className="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 flex items-center gap-3 text-rose-800 animate-in fade-in slide-in-from-top-4 duration-300 shadow-sm shadow-rose-100">
                                <div className="p-1.5 bg-rose-100 rounded-lg text-rose-600">
                                    <Icons.Exclamation />
                                </div>
                                <p className="text-sm font-bold">{flash.error}</p>
                            </div>
                        )}

                        {flash?.info && (
                            <div className="mb-6 p-4 rounded-2xl bg-blue-50 border border-blue-200 flex items-center gap-3 text-blue-800 animate-in fade-in slide-in-from-top-4 duration-300 shadow-sm shadow-blue-100">
                                <div className="p-1.5 bg-blue-100 rounded-lg text-blue-600">
                                    <Icons.Sparkles />
                                </div>
                                <p className="text-sm font-bold">{flash.info}</p>
                            </div>
                        )}

                        {children}
                    </div>
                </main>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `
                .custom-scrollbar::-webkit-scrollbar { width: 5px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: rgba(224, 231, 255, 0.5); border-radius: 10px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: #a5b4fc; border-radius: 10px; }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #818cf8; }
            `}} />

            {/* MOBILE BOTTOM NAVIGATION */}
            <div className={`lg:hidden fixed bottom-6 left-4 right-4 z-40 transition-transform duration-200 ${sidebarOpen ? 'translate-y-32' : 'translate-y-0'}`}>
                <nav className="glass rounded-[2rem] shadow-2xl shadow-indigo-500/15 p-2 flex items-center justify-between gap-1 border border-white/50 ring-1 ring-indigo-950/5">
                    <Link
                        href={route('dashboard')}
                        className={`flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl transition-all duration-200 ${isRouteActive('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 active:bg-slate-50'}`}
                    >
                        <Icons.ChartBar />
                        <span className="text-[10px] font-bold uppercase tracking-wider">Home</span>
                    </Link>

                    <Link
                        href={route('invoices.index')}
                        className={`flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl transition-all duration-200 ${isRouteActive('invoices.index') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 active:bg-slate-50'}`}
                    >
                        <Icons.Document />
                        <span className="text-[10px] font-bold uppercase tracking-wider">Sales</span>
                    </Link>

                    <div className="flex-shrink-0 -mt-8 px-1">
                        <MobileQuickAction permissions={teamPermissions} />
                    </div>

                    <Link
                        href={route('bills.index')}
                        className={`flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl transition-all duration-200 ${isRouteActive('bills.index') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 active:bg-slate-50'}`}
                    >
                        <Icons.ShoppingCart />
                        <span className="text-[10px] font-bold uppercase tracking-wider">Bills</span>
                    </Link>

                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-2xl text-slate-500 active:bg-slate-50 transition-all duration-200"
                    >
                        <Icons.Menu />
                        <span className="text-[10px] font-bold uppercase tracking-wider">Menu</span>
                    </button>
                </nav>
            </div>
        </div>
    );
}