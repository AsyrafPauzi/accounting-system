import React from 'react';

const ChevronLeft = () => (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
    </svg>
);
const ChevronRight = () => (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
    </svg>
);

export default function IndexPagination({ currentPage = 1, lastPage = 1, onPage }) {
    if (lastPage <= 1) return null;

    return (
        <div className="px-4 sm:px-6 py-4 border-t border-border-warm flex flex-wrap items-center justify-between gap-3 bg-cream/30">
            <p className="text-sm text-ink">Page {currentPage} of {lastPage}</p>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    disabled={currentPage <= 1}
                    onClick={() => onPage?.(Math.max(1, currentPage - 1))}
                    className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${currentPage <= 1 ? 'pointer-events-none text-ink-muted border-border-warm' : 'text-ink border-border-warm hover:bg-cream'}`}
                >
                    <ChevronLeft /> Previous
                </button>
                <button
                    type="button"
                    disabled={currentPage >= lastPage}
                    onClick={() => onPage?.(Math.min(lastPage, currentPage + 1))}
                    className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold border ${currentPage >= lastPage ? 'pointer-events-none text-ink-muted border-border-warm' : 'text-ink border-border-warm hover:bg-cream'}`}
                >
                    Next <ChevronRight />
                </button>
            </div>
        </div>
    );
}
