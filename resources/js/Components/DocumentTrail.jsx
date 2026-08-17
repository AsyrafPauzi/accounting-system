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

export default function DocumentTrail({ steps = [] }) {
    if (!steps.length) {
        return null;
    }

    return (
        <div className="bg-surface rounded-2xl border border-border-warm p-5">
            <h3 className="text-sm font-semibold mb-3">Document flow</h3>
            <ol className="flex flex-wrap items-center gap-2 text-sm">
                {steps.map((step, i) => (
                    <li key={`${step.type}-${step.id}`} className="flex items-center gap-2">
                        {i > 0 && <span className="text-ink-muted">→</span>}
                        {step.href ? (
                            <Link href={step.href} className="text-terracotta font-medium">
                                {LABELS[step.type] || step.type} {step.number}
                            </Link>
                        ) : (
                            <span>{LABELS[step.type] || step.type} {step.number}</span>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
}
