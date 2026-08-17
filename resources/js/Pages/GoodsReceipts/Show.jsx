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
    headerPrimary,
    partyAddress,
    sectionTitle,
    SidebarCard,
} from '@/Components/DocumentShowLayout';

export default function Show({ auth, order, trail = [], company = {} }) {
    const po = order.purchase_order || order.purchaseOrder;
    const bills = order.bills || [];
    const billTo = partyAddress(order.supplier);
    const canConvert = order.status !== 'billed' && order.status !== 'cancelled';
    const canReturn = order.status === 'received';

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
                    backHref={route('goods-receipts.index')}
                    title={order.grn_number}
                    status={order.status}
                    subtitle={order.supplier?.name || 'No supplier'}
                >
                    {canConvert && (
                        <button
                            type="button"
                            className={headerPrimary}
                            onClick={() => postAction(route('goods-receipts.bill', order.id), {
                                confirm: { title: 'Convert to bill?', text: 'Creates a draft bill from this goods receipt.', confirmText: 'Convert', icon: 'question' },
                            })}
                        >
                            Convert to bill
                        </button>
                    )}
                </DocumentShowHeader>
            }
        >
            <Head title={order.grn_number} />
            <DocumentShowLayout
                company={company}
                docLabel="Goods receipt"
                docNumber={order.grn_number}
                meta={[
                    { label: 'Issued', value: formatDate(order.issue_date) },
                    { label: 'Received', value: order.received_date ? formatDate(order.received_date) : '—' },
                ]}
                partyTitle="Supplier"
                partyName={order.supplier?.name}
                partyLines={billTo}
                notes={order.notes}
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
                            <button type="button" className={docPrimary} onClick={() => router.post(route('goods-receipts.email', order.id))}>Email supplier</button>
                            <a href={route('goods-receipts.pdf', order.id)} target="_blank" rel="noreferrer" className={docBtn}>View PDF</a>
                            {po && (
                                <Link href={route('purchase-orders.show', po.id)} className={docBtn}>Open {po.po_number}</Link>
                            )}
                            {canReturn && (
                                <button
                                    type="button"
                                    className={`${docGhost} text-terracotta hover:text-terracotta`}
                                    onClick={() => postAction(route('goods-receipts.return', order.id), {
                                        confirm: { title: 'Return this goods receipt?', text: 'Marks the receipt as returned.', confirmText: 'Return', confirmColor: '#dc2626', icon: 'warning' },
                                    })}
                                >
                                    Return receipt
                                </button>
                            )}
                        </div>
                        <DocumentTrail steps={trail} variant="stack" />
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
                                <th className="px-6 sm:px-10 py-3 text-left font-semibold">Item</th>
                                <th className="px-6 sm:px-10 py-3 text-right font-semibold w-32">Qty received</th>
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
