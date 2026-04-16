import React, { useState } from 'react';
import { Link } from '@inertiajs/react';

const Icons = {
    Plus: () => <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" /></svg>,
    Invoice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Bill: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>,
    UserPlus: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>,
    X: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>,
};

export default function MobileQuickAction({ permissions = {} }) {
    const [isOpen, setIsOpen] = useState(false);

    const toggle = () => setIsOpen(!isOpen);

    if (!permissions.create && !permissions.edit) return null;

    const actions = [
        { name: 'New Invoice', href: 'invoices.create', Icon: Icons.Invoice, color: 'bg-indigo-600' },
        { name: 'New Bill', href: 'bills.create', Icon: Icons.Bill, color: 'bg-emerald-600' },
        { name: 'New Customer', href: 'customers.create', Icon: Icons.UserPlus, color: 'bg-blue-600' },
    ];

    return (
        <div className="relative">
            {/* Backdrop */}
            {isOpen && (
                <div 
                    className="fixed inset-0 z-40 bg-indigo-950/20 backdrop-blur-[2px] transition-opacity duration-300"
                    onClick={() => setIsOpen(false)}
                />
            )}

            {/* Actions Menu */}
            <div className={`fixed right-6 bottom-24 z-50 flex flex-col items-end gap-3 transition-all duration-300 ${isOpen ? 'opacity-100 translate-y-0 scale-100' : 'opacity-0 translate-y-10 scale-90 pointer-events-none'}`}>
                {actions.map((action) => (
                    <Link
                        key={action.name}
                        href={route(action.href)}
                        className="flex items-center gap-3 group"
                        onClick={() => setIsOpen(false)}
                    >
                        <span className="px-3 py-1.5 rounded-lg bg-white shadow-lg border border-indigo-50 text-xs font-bold text-indigo-950 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                            {action.name}
                        </span>
                        <div className={`w-12 h-12 rounded-2xl ${action.color} text-white flex items-center justify-center shadow-lg shadow-indigo-500/20 transform active:scale-95 transition-transform`}>
                            <action.Icon />
                        </div>
                    </Link>
                ))}
            </div>

            {/* Main Toggle Button */}
            <button
                type="button"
                onClick={toggle}
                className={`relative z-50 w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white flex items-center justify-center shadow-xl shadow-indigo-500/40 transform transition-all duration-300 active:scale-90 ${isOpen ? 'rotate-45' : ''}`}
                aria-label="Quick Actions"
            >
                <Icons.Plus />
            </button>
        </div>
    );
}
