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
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, order, trail = [], editable = false, lock_reason = null, company = {} }) {
    const currency = order.currency || 'MYR';
    const receipts = order.goods_receipts || order.goodsReceipts || [];
    const bills = order.bills || [];
    const billTo = partyAddress(order.supplier);
    const canConvert = order.status !== 'cancelled';
    const canCancel = order.status !== 'cancelled' && order.status !== 'billed';
    const canReceive = order.status !== 'cancelled';
    const [qtys, setQtys] = useState(
        Object.fromEntries((order.items || []).map((i) => [i.id, Math.max(0, Number(i.quantity) - Number(i.qty_received || 0))]))
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
                    backHref={route('purchase-orders.index')}
                    title={order.po_number}
                    status={order.status}
                    subtitle={order.supplier?.name || 'No supplier'}
                >
                    {editable && <Link href={route('purchase-orders.edit', order.id)} className={headerBtn}>Edit</Link>}
                    {canConvert && (
                        <button
                            type="button"
                            className={headerPrimary}
                            onClick={() => postAction(route('purchase-orders.bill', order.id), {
                                confirm: { title: 'Convert to bill?', text: 'Creates a draft bill from remaining unbilled quantities.', confirmText: 'Convert', icon: 'question' },
                            })}
                        >
                            Convert to bill
                        </button>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={order.po_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Purchase order"
                docNumber={order.po_number}
                meta={[
                    { label: 'Issued', value: formatDate(order.issue_date) },
                    { label: 'Expected', value: order.expected_date ? formatDate(order.expected_date) : '—' },
                ]}
                partyTitle="Supplier"
                partyName={order.supplier?.name}
                partyLines={billTo}
                notes={order.notes}
                footer={lock_reason ? <p className="text-xs text-ink-muted">{lock_reason}</p> : null}
                totals={
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-6 text-ink-muted">
                            <dt>Subtotal</dt>
                            <dd className="font-mono tabular-nums text-ink">{formatCurrency(order.amount_before_tax, currency)}</dd>
                        </div>
                        <div className="flex justify-between gap-6 text-ink-muted">
                            <dt>Tax</dt>
                            <dd className="font-mono tabular-nums text-ink">{formatCurrency(order.tax_amount, currency)}</dd>
                        </div>
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
                            <button type="button" className={docPrimary} onClick={() => router.post(route('purchase-orders.email', order.id))}>Email supplier</button>
                            <a href={route('purchase-orders.pdf', order.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {canCancel && (
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={() => postAction(route('purchase-orders.cancel', order.id), {
                                        confirm: { title: 'Cancel this purchase order?', text: 'This cannot be undone.', confirmText: 'Cancel order', confirmColor: '#dc2626', icon: 'warning' },
                                    })}
                                >
                                    Cancel order
                                </button>
                            )}
                        </div>
                        <DocumentTrail steps={trail} variant="stack" />
                        {canReceive && (
                            <SidebarCard title="Receive goods">
                                <div className="space-y-2">
                                    {(order.items || []).map((i) => (
                                        <label key={i.id} className="block text-sm">
                                            <span className="text-ink-muted truncate block">{i.description}</span>
                                            <span className="text-[10px] text-ink-muted">Open {Math.max(0, Number(i.quantity) - Number(i.qty_received || 0))}</span>
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
                                        onClick={() => router.post(route('purchase-orders.receive', order.id), { quantities: qtys })}
                                    >
                                        Create goods receipt
                                    </button>
                                </div>
                            </SidebarCard>
                        )}
                        {receipts.length > 0 && (
                            <SidebarCard title="Goods receipts">
                                {receipts.map((d) => (
                                    <Link key={d.id} href={route('goods-receipts.show', d.id)} className="block text-sm text-terracotta hover:underline">
                                        {d.grn_number} · {d.status}
                                    </Link>
                                ))}
                            </SidebarCard>
                        )}
                        {bills.length > 0 && (
                            <SidebarCard title="Bills">
                                {bills.map((b) => (
                                    <Link key={b.id} href={route('bills.show', b.id)} className="block text-sm text-terracotta hover:underline">
                                        {b.bill_number} · {b.status}
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
                                <th className="px-3 py-3 text-right font-semibold w-20">Received</th>
                                <th className="px-3 py-3 text-right font-semibold w-20">Billed</th>
                                <th className="px-3 py-3 text-right font-semibold w-24">Price</th>
                                <th className="px-6 sm:px-10 py-3 text-right font-semibold w-28">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(order.items || []).map((i) => (
                                <tr key={i.id} className="border-b border-border-warm/60 last:border-0">
                                    <td className="px-6 sm:px-10 py-3.5 whitespace-pre-wrap text-ink align-top">{i.description}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{i.quantity}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{i.qty_received}</td>
                                    <td className="px-3 py-3.5 text-right font-mono text-ink-muted tabular-nums align-top">{i.qty_billed}</td>
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
