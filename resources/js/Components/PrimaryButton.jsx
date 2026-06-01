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
                `inline-flex items-center justify-center gap-1.5 rounded-xl border border-transparent bg-terracotta px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-terracotta-dark dark:hover:bg-terracotta-light focus:outline-none focus:ring-2 focus:ring-terracotta focus:ring-offset-2 ${
                    disabled && 'opacity-50 cursor-not-allowed'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
