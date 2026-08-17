import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentShowLayout, {
    DocumentShowHeader,
    DocumentLines,
    DocumentTotals,
    docBtn,
    docPrimary,
    headerBtn,
    headerPrimary,
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, template, company = {} }) {
    const currency = 'MYR';
    const items = template.items || [];
    const subtotal = items.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const tax = Number(template.tax_amount || 0);
    const total = subtotal + tax;
    const status = template.is_active ? 'active' : 'paused';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('recurring-bills.index')}
                    title={template.name || `Template #${template.id}`}
                    status={status}
                    subtitle={template.supplier?.name || 'No supplier'}
                >
                    <button type="button" className={headerBtn} onClick={() => router.post(route('recurring-bills.toggle', template.id))}>
                        {template.is_active ? 'Pause' : 'Resume'}
                    </button>
                    <button type="button" className={headerPrimary} onClick={() => router.post(route('recurring-bills.run', template.id))}>
                        Run now
                    </button>
                </DocumentShowHeader>
            }
        >
            <Head title={template.name || 'Recurring bill'} />
            <DocumentShowLayout
                company={company}
                docLabel="Recurring bill"
                docNumber={template.name || `#${template.id}`}
                meta={[
                    { label: 'Cadence', value: `${template.interval > 1 ? `Every ${template.interval} ` : ''}${template.cadence}` },
                    { label: 'Next run', value: formatDate(template.next_run_date) },
                    template.last_run_date ? { label: 'Last run', value: formatDate(template.last_run_date) } : null,
                    template.payment_terms_days ? { label: 'Terms', value: `${template.payment_terms_days} days` } : null,
                ].filter(Boolean)}
                partyTitle="Bill from"
                partyName={template.supplier?.name}
                partyLines={partyAddress(template.supplier)}
                notes={template.notes}
                totals={
                    <DocumentTotals
                        rows={[
                            { label: 'Subtotal', value: formatCurrency(subtotal, currency) },
                            tax > 0 ? { label: 'Tax', value: formatCurrency(tax, currency) } : null,
                            { label: 'Each cycle', value: formatCurrency(total, currency), tone: 'total' },
                        ].filter(Boolean)}
                    />
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Each cycle</p>
                            <p className="mt-1 text-3xl font-display font-medium tabular-nums text-ink">
                                {formatCurrency(total, currency)}
                            </p>
                            <p className="mt-1 text-xs text-ink-muted">
                                {template.generated_count || 0} generated{template.auto_post ? ' · auto-posts' : ''}
                            </p>
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            <button type="button" className={docPrimary} onClick={() => router.post(route('recurring-bills.run', template.id))}>
                                Run now
                            </button>
                            <button type="button" className={docBtn} onClick={() => router.post(route('recurring-bills.toggle', template.id))}>
                                {template.is_active ? 'Pause template' : 'Resume template'}
                            </button>
                        </div>
                        <SidebarCard title="Schedule">
                            <div className="flex justify-between text-sm gap-2">
                                <span className="text-ink-muted">Starts</span>
                                <span>{formatDate(template.start_date)}</span>
                            </div>
                            <div className="flex justify-between text-sm gap-2">
                                <span className="text-ink-muted">Ends</span>
                                <span>{template.end_date ? formatDate(template.end_date) : 'Open-ended'}</span>
                            </div>
                            <div className="flex justify-between text-sm gap-2">
                                <span className="text-ink-muted">Next</span>
                                <span>{formatDate(template.next_run_date)}</span>
                            </div>
                        </SidebarCard>
                    </>
                }
            >
                <DocumentLines items={items} currency={currency} formatCurrency={formatCurrency} />
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
