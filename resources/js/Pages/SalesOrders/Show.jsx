import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import { formatCurrency } from '@/utils/currency';
import { formatDate } from '@/utils/dates';
import DocumentTrail from '@/Components/DocumentTrail';
import DocumentShowLayout, {
    DocumentShowHeader,
    docBtn,
    docPrimary,
    docGhost,
    field,
    headerBtn,
    headerPrimary,
    partyAddress,
    partyShipping,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, order, document_trail = [], company = {} }) {
    const currency = order.currency || 'MYR';
    const permissions = auth.permissions || [];
    const deliveries = order.delivery_orders || order.deliveryOrders || [];
    const invoices = order.invoices || [];
    const shipTo = partyShipping(order.customer);
    const billTo = partyAddress(order.customer);
    const locked = ['delivered', 'invoiced', 'cancelled'].includes(order.status);
    const canEdit = permissions.includes('sales-orders.edit') && !locked;
    const canCancel = permissions.includes('sales-orders.edit') && !['cancelled', 'invoiced'].includes(order.status);
    const canConvert = order.status !== 'cancelled' && order.status !== 'invoiced' && permissions.includes('invoices.create');
    const canDeliver = order.status !== 'cancelled' && order.status !== 'invoiced';
    const canEmail = permissions.includes('invoices.email');
    const [qtys, setQtys] = useState(
        Object.fromEntries((order.items || []).map((i) => [i.id, Math.max(0, Number(i.quantity) - Number(i.qty_delivered || 0))]))
    );

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
                    backHref={route('sales-orders.index')}
                    title={order.so_number}
                    status={order.status}
                    subtitle={order.customer?.name || 'No customer'}
                >
                    {canEdit && <Link href={route('sales-orders.edit', order.id)} className={headerBtn}>Edit</Link>}
                    {canConvert && (
                        <button
                            type="button"
                            className={headerPrimary}
                            onClick={() => postAction(route('sales-orders.invoice', order.id), {
                                confirm: { title: 'Convert to invoice?', text: 'Creates a draft invoice from remaining uninvoiced quantities.', confirmText: 'Convert', icon: 'question' },
                            })}
                        >
                            Convert to invoice
                        </button>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={order.so_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Sales order"
                docNumber={order.so_number}
                meta={[
                    { label: 'Issued', value: formatDate(order.issue_date) },
                    { label: 'Expected', value: order.expected_date ? formatDate(order.expected_date) : '—' },
                ]}
                partyTitle="Bill to"
                partyName={order.customer?.name}
                partyLines={billTo}
                secondaryParty={shipTo.length > 0 ? { title: 'Ship to', lines: shipTo } : null}
                notes={order.customer_notes}
                totals={
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-6 text-ink-muted">
                            <dt>Subtotal</dt>
                            <dd className="font-mono tabular-nums text-ink">{formatCurrency(order.amount_before_tax, currency)}</dd>
                        </div>
                        {Number(order.discount_total) > 0 && (
                            <div className="flex justify-between gap-6 text-ink-muted">
                                <dt>Discount</dt>
                                <dd className="font-mono tabular-nums text-terracotta">−{formatCurrency(order.discount_total, currency)}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-6 text-ink-muted">
                            <dt>Tax</dt>
                            <dd className="font-mono tabular-nums text-ink">{formatCurrency(order.tax_amount, currency)}</dd>
                        </div>
                        {Number(order.shipping_amount) > 0 && (
                            <div className="flex justify-between gap-6 text-ink-muted">
                                <dt>Shipping</dt>
                                <dd className="font-mono tabular-nums text-ink">{formatCurrency(order.shipping_amount, currency)}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-6 pt-2 border-t border-ink/15 text-base font-semibold text-ink">
                            <dt>Total</dt>
                            <dd className="font-mono tabular-nums">{formatCurrency(order.total_amount, currency)}</dd>
                        </div>
                    </dl>
                }
                sidebar={
                    <>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className={sectionTitle}>Total</p>
                            <p className="mt-1 text-3xl font-display font-medium tabular-nums text-ink">
                                {formatCurrency(order.total_amount, currency)}
                            </p>
                        </div>
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 space-y-2">
                            {canEmail && (
                                <button type="button" className={docPrimary} onClick={() => router.post(route('sales-orders.email', order.id))}>Email customer</button>
                            )}
                            <a href={route('sales-orders.pdf', order.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {canCancel && (
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={() => postAction(route('sales-orders.cancel', order.id), {
                                        confirm: { title: 'Cancel this sales order?', text: 'This cannot be undone.', confirmText: 'Cancel order', confirmColor: '#dc2626', icon: 'warning' },
                                    })}
                                >
                                    Cancel order
                                </button>
                            )}
                        </div>
                        <DocumentTrail steps={document_trail} variant="stack" />
                        {canDeliver && (
                            <SidebarCard title="Create delivery">
                                <div className="space-y-2">
                                    {(order.items || []).map((i) => (
                                        <label key={i.id} className="block text-sm">
                                            <span className="text-ink-muted truncate block">{i.description}</span>
                                            <span className="text-[10px] text-ink-muted">Open {Math.max(0, Number(i.quantity) - Number(i.qty_delivered || 0))}</span>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className={`${field} mt-1 text-right font-mono`}
                                                value={qtys[i.id] ?? 0}
                                                onChange={(e) => setQtys({ ...qtys, [i.id]: e.target.value })}
                                            />
                                        </label>
                                    ))}
                                    <button
                                        type="button"
                                        className={docPrimary}
                                        onClick={() => router.post(route('sales-orders.deliver', order.id), { quantities: qtys })}
                                    >
                                        Create delivery order
                                    </button>
                                </div>
                            </SidebarCard>
                        )}
                        {deliveries.length > 0 && (
                            <SidebarCard title="Delivery orders">
                                {deliveries.map((d) => (
                                    <Link key={d.id} href={route('delivery-orders.show', d.id)} className="block text-sm text-terracotta hover:underline">
                                        {d.do_number} · {d.status}
                                    </Link>
                                ))}
                            </SidebarCard>
                        )}
                        {invoices.length > 0 && (
                            <SidebarCard title="Invoices">
                                {invoices.map((inv) => (
                                    <Link key={inv.id} href={route('invoices.show', inv.id)} className="block text-sm text-terracotta hover:underline">
                                        {inv.invoice_number} · {inv.status}
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
                                <th className="px-6 sm:px-10 py-3 text-left font-semibold">Description</th>
                                <th className="px-3 py-3 text-right font-semibold w-16">Qty</th>
                                <th className="px-3 py-3 text-right font-semibold w-20">Delivered</th>
                                <th className="px-3 py-3 text-right font-semibold w-20">Invoiced</th>
                                <th className="px-3 py-3 text-right font-semibold w-24">Price</th>
                                <th className="px-6 sm:px-10 py-3 text-right font-semibold w-28">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(order.items || []).map((i) => (
                                <tr key={i.id} className="border-b border-border-warm/60 last:border-0">
                                    <td className="px-6 sm:px-10 py-3.5 whitespace-pre-wrap text-ink align-top">{i.description}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{i.quantity}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{i.qty_delivered}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{i.qty_invoiced}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{formatCurrency(i.unit_price, currency)}</td>
                                    <td className="px-6 sm:px-10 py-3.5 text-right font-mono text-ink tabular-nums align-top">{formatCurrency(i.amount, currency)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </DocumentShowLayout>
        </AuthenticatedLayout>
    );
}
