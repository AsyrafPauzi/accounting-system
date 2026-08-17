import React from 'react';
import { Link } from '@inertiajs/react';

const LABELS = {
    estimate: 'Estimate',
    sales_order: 'Sales order',
    delivery_order: 'Delivery',
    invoice: 'Invoice',
    credit_note: 'Credit note',
    purchase_order: 'Purchase order',
    goods_receipt: 'Goods receipt',
    bill: 'Bill',
    supplier_credit_note: 'Supplier credit',
};

function StepLabel({ step }) {
    const label = `${LABELS[step.type] || step.type} ${step.number}`;
    if (step.href) {
        return (
            <Link href={step.href} className="text-terracotta font-medium hover:underline">
                {label}
            </Link>
        );
    }
    return <span className="text-ink font-medium">{label}</span>;
}

export default function DocumentTrail({ steps = [], variant = 'inline' }) {
    if (!steps.length) {
        return null;
    }

    if (variant === 'stack') {
        return (
            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Document flow</p>
                <ol className="mt-3 space-y-0">
                    {steps.map((step, i) => (
                        <li key={`${step.type}-${step.id}`} className="flex gap-3">
                            <div className="flex flex-col items-center self-stretch">
                                <span className={`mt-0.5 h-2 w-2 rounded-full ${i === steps.length - 1 ? 'bg-terracotta' : 'bg-border-warm'}`} />
                                {i < steps.length - 1 && <span className="w-px flex-1 bg-border-warm my-1" />}
                            </div>
                            <div className={`text-sm min-w-0 pb-3 ${i === steps.length - 1 ? 'pb-0' : ''}`}>
                                <StepLabel step={step} />
                            </div>
                        </li>
                    ))}
                </ol>
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-border-warm bg-cream/30 px-4 py-3">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span className="text-[10px] uppercase tracking-wider font-semibold text-ink-muted shrink-0">Flow</span>
                <ol className="flex flex-wrap items-center gap-1.5 text-sm min-w-0">
                    {steps.map((step, i) => (
                        <li key={`${step.type}-${step.id}`} className="flex items-center gap-1.5">
                            {i > 0 && <span className="text-ink-muted/70" aria-hidden>→</span>}
                            <StepLabel step={step} />
                        </li>
                    ))}
                </ol>
            </div>
        </div>
    );
}
