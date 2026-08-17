import React from 'react';

const MagnifyingGlass = () => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
);

export default function IndexFilterBar({
    search = '',
    onSearchChange,
    searchPlaceholder = 'Search...',
    status = '',
    onStatusChange,
    statuses,
    extraFilters = null,
    perPage = 25,
    onPerPageChange,
    onApply,
    from = 0,
    to = 0,
    total = 0,
}) {
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                onApply?.({ page: 1 });
            }}
            className="px-4 sm:px-6 py-4 border-b border-border-warm flex flex-wrap items-center gap-3 bg-cream/50"
        >
            <div className="relative flex-1 min-w-0 max-w-full sm:max-w-xs">
                <span className="absolute inset-y-0 left-3 flex items-center text-ink-muted"><MagnifyingGlass /></span>
                <input
                    type="text"
                    placeholder={searchPlaceholder}
                    value={search}
                    onChange={(e) => onSearchChange?.(e.target.value)}
                    onBlur={() => onApply?.({ page: 1 })}
                    className="pl-9 w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta"
                />
            </div>
            {Array.isArray(statuses) && statuses.length > 0 && (
                <select
                    value={status}
                    onChange={(e) => onStatusChange ? onStatusChange(e.target.value) : onApply?.({ status: e.target.value, page: 1 })}
                    className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]"
                >
                    <option value="">All statuses</option>
                    {statuses.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
            )}
            {extraFilters}
            <select
                value={perPage}
                onChange={(e) => onPerPageChange ? onPerPageChange(Number(e.target.value)) : onApply?.({ per_page: Number(e.target.value), page: 1 })}
                className="border border-border-warm rounded-xl py-2.5 pl-4 pr-10 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta min-w-[140px]"
            >
                <option value={10}>10 per page</option>
                <option value={25}>25 per page</option>
                <option value={50}>50 per page</option>
                <option value={100}>100 per page</option>
            </select>
            <button type="submit" className="px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta">Apply</button>
            <span className="text-ink-muted text-sm font-medium ml-auto whitespace-nowrap">
                {total > 0 ? `${from}–${to} of ${total}` : '0 of 0'}
            </span>
        </form>
    );
}
