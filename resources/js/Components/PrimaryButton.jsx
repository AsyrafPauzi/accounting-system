export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-xl border border-transparent bg-terracotta px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-terracotta-dark dark:hover:bg-terracotta-light focus:bg-terracotta-dark dark:focus:bg-terracotta-light focus:outline-none focus:ring-2 focus:ring-terracotta focus:ring-offset-2 active:bg-terracotta-dark ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
