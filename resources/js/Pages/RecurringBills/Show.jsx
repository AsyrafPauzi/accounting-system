import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatDate } from '@/utils/dates';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';
const primary = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark';

export default function Show({ auth, template }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={template.name || 'Recurring bill'} />
            <div className="max-w-4xl mx-auto p-4 sm:p-6 space-y-4 min-w-0">
                <Link href={route('recurring-bills.index')} className="text-xs text-ink-muted">← Recurring bills</Link>
                <div className="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">{template.name || `Template #${template.id}`}</h1>
                        <p className="text-sm text-ink-muted">{template.supplier?.name} · {template.cadence} · next {formatDate(template.next_run_date)}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" className={btn} onClick={() => router.post(route('recurring-bills.toggle', template.id))}>{template.is_active ? 'Pause' : 'Resume'}</button>
                        <button type="button" className={primary} onClick={() => router.post(route('recurring-bills.run', template.id))}>Run now</button>
                    </div>
                </div>
                <div className="bg-surface rounded-2xl border border-border-warm p-5 space-y-2 text-sm shadow-sm">
                    <h2 className="text-sm font-display font-medium text-ink pb-2 border-b border-border-warm">Line items</h2>
                    {(template.items || []).map((i) => (
                        <div key={i.id} className="flex justify-between gap-4">
                            <span className="min-w-0">{i.description}</span>
                            <span className="font-mono tabular-nums shrink-0">{Number(i.amount).toFixed(2)}</span>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
