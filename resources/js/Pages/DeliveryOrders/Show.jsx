import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import DocumentShowLayout, {
    DocumentShowHeader,
    docBtn,
    docPrimary,
    docGhost,
    headerBtn,
    headerPrimary,
    partyAddress,
    partyShipping,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, order, document_trail = [], company = {} }) {
    const permissions = auth.permissions || [];
    const so = order.sales_order || order.salesOrder;
    const invoices = order.invoices || [];
    const shipTo = partyShipping(order.customer);
    const billTo = partyAddress(order.customer);
    const canConvert = order.status !== 'invoiced' && order.status !== 'cancelled' && permissions.includes('invoices.create');
    const canEdit = permissions.includes('delivery-orders.edit') && order.status === 'delivered';
    const canReturn = permissions.includes('delivery-orders.edit') && order.status === 'delivered';
    const canEmail = permissions.includes('invoices.email');

    const postAction = async (url, opts = {}) => {
        if (opts.confirm) {
            const ok = await confirm(opts.confirm);
            if (!ok) return;
        }
        router.post(url, opts.body || {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentShowHeader
                    backHref={route('delivery-orders.index')}
                    title={order.do_number}
                    status={order.status}
                    subtitle={order.customer?.name || 'No customer'}
                >
                    {canEdit && <Link href={route('delivery-orders.edit', order.id)} className={headerBtn}>Edit</Link>}
                    {canConvert && (
                        <button
                            type="button"
                            className={headerPrimary}
                            onClick={() => postAction(route('delivery-orders.invoice', order.id), {
                                confirm: { title: 'Convert to invoice?', text: 'Creates a draft invoice from this delivery.', confirmText: 'Convert', icon: 'question' },
                            })}
                        >
                            Convert to invoice
                        </button>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={order.do_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Delivery order"
                docNumber={order.do_number}
                meta={[
                    { label: 'Issued', value: formatDate(order.issue_date) },
                    { label: 'Delivered', value: order.delivery_date ? formatDate(order.delivery_date) : '—' },
                ]}
                partyTitle="Ship to"
                partyName={order.customer?.name}
                partyLines={shipTo.length > 0 ? shipTo : billTo}
                secondaryParty={shipTo.length > 0 ? { title: 'Bill to', name: order.customer?.name, lines: billTo } : null}
                notes={order.customer_notes}
                footer={
                    <div className="grid grid-cols-2 gap-8 pt-6">
                        <div>
                            <p className={sectionTitle}>Received by</p>
                            <div className="mt-8 border-b border-border-warm" />
                            <p className="mt-2 text-xs text-ink-muted">Name / signature</p>
                        </div>
                        <div>
                            <p className={sectionTitle}>Date</p>
                            <div className="mt-8 border-b border-border-warm" />
                        </div>
                    </div>
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            {canEmail && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('delivery-orders.email', order.id))}>Email customer</button>
                            )}
                            <a href={route('delivery-orders.pdf', order.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {so && (
                                <Link href={route('sales-orders.show', so.id)} className={docBtn}>Open {so.so_number}</Link>
                            )}
                            {canReturn && (
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={() => postAction(route('delivery-orders.return', order.id), {
                                        confirm: { title: 'Return this delivery?', text: 'Marks the delivery as returned.', confirmText: 'Return', confirmColor: '#dc2626', icon: 'warning' },
                                    })}
                                >
                                    Return delivery
                                </button>
                            )}
                        </div>
                        <DocumentTrail steps={document_trail} variant="stack" />
                        {invoices.length > 0 && (
                            <SidebarCard title="Invoices">
                                {invoices.map((inv) => (
                                    <Link key={inv.id} href={route('invoices.show', inv.id)} className="block text-sm text-terracotta hover:underline">
                                        {inv.invoice_number}
                                        {inv.status ? ` · ${inv.status}` : ''}
                                    </Link>
                                ))}
                            </SidebarCard>
                        )}
                    </>
                }
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className={`${sectionTitle} border-y border-ink/15 bg-cream/40`}>
                                <th className="px-6 sm:px-10 py-3 text-left font-semibold">Item</th>
                                <th className="px-6 sm:px-10 py-3 text-right font-semibold w-32">Qty delivered</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(order.items || []).map((i) => (
                                <tr key={i.id} className="border-b border-border-warm/60 last:border-0">
                                    <td className="px-6 sm:px-10 py-3.5 whitespace-pre-wrap text-ink align-top">{i.description}</td>
                                    <td className="px-6 sm:px-10 py-3.5 text-right font-mono text-ink tabular-nums align-top">{i.quantity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
