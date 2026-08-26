import React, { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { useTranslation } from '@/i18n';
import { Link, usePage } from '@inertiajs/react';
import MobileQuickAction from '@/Components/MobileQuickAction';
import WelcomeModal from '@/Components/WelcomeModal';
import AccountantCopilot from '@/Components/AccountantCopilot';
import VerifyEmailReminderModal from '@/Components/VerifyEmailReminderModal';
import { shouldShowVerifyReminder } from '@/utils/verifyReminder';

const Icons = {
    ChartBar: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ReceiptRefund: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>,
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    ShoppingCart: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>,
    BuildingOffice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    Folder: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>,
    CreditCard: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>,
    BookOpen: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>,
    ChartPie: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /></svg>,
    Scale: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>,
    DocumentCheck: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Exclamation: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Sparkles: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5zM19 11l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z" /></svg>,
    Download: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" /></svg>,
    Trash: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" /></svg>,
    Shield: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>,
    Menu: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" /></svg>,
    X: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>,
    ChevronDown: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>,
    ChevronLeft: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    ChevronRight: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>,
    Audit: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Scan: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2M20 7V5a1 1 0 00-1-1h-2M20 17v2a1 1 0 01-1 1h-2M4 12h16" /></svg>,
    ClipboardList: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>,
    ArrowPath: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>,
    DocumentChart: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Tag: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>,
};

const navConfig = [
    { group: 'Main', groupKey: 'navigation.groups.main', links: [{ name: 'Dashboard', nameKey: 'navigation.links.dashboard', route: 'dashboard', Icon: Icons.ChartBar, subtitle: 'Overview of sales, bills, and cash' }] },
    { group: 'Sales (Revenue)', groupKey: 'navigation.groups.sales', subgroups: [
        { name: 'Quotes & orders', links: [
            { name: 'Estimates', route: 'estimates.index', Icon: Icons.ClipboardList, planPermission: 'estimates.view', userPermission: 'estimates.view', subtitle: 'Quotations sent to customers' },
            { name: 'Sales Orders', route: 'sales-orders.index', Icon: Icons.ClipboardList, planPermission: 'estimates.view', userPermission: 'sales-orders.view', requiresGoodsFlow: true, subtitle: 'Confirmed orders before delivery' },
            { name: 'Delivery Orders', route: 'delivery-orders.index', Icon: Icons.ClipboardList, planPermission: 'estimates.view', userPermission: 'delivery-orders.view', requiresGoodsFlow: true, subtitle: 'Goods dispatched to customers' },
        ]},
        { name: 'Billing', links: [
            { name: 'Invoices', nameKey: 'navigation.links.invoices', route: 'invoices.index', Icon: Icons.Document, planPermission: 'invoices.view', userPermission: 'invoices.view', subtitle: 'Bills sent to customers' },
            { name: 'Recurring Invoices', route: 'recurring-invoices.index', Icon: Icons.ArrowPath, planPermission: 'recurring-invoices.view', userPermission: 'recurring-invoices.view', subtitle: 'Auto-generate, post, and email on a schedule' },
            { name: 'Credit Notes', nameKey: 'navigation.links.credit_notes', route: 'credit-notes.index', Icon: Icons.ReceiptRefund, planPermission: 'credit-notes.view', userPermission: 'credit-notes.view', subtitle: 'Refunds and reductions for customers' },
            { name: 'Debit Notes', route: 'debit-notes.index', Icon: Icons.Document, planPermission: 'credit-notes.view', userPermission: 'debit-notes.view', subtitle: 'Additional charges to customers' },
        ]},
        { name: 'Collections', links: [
            { name: 'Customer Deposits', route: 'ar-deposits.index', Icon: Icons.CreditCard, planPermission: 'invoices.record-payment', userPermission: 'invoices.record-payment', subtitle: 'Receipts and knock-off across invoices' },
            { name: 'Customer Statements', route: 'customer-statements.index', Icon: Icons.DocumentChart, planPermission: 'customer-statements.view', userPermission: 'customers.view', subtitle: 'Balance forward report per customer' },
        ]},
        { name: 'Masters', links: [
            { name: 'Customers', nameKey: 'navigation.links.customers', route: 'customers.index', Icon: Icons.Users, planPermission: 'customers.view', userPermission: 'customers.view', subtitle: 'People and companies you bill' },
            { name: 'Products & Services', route: 'products.index', Icon: Icons.Tag, planPermission: 'products.view', userPermission: 'products.view', subtitle: 'Reusable invoice line items' },
        ]},
    ]},
    { group: 'Purchases (Expenses)', groupKey: 'navigation.groups.purchases', subgroups: [
        { name: 'Ordering', links: [
            { name: 'Purchase Orders', route: 'purchase-orders.index', Icon: Icons.ClipboardList, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Orders placed with suppliers' },
            { name: 'Goods Receipts', route: 'goods-receipts.index', Icon: Icons.ClipboardList, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Stock received from suppliers' },
        ]},
        { name: 'Bills', links: [
            { name: 'Receipt inbox', route: 'receipts.index', Icon: Icons.Document, planPermission: 'ocr.use', userPermission: 'ocr.use', subtitle: 'Upload receipts and create bills from OCR' },
            { name: 'Bills / Purchases', nameKey: 'navigation.links.bills', route: 'bills.index', Icon: Icons.ShoppingCart, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Supplier invoices to pay' },
            { name: 'Recurring Bills', route: 'recurring-bills.index', Icon: Icons.ArrowPath, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Auto-generate supplier bills on a schedule' },
            { name: 'Supplier Credit Notes', route: 'supplier-credit-notes.index', Icon: Icons.ReceiptRefund, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Credits from suppliers' },
            { name: 'Supplier Debit Notes', route: 'supplier-debit-notes.index', Icon: Icons.Document, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Additional charges from suppliers' },
        ]},
        { name: 'Payments', links: [
            { name: 'Supplier Deposits', route: 'ap-deposits.index', Icon: Icons.CreditCard, planPermission: 'bills.record-payment', userPermission: 'bills.record-payment', subtitle: 'Prepaid and knock-off across bills' },
            { name: 'Supplier Statements', route: 'supplier-statements.index', Icon: Icons.DocumentChart, planPermission: 'bills.view', userPermission: 'bills.view', subtitle: 'Balance forward report per supplier' },
            { name: 'Accounts Payable', route: 'accounts-payable.index', Icon: Icons.Document, planPermission: 'reports.aged-reports', userPermission: 'reports.aged-reports', subtitle: 'Outstanding and aging' },
        ]},
        { name: 'Masters', links: [
            { name: 'Suppliers', nameKey: 'navigation.links.suppliers', route: 'suppliers.index', Icon: Icons.BuildingOffice, planPermission: 'suppliers.view', userPermission: 'suppliers.view', subtitle: 'Companies you buy from' },
        ]},
    ]},
    { group: 'Accounting', groupKey: 'navigation.groups.accounting', links: [
        { name: 'Transactions', route: 'transactions.index', Icon: Icons.CreditCard, planPermission: 'journal.view', userPermission: 'journal.view', subtitle: 'Bank & cash movements feed', activeRoutes: ['transactions.index', 'transactions.deposit.create', 'transactions.withdrawal.create'] },
        { name: 'Bank reconciliation', route: 'bank-rec.index', Icon: Icons.DocumentCheck, planPermission: 'bank-rec.view', userPermission: 'bank-rec.view', subtitle: 'Import statements & match transactions', activeRoutes: ['bank-rec.index', 'bank-rec.import', 'bank-rec.match'] },
        { name: 'Chart of Accounts', route: 'chart-of-accounts.index', Icon: Icons.Folder, planPermission: 'accounts.view', userPermission: 'accounts.view', subtitle: 'Accounts used in postings and reports' },
        { name: 'General Ledger', route: 'general-ledger.index', Icon: Icons.BookOpen, planPermission: 'general-ledger.view', userPermission: 'general-ledger.view', subtitle: 'By journal entry' },
        { name: 'Manual Journal Entry', route: 'journal.index', Icon: Icons.Scale, planPermission: 'journal.create', userPermission: 'journal.view', subtitle: 'Post custom journal entries' },
        { name: 'Payroll', route: 'payroll.create', Icon: Icons.Users, planPermission: 'payroll.run', userPermission: 'journal.create', subtitle: 'Record monthly salaries & statutory' },
        { name: 'Trial Balance', route: 'trial-balance.index', Icon: Icons.Scale, planPermission: 'general-ledger.view', userPermission: 'general-ledger.view', subtitle: 'Verify account balances' },
    ]},
    { group: 'Reports', groupKey: 'navigation.groups.reports', links: [
        { name: 'Reports', nameKey: 'navigation.links.reports', route: 'reports.index', Icon: Icons.ChartPie, planPermission: 'reports.view', userPermission: 'reports.view', subtitle: 'Financial statements & analysis', activeRoutes: ['reports.index', 'general-ledger.index', 'general-ledger.report', 'trial-balance.index', 'profit-and-loss.index', 'reports.sales.index', 'balance-sheet.index', 'cashflow-summary.index', 'aged-receivables.index', 'accounts-payable.index', 'reports.sales-tax.index', 'reports.payroll-remittance', 'reports.income-by-customer.index', 'reports.customer-credits.index', 'reports.purchases-by-vendor.index'] },
    ]},
];

const subgroupKey = (group, subgroup) => `${group}::${subgroup}`;

const sectionAllLinks = (section) =>
    section.subgroups
        ? section.subgroups.flatMap((sg) => sg.links)
        : (section.links ?? []);

const linkIsActive = (link, isRouteActive) =>
    link.activeRoutes
        ? link.activeRoutes.some((r) => isRouteActive(r))
        : isRouteActive(link.route);

// Shared classNames for nav links — terracotta active state, ink-muted resting
const linkClasses = (active, disabled, rail = false) =>
    `flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors duration-150 ${
        rail ? 'lg:justify-center lg:px-2' : ''
    } ${
        active
            ? 'bg-terracotta text-white shadow-sm'
            : disabled
                ? 'text-ink-muted/50 hover:text-ink-muted'
                : 'text-ink hover:bg-surface-alt hover:text-ink'
    }`;

const navLabelClass = (rail) => `flex-1 min-w-0 ${rail ? 'lg:hidden' : ''}`;

const iconWrapClasses = (active) => (active ? 'text-white' : 'text-terracotta/80');

export default function Authenticated({ user: propUser, header, children }) {
    const page = usePage();
    const { t } = useTranslation();
    const linkName = (link) => (link.nameKey ? t(link.nameKey) : link.name);
    const sectionName = (section) => (section.groupKey ? t(section.groupKey) : section.group);
    const { flash, auth, company_flags } = page.props;
    const url = page.url;
    const user = propUser || auth?.user || {};
    const hasActiveSubscription = auth?.hasActiveSubscription ?? false;
    const teamPermissions = auth?.teamPermissions ?? { view: false, create: false, edit: false, delete: false };
    const isAdmin = user?.role_name === 'super-admin';
    const isImpersonating = Boolean(auth?.impersonator_id);
    const practice = page.props.practice;
    const isFirmActingOnClient = Boolean(practice?.is_inside_client);
    // Deployment mode is shared via Inertia's `share()`; default to
    // 'saas' so missing prop = SaaS behaviour (least surprising).
    const deploymentMode = page.props.deployment_mode ?? 'saas';
    const isSelfHosted = deploymentMode === 'self_hosted';
    const planPermissions = auth?.planPermissions ?? {};
    const permissions = auth?.permissions ?? [];

    const hasPermission = (p) => permissions.includes(p) || isAdmin;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }
        try {
            return window.localStorage.getItem('bukucloud.sidebarCollapsed') === '1';
        } catch {
            return false;
        }
    });
    const [openGroups, setOpenGroups] = useState({});
    const [railTip, setRailTip] = useState(null);

    const persistSidebarCollapsed = (next) => {
        setSidebarCollapsed(next);
        setRailTip(null);
        try {
            window.localStorage.setItem('bukucloud.sidebarCollapsed', next ? '1' : '0');
        } catch {
            /* ignore quota / private mode */
        }
    };

    const showRailTip = (label, event, subtitle = '') => {
        if (!sidebarCollapsed) {
            return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        setRailTip({
            label,
            subtitle: subtitle || '',
            top: rect.top + rect.height / 2,
            left: rect.right + 10,
        });
    };

    const hideRailTip = () => setRailTip(null);

    const railProps = (label, subtitle = '') => {
        const hint = subtitle ? `${label} — ${subtitle}` : label;
        return {
            'aria-label': hint,
            title: hint,
            onMouseEnter: (e) => showRailTip(label, e, subtitle),
            onMouseLeave: hideRailTip,
            onFocus: (e) => showRailTip(label, e, subtitle),
            onBlur: hideRailTip,
        };
    };

    // Post-signup welcome tour. Shows on the first authenticated page
    // load after a freshly-registered user verifies their email — i.e.
    // when `email_verified_at` is set but `welcomed_at` is still null.
    // Suppress while impersonating: the welcome message would say "Hi
    // <impersonated user>" which is the wrong UX for an admin debugging.
    // Super-admins also skip it — the copy is SME onboarding, not the
    // platform console.
    const [welcomeOpen, setWelcomeOpen] = useState(
        Boolean(user?.email_verified_at) && !user?.welcomed_at && !isImpersonating && !isAdmin
    );

    // Verify-email reminder. Mutually exclusive with the welcome modal:
    // welcome only shows AFTER verification, this only shows BEFORE.
    // Cadence (>=2 days since last skip) is computed in the shared util
    // so the same gate is reusable in PracticeLayout.
    const [verifyReminderOpen, setVerifyReminderOpen] = useState(
        shouldShowVerifyReminder(user, isImpersonating)
    );

    const isRouteActive = (routeName) => {
        try {
            return route().current(routeName);
        } catch (e) {
            return false;
        }
    };

    const getSafeRoute = (routeName) => {
        try {
            return route(routeName);
        } catch (e) {
            return '#';
        }
    };

    const toggleGroup = (groupName) => {
        setOpenGroups((prev) => {
            const isSubgroup = groupName.includes('::');
            // Subgroups default open (undefined → open); top-level groups default closed.
            const currentlyOpen = isSubgroup
                ? prev[groupName] !== false
                : Boolean(prev[groupName]);
            return {
                ...prev,
                [groupName]: !currentlyOpen,
            };
        });
    };

    useEffect(() => {
        const initialOpenGroups = {};
        navConfig.forEach((section) => {
            const hasActive = sectionAllLinks(section).some((link) => linkIsActive(link, isRouteActive));
            if (hasActive) {
                initialOpenGroups[section.group] = true;
            }
            (section.subgroups ?? []).forEach((sg) => {
                if (sg.links.some((link) => linkIsActive(link, isRouteActive))) {
                    initialOpenGroups[subgroupKey(section.group, sg.name)] = true;
                }
            });
        });

        if (
            isRouteActive('admin.tenants.index') ||
            isRouteActive('admin.plans.index') ||
            isRouteActive('admin.self-hosted.index') ||
            isRouteActive('admin.platform.show') ||
            isRouteActive('admin.users.index') ||
            isRouteActive('admin.audit-logs.index') ||
            isRouteActive('admin.branding.edit') ||
            isRouteActive('admin.ocr.edit')
        ) initialOpenGroups['Admin'] = true;
        if (
            isRouteActive('settings.company') ||
            isRouteActive('settings.team.index') ||
            isRouteActive('audit-logs.index') ||
            isRouteActive('settings.plan.index') ||
            isRouteActive('settings.integrations.index') ||
            isRouteActive('audit.index') ||
            url.startsWith('/audit')
        ) {
            initialOpenGroups['Company'] = true;
        }

        setOpenGroups(initialOpenGroups);
    }, [url]);

    useEffect(() => {
        setSidebarOpen(false);
        setRailTip(null);
    }, [url]);

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

            <aside
                className={`fixed inset-y-0 left-0 z-50 w-72 flex flex-col border-r border-border-warm bg-surface custom-scrollbar transform transition-[width,transform] duration-200 ease-out lg:relative lg:z-auto lg:translate-x-0 lg:flex-shrink-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} ${sidebarCollapsed ? 'lg:w-[4.75rem]' : 'lg:w-72'}`}
            >
                <div className={`p-4 sm:p-6 flex items-center justify-between gap-3 border-b border-border-warm bg-surface ${sidebarCollapsed ? 'lg:flex-col lg:p-3 lg:gap-2' : ''}`}>
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-cream">
                            <ApplicationLogo className="block h-9 w-9" />
                        </div>
                        <div className={`min-w-0 ${sidebarCollapsed ? 'lg:hidden' : ''}`}>
                            <span className="font-display font-medium text-ink tracking-tight text-base block truncate">{page.props.product_name}</span>
                            {String(page.props.product_tagline ?? '').trim() !== '' && (
                                <span className="block text-eyebrow text-ink-muted uppercase truncate">{page.props.product_tagline}</span>
                            )}
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => persistSidebarCollapsed(!sidebarCollapsed)}
                        className="hidden lg:flex p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-colors"
                        aria-label={sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation'}
                        {...railProps(
                            sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation',
                            sidebarCollapsed ? 'Show names next to icons' : 'Icons only',
                        )}
                    >
                        {sidebarCollapsed ? <Icons.ChevronRight /> : <Icons.ChevronLeft />}
                    </button>
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(false)}
                        className="lg:hidden p-2 -m-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-colors"
                        aria-label="Close menu"
                    >
                        <Icons.X />
                    </button>
                </div>

                <nav className={`flex-1 py-5 overflow-y-auto bg-surface ${sidebarCollapsed ? 'px-3 lg:px-1.5' : 'px-3'}`}>
                    {/* SME plan nag — only for tenant users on free, never for
                        firm users (they have their own practice plan badge
                        below) and never for super-admins or self-hosted. */}
                    {!hasActiveSubscription && !isAdmin && !isSelfHosted && !practice && (
                        <div className={`mb-4 mx-1 px-3 py-2 rounded-xl bg-mustard/15 border border-mustard/40 text-ink text-[11px] font-medium flex items-center justify-between ${sidebarCollapsed ? 'lg:hidden' : ''}`}>
                            <span>You&apos;re on the Free tier.</span>
                            <Link
                                href={getSafeRoute('subscription.index')}
                                className="ml-2 text-eyebrow font-semibold uppercase text-terracotta hover:text-terracotta-dark dark:hover:text-terracotta-light underline-offset-2 hover:underline"
                            >
                                Upgrade
                            </Link>
                        </div>
                    )}

                    {/* Firm-side plan badge. Shows the firm's *own* practice
                        plan (Practice Free / Starter / Growth / Firm), not the
                        tenant's. The Upgrade link lands on /practice/plan, the
                        firm-specific billing page. */}
                    {practice?.subscription && !isAdmin && (
                        <div className={`mb-4 mx-1 px-3 py-2 rounded-xl text-[11px] font-medium flex items-center justify-between ${sidebarCollapsed ? 'lg:hidden' : ''} ${
                            practice.subscription.is_free
                                ? 'bg-mustard/15 border border-mustard/40 text-ink'
                                : 'bg-forest/10 border border-forest/30 text-ink'
                        }`}>
                            <span>
                                {practice.subscription.is_free ? "You're on " : 'Plan: '}
                                <strong>{practice.subscription.plan_name}</strong>
                            </span>
                            {/* Upgrade is a SaaS-only flow — self-hosted
                                Enterprise customers expand caps by getting
                                a re-issued license, not via /practice/plan. */}
                            {practice.subscription.is_free && !isSelfHosted && (
                                <Link
                                    href={getSafeRoute('practice.plan')}
                                    className="ml-2 text-eyebrow font-semibold uppercase text-terracotta hover:text-terracotta-dark dark:hover:text-terracotta-light underline-offset-2 hover:underline"
                                >
                                    Upgrade
                                </Link>
                            )}
                        </div>
                    )}
                    {navConfig.map((section, idx) => {
                        if (isAdmin && section.group !== 'Admin') return null;

                        const linkVisible = (link) => {
                            const planOk = !link.planPermission || planPermissions[link.planPermission];
                            const userOk = !link.userPermission || hasPermission(link.userPermission);
                            const goodsOk = !link.requiresGoodsFlow || company_flags?.show_goods_flow !== false;
                            return planOk && userOk && goodsOk;
                        };

                        const renderNavLink = (link) => {
                            const Icon = link.Icon;
                            const active = linkIsActive(link, isRouteActive);
                            const isPaidOnly = link.requirePaid;
                            const disabled = isPaidOnly && !hasActiveSubscription;
                            return (
                                <Link
                                    key={link.name}
                                    href={disabled ? route('subscription.index') : getSafeRoute(link.route)}
                                    className={linkClasses(active, disabled, sidebarCollapsed)}
                                    {...railProps(link.name, link.subtitle)}
                                >
                                    <span className={iconWrapClasses(active)}>
                                        <Icon />
                                    </span>
                                    <span className={navLabelClass(sidebarCollapsed)}>
                                        <span className="block truncate">{linkName(link)}</span>
                                        {link.subtitle && (
                                            <span className={`block text-[10px] font-normal mt-0.5 truncate ${active ? 'text-white/80' : 'text-ink-muted'}`}>
                                                {link.subtitle}
                                            </span>
                                        )}
                                    </span>
                                    {active && (
                                        <span className={`w-1.5 h-1.5 rounded-full bg-white/90 flex-shrink-0 ${sidebarCollapsed ? 'lg:hidden' : ''}`} />
                                    )}
                                </Link>
                            );
                        };

                        if (section.subgroups) {
                            const visibleSubgroups = section.subgroups
                                .map((sg) => ({ ...sg, links: sg.links.filter(linkVisible) }))
                                .filter((sg) => sg.links.length > 0);

                            if (visibleSubgroups.length === 0) return null;

                            const isOpen = Boolean(openGroups[section.group]) || sidebarCollapsed;

                            return (
                                <div key={idx} className="mb-2">
                                    <button
                                        type="button"
                                        onClick={() => toggleGroup(section.group)}
                                        className={`w-full flex items-center justify-between px-3 py-2 text-eyebrow font-semibold text-ink-muted uppercase hover:bg-surface-alt rounded-lg transition-colors ${sidebarCollapsed ? 'lg:hidden' : ''}`}
                                    >
                                        <span>{sectionName(section)}</span>
                                        <span className={`transition-transform duration-200 ${isOpen ? 'rotate-0' : '-rotate-90 text-ink-muted/60'}`}>
                                            <Icons.ChevronDown />
                                        </span>
                                    </button>

                                    <div className={`mt-1 space-y-1 overflow-hidden transition-all duration-300 ${isOpen ? 'max-h-[3000px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                        {visibleSubgroups.map((sg) => {
                                            const sgKey = subgroupKey(section.group, sg.name);
                                            // Default open; only collapse when user toggles off.
                                            const sgOpen = sidebarCollapsed || openGroups[sgKey] !== false;

                                            if (sidebarCollapsed) {
                                                return (
                                                    <React.Fragment key={sgKey}>
                                                        <div className="space-y-0.5 hidden lg:block">
                                                            {sg.links.map(renderNavLink)}
                                                        </div>
                                                        <div className="mb-0.5 lg:hidden">
                                                            <button
                                                                type="button"
                                                                onClick={() => toggleGroup(sgKey)}
                                                                className="w-full flex items-center justify-between px-3 py-1.5 text-[11px] font-semibold text-ink-muted/80 hover:text-ink hover:bg-surface-alt rounded-lg transition-colors"
                                                            >
                                                                <span>{sg.name}</span>
                                                                <span className={`transition-transform duration-200 ${openGroups[sgKey] !== false ? 'rotate-0' : '-rotate-90 text-ink-muted/50'}`}>
                                                                    <Icons.ChevronDown />
                                                                </span>
                                                            </button>
                                                            <div className={`mt-0.5 space-y-0.5 pl-1 overflow-hidden transition-all duration-300 ${openGroups[sgKey] !== false ? 'max-h-[1200px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                                                {sg.links.map(renderNavLink)}
                                                            </div>
                                                        </div>
                                                    </React.Fragment>
                                                );
                                            }

                                            return (
                                                <div key={sgKey} className="mb-0.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleGroup(sgKey)}
                                                        className="w-full flex items-center justify-between px-3 py-1.5 text-[11px] font-semibold text-ink-muted/80 hover:text-ink hover:bg-surface-alt rounded-lg transition-colors"
                                                    >
                                                        <span>{sg.name}</span>
                                                        <span className={`transition-transform duration-200 ${sgOpen ? 'rotate-0' : '-rotate-90 text-ink-muted/50'}`}>
                                                            <Icons.ChevronDown />
                                                        </span>
                                                    </button>
                                                    <div className={`mt-0.5 space-y-0.5 pl-1 overflow-hidden transition-all duration-300 ${sgOpen ? 'max-h-[1200px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                                        {sg.links.map(renderNavLink)}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        }

                        const visibleLinks = (section.links ?? []).filter(linkVisible);

                        if (visibleLinks.length === 0) return null;
                        const isOpen = Boolean(openGroups[section.group]) || sidebarCollapsed;

                        return (
                            <div key={idx} className="mb-2">
                                <button
                                    type="button"
                                    onClick={() => toggleGroup(section.group)}
                                    className={`w-full flex items-center justify-between px-3 py-2 text-eyebrow font-semibold text-ink-muted uppercase hover:bg-surface-alt rounded-lg transition-colors ${sidebarCollapsed ? 'lg:hidden' : ''}`}
                                >
                                    <span>{sectionName(section)}</span>
                                    <span className={`transition-transform duration-200 ${isOpen ? 'rotate-0' : '-rotate-90 text-ink-muted/60'}`}>
                                        <Icons.ChevronDown />
                                    </span>
                                </button>

                                <div className={`mt-1 space-y-0.5 overflow-hidden transition-all duration-300 ${isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                    {visibleLinks.map(renderNavLink)}
                                </div>
                            </div>
                        );
                    })}

                    {isAdmin && (
                        <div className="mb-2">
                            <button
                                onClick={() => toggleGroup('Admin')}
                                className={`w-full flex items-center justify-between px-3 py-2 text-eyebrow font-semibold text-ink-muted uppercase hover:bg-surface-alt rounded-lg transition-colors ${sidebarCollapsed ? 'lg:hidden' : ''}`}
                            >
                                <span>Admin</span>
                                <span className={`transition-transform duration-200 ${openGroups['Admin'] || sidebarCollapsed ? 'rotate-0' : '-rotate-90 text-ink-muted/60'}`}>
                                    <Icons.ChevronDown />
                                </span>
                            </button>
                            <div className={`mt-1 space-y-0.5 overflow-hidden transition-all duration-300 ${openGroups['Admin'] || sidebarCollapsed ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                {[
                                    { name: 'Tenants', route: 'admin.tenants.index', Icon: Icons.BuildingOffice, subtitle: 'All company accounts on the platform' },
                                    { name: 'Plan Catalog', route: 'admin.plans.index', Icon: Icons.Sparkles, subtitle: 'Subscription plans and features' },
                                    { name: 'Self-hosted Installs', route: 'admin.self-hosted.index', Icon: Icons.BuildingOffice, saasOnly: true, subtitle: 'Licensed on-prem installs' },
                                    { name: 'Patch Broadcaster', route: 'admin.platform.show', Icon: Icons.ArrowPath, saasOnly: true, subtitle: 'Push updates to installs' },
                                    { name: 'Platform Users', route: 'admin.users.index', Icon: Icons.Users, subtitle: 'Super-admin accounts' },
                                    { name: 'Audit Log', route: 'admin.audit-logs.index', Icon: Icons.Audit, subtitle: 'Platform activity history' },
                                    { name: 'Receipt OCR', route: 'admin.ocr.edit', Icon: Icons.Scan, subtitle: 'Scan settings for receipts' },
                                    { name: 'Branding', route: 'admin.branding.edit', Icon: Icons.Sparkles, subtitle: 'Logo, colours, and product name' },
                                ].filter((link) => !link.saasOnly || !isSelfHosted).map((link) => {
                                    const active = isRouteActive(link.route);
                                    return (
                                        <Link
                                            key={link.name}
                                            href={getSafeRoute(link.route)}
                                            className={linkClasses(active, false, sidebarCollapsed)}
                                            {...railProps(link.name, link.subtitle)}
                                        >
                                            <span className={iconWrapClasses(active)}>
                                                <link.Icon />
                                            </span>
                                            <span className={navLabelClass(sidebarCollapsed)}>
                                                <span className="block truncate">{linkName(link)}</span>
                                                {link.subtitle && (
                                                    <span className={`block text-[10px] font-normal mt-0.5 truncate ${active ? 'text-white/80' : 'text-ink-muted'}`}>
                                                        {link.subtitle}
                                                    </span>
                                                )}
                                            </span>
                                            {active && <span className={`w-1.5 h-1.5 rounded-full bg-white/90 ${sidebarCollapsed ? 'lg:hidden' : ''}`} />}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {!isAdmin && (
                        <div className="mb-2">
                            <button
                                onClick={() => toggleGroup('Company')}
                                className={`w-full flex items-center justify-between px-3 py-2 text-eyebrow font-semibold text-ink-muted uppercase hover:bg-surface-alt rounded-lg transition-colors ${sidebarCollapsed ? 'lg:hidden' : ''}`}
                            >
                                <span>Company</span>
                                <span className={`transition-transform duration-200 ${openGroups['Company'] || sidebarCollapsed ? 'rotate-0' : '-rotate-90 text-ink-muted/60'}`}>
                                    <Icons.ChevronDown />
                                </span>
                            </button>
                            <div className={`mt-1 space-y-0.5 overflow-hidden transition-all duration-300 ${openGroups['Company'] || sidebarCollapsed ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                <Link
                                    href={getSafeRoute('settings.company')}
                                    className={linkClasses(isRouteActive('settings.company'), false, sidebarCollapsed)}
                                    {...railProps('Company settings', 'Legal name, address, and tax IDs')}
                                >
                                    <span className={iconWrapClasses(isRouteActive('settings.company'))}>
                                        <Icons.BuildingOffice />
                                    </span>
                                    <span className={navLabelClass(sidebarCollapsed)}>Company settings</span>
                                </Link>
                                {/* Tenant admins can hand the books to a firm here. Hidden when
                                    the user is themselves a firm/accountant user — they're the
                                    party who would receive such an invite, not send one. */}
                                {!practice && (
                                    <Link
                                        href={getSafeRoute('settings.invite-firm.show')}
                                        className={linkClasses(isRouteActive('settings.invite-firm.show'), false, sidebarCollapsed)}
                                        {...railProps('Invite my accountant', 'Give a firm access to the books')}
                                    >
                                        <span className={iconWrapClasses(isRouteActive('settings.invite-firm.show'))}>
                                            <Icons.Users />
                                        </span>
                                        <span className={navLabelClass(sidebarCollapsed)}>Invite my accountant</span>
                                    </Link>
                                )}
                                {hasPermission('audit.view') && planPermissions['audit-logs.view'] && (
                                    <Link
                                        href={getSafeRoute('audit.index')}
                                        className={linkClasses(isRouteActive('audit.index'), false, sidebarCollapsed)}
                                        {...railProps('Audit Compliance', 'Year-end audit checklist')}
                                    >
                                        <span className={iconWrapClasses(isRouteActive('audit.index'))}>
                                            <Icons.Audit />
                                        </span>
                                        <span className={navLabelClass(sidebarCollapsed)}>Audit Compliance</span>
                                    </Link>
                                )}
                                {hasPermission('users.view') && planPermissions['users.view'] && (
                                    <Link
                                        href={getSafeRoute('settings.team.index')}
                                        className={linkClasses(isRouteActive('settings.team.index'), false, sidebarCollapsed)}
                                        {...railProps('Team & Roles', 'Who can sign in and what they can do')}
                                    >
                                        <span className={iconWrapClasses(isRouteActive('settings.team.index'))}>
                                            <Icons.Users />
                                        </span>
                                        <span className={navLabelClass(sidebarCollapsed)}>Team & Roles</span>
                                    </Link>
                                )}
                                {hasPermission('audit-logs.view') && planPermissions['audit-logs.view'] && (
                                    <Link
                                        href={getSafeRoute('audit-logs.index')}
                                        className={linkClasses(isRouteActive('audit-logs.index'), false, sidebarCollapsed)}
                                        {...railProps('Audit Logs', 'Who changed what, and when')}
                                    >
                                        <span className={iconWrapClasses(isRouteActive('audit-logs.index'))}>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </span>
                                        <span className={navLabelClass(sidebarCollapsed)}>Audit Logs</span>
                                    </Link>
                                )}
                                {/* API & Integrations — visible to admins on Solo+ tenants
                                    (Solo is the lowest tier with `api.access`). Hidden on
                                    Startup/Free tenants and from non-admin team members. */}
                                {hasPermission('integrations.view') && planPermissions['api.access'] && (
                                    <Link
                                        href={getSafeRoute('settings.integrations.index')}
                                        className={linkClasses(isRouteActive('settings.integrations.index'), false, sidebarCollapsed)}
                                        {...railProps('API & Integrations', 'Connect other apps')}
                                    >
                                        <span className={iconWrapClasses(isRouteActive('settings.integrations.index'))}>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </span>
                                        <span className={navLabelClass(sidebarCollapsed)}>API & Integrations</span>
                                    </Link>
                                )}
                                {/* Plan & Usage:
                                      - SaaS: tenant admins only (subscription dashboard).
                                      - Self-hosted: also surface to firm-owners — on
                                        Enterprise installs the firm-owner is effectively
                                        the operator, and they need to see license
                                        expiry / heartbeat / renewal contact. */}
                                {(user.role_name === 'admin' || user.role_name === 'super-admin' || (isSelfHosted && user.role_name === 'firm-owner')) && (
                                    <Link
                                        href={getSafeRoute('settings.plan.index')}
                                        className={linkClasses(isRouteActive('settings.plan.index'), false, sidebarCollapsed)}
                                        {...railProps(isSelfHosted ? 'License & Usage' : 'Plan & Usage', isSelfHosted ? 'License expiry and usage' : 'Subscription and usage limits')}
                                    >
                                        <span className={iconWrapClasses(isRouteActive('settings.plan.index'))}>
                                            <Icons.Sparkles />
                                        </span>
                                        <span className={navLabelClass(sidebarCollapsed)}>{isSelfHosted ? 'License & Usage' : 'Plan & Usage'}</span>
                                    </Link>
                                )}
                                {/* Two-factor auth, Download my data, and Delete account
                                    used to live here as separate sidebar items. They've moved
                                    to the user's account page (/profile → Security & data
                                    section) so the Company group stays focused on company-wide
                                    things (settings, audit, team, plan) and account-level
                                    controls live with the user's own profile. */}
                            </div>
                        </div>
                    )}
                </nav>

                <div className={`p-4 pb-15 lg:pb-4 border-t border-border-warm bg-surface ${sidebarCollapsed ? 'lg:px-2' : ''}`}>
                    <div className={`flex items-center gap-3 mb-3 ${sidebarCollapsed ? 'lg:justify-center lg:mb-2' : ''}`}>
                        <div
                            className="h-10 w-10 rounded-xl bg-terracotta flex items-center justify-center text-white text-sm font-semibold"
                            {...railProps(user.name || 'User', isImpersonating ? 'Impersonating' : (user.role_name?.replace('-', ' ') || 'Signed in'))}
                        >
                            {(user.name || 'U').charAt(0)}
                        </div>
                        <div className={`flex-1 min-w-0 ${sidebarCollapsed ? 'lg:hidden' : ''}`}>
                            <p className="text-sm font-medium text-ink truncate">{user.name || 'User'}</p>
                            <p className="text-eyebrow font-semibold text-ink-muted uppercase truncate">
                                {isImpersonating ? 'Impersonating' : (user.role_name?.replace('-', ' ') || 'User')}
                            </p>
                        </div>
                    </div>
                    <div className={`grid gap-2 ${sidebarCollapsed ? 'grid-cols-2 lg:grid-cols-1' : 'grid-cols-2'}`}>
                        <Link
                            href={route('profile.edit')}
                            className="py-2 rounded-lg text-center text-xs font-semibold text-ink bg-cream border border-border-warm hover:bg-surface-alt transition-colors"
                            {...railProps('Settings', 'Your profile and password')}
                        >
                            <span className={sidebarCollapsed ? 'lg:hidden' : ''}>Settings</span>
                            <span className={`hidden ${sidebarCollapsed ? 'lg:inline' : ''}`} aria-hidden="true">
                                <svg className="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </span>
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="py-2 rounded-lg text-center text-xs font-semibold text-terracotta bg-cream border border-border-warm hover:bg-terracotta/10 transition-colors"
                            {...railProps('Logout', 'Sign out of this account')}
                        >
                            <span className={sidebarCollapsed ? 'lg:hidden' : ''}>Logout</span>
                            <span className={`hidden ${sidebarCollapsed ? 'lg:inline' : ''}`} aria-hidden="true">
                                <svg className="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            </span>
                        </Link>
                    </div>
                </div>
            </aside>

            {railTip && (
                <div
                    role="tooltip"
                    className="hidden lg:block fixed z-[80] pointer-events-none -translate-y-1/2 rounded-lg bg-ink text-cream px-2.5 py-1.5 shadow-lg max-w-[16rem]"
                    style={{ top: railTip.top, left: railTip.left }}
                >
                    <div className="relative">
                        <span className="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-ink" />
                        <div className="text-xs font-semibold leading-tight">{railTip.label}</div>
                        {railTip.subtitle ? (
                            <div className="text-[10px] font-normal text-cream/70 mt-0.5 leading-snug">{railTip.subtitle}</div>
                        ) : null}
                    </div>
                </div>
            )}

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
                            <span>
                                You&apos;re impersonating another user. Actions affect that tenant only.
                            </span>
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
                                Working in <strong>{practice.acting_client?.name}</strong>{' '}
                                — every change is logged against {practice.firm?.name}.
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

                    {/* Self-hosted update banner. Only renders when the
                        publisher (BukuCloud) has advertised a different
                        version than what this install is running. The
                        banner is informational — the actual upgrade
                        happens via `docker compose pull` outside the app. */}
                    {page.props.self_hosted_update && (
                        <div className="max-w-[90rem] mx-auto mb-4 px-4 py-3 rounded-xl bg-mustard/15 border border-mustard/40 text-ink text-sm font-medium flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <span>
                                <span className="text-eyebrow uppercase font-semibold text-mustard-dark dark:text-mustard mr-2">Update available</span>
                                BukuCloud <strong>{page.props.self_hosted_update.available_version}</strong> is now released.
                                You're running <strong>{page.props.self_hosted_update.current_version}</strong>.
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
