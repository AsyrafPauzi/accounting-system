import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';

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
};

const navConfig = [
    { group: 'Main', links: [{ name: 'Dashboard', route: 'dashboard', Icon: Icons.ChartBar }] },
    { group: 'Sales (Revenue)', links: [
        { name: 'Invoices', route: 'invoices.index', Icon: Icons.Document },
        { name: 'Credit Notes', route: 'credit-notes.index', Icon: Icons.ReceiptRefund },
        { name: 'Customers', route: 'customers.index', Icon: Icons.Users },
    ]},
    { group: 'Purchases (Expenses)', links: [
        { name: 'Bills / Purchases', route: 'dashboard', Icon: Icons.ShoppingCart },
        { name: 'Suppliers', route: 'dashboard', Icon: Icons.BuildingOffice },
    ]},
    { group: 'Accounting', links: [
        { name: 'Chart of Accounts', route: 'dashboard', Icon: Icons.Folder },
        { name: 'General Ledger', route: 'dashboard', Icon: Icons.BookOpen },
    ]},
    { group: 'Financial Reports', links: [
        { name: 'Profit & Loss', route: 'dashboard', Icon: Icons.ChartPie },
        { name: 'Balance Sheet', route: 'dashboard', Icon: Icons.Scale },
    ]},
    { group: 'Compliance', links: [
        { name: 'LHDN MyInvois', route: 'dashboard', Icon: Icons.DocumentCheck },
    ]},
];

export default function Authenticated({ user, header, children }) {
    const { flash, auth } = usePage().props;
    const isAdmin = user?.role === 'admin';
    const isImpersonating = Boolean(auth?.impersonator_id);

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

    return (
        <div className="flex h-screen bg-slate-50 overflow-hidden font-sans">
            {/* LEFT COLUMN: PREMIUM SIDEBAR */}
            <aside className="w-72 flex flex-col flex-shrink-0 border-r border-slate-200/80 bg-white shadow-xl shadow-slate-200/50 custom-scrollbar">
                {/* Brand */}
                <div className="p-6 flex items-center gap-3 border-b border-slate-100">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-500/25 ring-2 ring-white">
                        <ApplicationLogo className="block h-6 w-auto fill-current text-white" />
                    </div>
                    <div>
                        <span className="font-bold text-slate-900 tracking-tight text-base">Accounter</span>
                        <span className="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Malaysia Edition</span>
                    </div>
                </div>

                {/* Navigation */}
                <nav className="flex-1 py-5 overflow-y-auto px-3">
                    {navConfig.map((section, idx) => (
                        <div key={idx} className="mb-5">
                            <h3 className="px-3 mb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                {section.group}
                            </h3>
                            <div className="space-y-0.5">
                                {section.links.map((link) => {
                                    const Icon = link.Icon;
                                    const active = isRouteActive(link.route);
                                    return (
                                        <Link
                                            key={link.name}
                                            href={getSafeRoute(link.route)}
                                            className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                                active
                                                    ? 'bg-blue-50 text-blue-700 shadow-sm'
                                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                            }`}
                                        >
                                            <span className={active ? 'text-blue-600' : 'text-slate-400'}>
                                                <Icon />
                                            </span>
                                            <span className="flex-1">{link.name}</span>
                                            {active && (
                                                <span className="w-1.5 h-1.5 rounded-full bg-blue-500" />
                                            )}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}

                    {isAdmin && (
                        <div className="mb-5">
                            <h3 className="px-3 mb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Admin</h3>
                            <Link
                                href={getSafeRoute('admin.tenants.index')}
                                className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                    isRouteActive('admin.tenants.index')
                                        ? 'bg-blue-50 text-blue-700 shadow-sm'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                }`}
                            >
                                <span className="text-slate-400"><Icons.BuildingOffice /></span>
                                <span className="flex-1">Tenant Admin</span>
                                {isRouteActive('admin.tenants.index') && <span className="w-1.5 h-1.5 rounded-full bg-blue-500" />}
                            </Link>
                        </div>
                    )}
                </nav>

                {/* User block */}
                <div className="p-4 border-t border-slate-100 bg-slate-50/80">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-md ring-2 ring-white">
                            {user.name.charAt(0)}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-bold text-slate-800 truncate">{user.name}</p>
                            <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider truncate">
                                {isImpersonating ? 'Impersonating' : (isAdmin ? 'Administrator' : 'User')}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <Link
                            href={route('profile.edit')}
                            className="py-2 rounded-lg text-center text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 transition-colors"
                        >
                            Settings
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="py-2 rounded-lg text-center text-xs font-bold text-rose-600 bg-rose-50 border border-rose-100 hover:bg-rose-100 transition-colors"
                        >
                            Logout
                        </Link>
                    </div>
                </div>
            </aside>

            {/* RIGHT COLUMN: MAIN CONTENT */}
            <div className="flex-1 flex flex-col overflow-hidden relative bg-slate-50">
                {header && (
                    <header className="flex-shrink-0 bg-white border-b border-slate-200/80 shadow-sm z-20">
                        <div className="max-w-full mx-auto py-6 px-8 lg:px-10">
                            {header}
                        </div>
                    </header>
                )}

                <main className="flex-1 overflow-y-auto p-6 lg:p-8 relative">
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

                    {flash?.success && (
                        <div className="max-w-7xl mx-auto mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="max-w-7xl mx-auto mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium">
                            {flash.error}
                        </div>
                    )}
                    <div className="max-w-7xl mx-auto">
                        {children}
                    </div>
                </main>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `
                .custom-scrollbar::-webkit-scrollbar { width: 5px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
            `}} />
        </div>
    );
}