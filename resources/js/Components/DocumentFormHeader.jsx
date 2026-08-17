import React from 'react';
import { Link } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
        </svg>
    ),
    Document: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    ),
};

export default function DocumentFormHeader({
    backHref,
    title,
    subtitle,
    cancelHref,
    formId,
    processing = false,
    submitLabel,
    processingLabel = 'Saving…',
    showSubmit = true,
    submitDisabled = false,
    accent = 'terracotta',
    actions,
}) {
    const submitClass = accent === 'mustard'
        ? 'inline-flex items-center gap-2 px-5 py-2 rounded-xl font-semibold text-white bg-mustard hover:bg-mustard disabled:opacity-50 shadow-lg transition-all duration-200'
        : 'inline-flex items-center gap-2 px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg transition-all duration-200';

    return (
        <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
            <div className="flex items-center gap-2 min-w-0">
                <Link href={backHref} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200 shrink-0">
                    <Icons.ChevronLeft />
                </Link>
                <div className="flex items-center gap-2.5 min-w-0">
                    <span className={`p-2 rounded-xl bg-surface-alt shrink-0 ${accent === 'mustard' ? 'text-mustard' : 'text-terracotta'}`}>
                        <Icons.Document />
                    </span>
                    <div className="min-w-0">
                        <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">{title}</h2>
                        {subtitle && <p className="text-ink-muted text-sm font-medium mt-1">{subtitle}</p>}
                    </div>
                </div>
            </div>
            <div className="flex flex-wrap gap-2 shrink-0">
                {actions !== undefined ? actions : (
                    <>
                        <Link href={cancelHref || backHref} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200">
                            Cancel
                        </Link>
                        {showSubmit && (
                            <button type="submit" form={formId} disabled={processing || submitDisabled} className={submitClass}>
                                {processing ? processingLabel : submitLabel}
                            </button>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
