import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-terracotta bg-surface-alt text-terracotta focus:border-terracotta focus:bg-surface-alt focus:text-ink'
                    : 'border-transparent text-ink hover:border-border-warm hover:bg-cream hover:text-ink focus:border-border-warm focus:bg-cream focus:text-ink'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
