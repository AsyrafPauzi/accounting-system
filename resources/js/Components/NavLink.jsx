import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-terracotta text-ink focus:border-terracotta'
                    : 'border-transparent text-ink-muted hover:border-border-warm hover:text-ink focus:border-border-warm focus:text-ink') +
                className
            }
        >
            {children}
        </Link>
    );
}
