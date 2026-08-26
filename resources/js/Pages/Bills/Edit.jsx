import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import Modal from '@/Components/Modal';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import BillForm, { defaultAccountCode, itemsFromBill, toBillPayload } from './_Form';

const headerBtn = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200';
const headerPrimary = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50';

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Edit({ auth, bill, suppliers = [], expenseAccounts = [], bankAccounts = [], products = [], journal_entry_id = null }) {
    const isDraft = bill.status === 'draft';
    const isVoid = bill.status === 'void';
    const balanceDue = isDraft || isVoid ? 0 : parseFloat(bill.balance_due ?? 0);
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [showReceiptModal, setShowReceiptModal] = useState(false);
    const [receiptUrl, setReceiptUrl] = useState(bill.receipt_url || null);
    const receiptIsPdf = !!receiptUrl && /\.pdf($|\?)/i.test(receiptUrl);
    const accountCode = defaultAccountCode(expenseAccounts);
    const kind = bill.purchase_kind && bill.purchase_kind !== 'credit' ? bill.purchase_kind : 'credit';

    const form = useForm({
        bill_number: bill.bill_number || '',
        purchase_kind: kind,
        supplier_id: bill.supplier_id ? String(bill.supplier_id) : '',
        bill_date: bill.bill_date ? String(bill.bill_date).slice(0, 10) : '',
        due_date: bill.due_date ? String(bill.due_date).slice(0, 10) : '',
        tax_amount: parseFloat(bill.tax_amount) || 0,
        reference: bill.reference || '',
        private_notes: bill.private_notes || '',
        items: itemsFromBill(bill.items, accountCode),
    });

    const { data, setData, processing, errors } = form;

    const paymentForm = useForm({
        amount: balanceDue > 0 ? balanceDue.toFixed(2) : 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (bankAccounts && bankAccounts[0]?.value) || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (!isDraft) return;
        form.transform((current) => toBillPayload(current));
        form.put(route('bills.update', bill.id));
    };

    const handlePost = async () => {
        const ok = await confirm({
            title: 'Post this bill?',
            text: 'This locks the bill and posts the purchase journal.',
            confirmText: 'Post',
            icon: 'question',
        });
        if (ok) router.post(route('bills.post', bill.id));
    };

    const handleVoid = async () => {
        const ok = await confirm({
            title: 'Void this bill?',
            text: 'This reverses the purchase journal. It cannot be undone.',
            confirmText: 'Void',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('bills.void', bill.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('bills.show', bill.id)}
                    title={bill.bill_number}
                    subtitle={`${bill.supplier?.name || 'No supplier'} · ${bill.status}`}
                    formId="bill-form"
                    processing={processing}
                    submitLabel="Save changes"
                    showSubmit={isDraft}
                    actions={
                        <>
                            {isDraft && (
                                <Link href={route('bills.show', bill.id)} className={headerBtn}>Cancel</Link>
                            )}
                            {isDraft && (
                                <button type="submit" form="bill-form" disabled={processing} className={headerPrimary}>
                                    {processing ? 'Saving…' : 'Save changes'}
                                </button>
                            )}
                            {isDraft && auth.permissions.includes('bills.post') && (
                                <button type="button" onClick={handlePost} className={headerPrimary}>Post bill</button>
                            )}
                            {!isDraft && !isVoid && balanceDue > 0 && auth.permissions.includes('bills.record-payment') && (
                                <button type="button" onClick={() => setShowPaymentModal(true)} className={headerPrimary}>
                                    Record payment
                                </button>
                            )}
                            {auth.planPermissions['general-ledger.view'] && journal_entry_id && (
                                <Link href={route('general-ledger.show', journal_entry_id)} className={headerBtn}>
                                    Accounting entry
                                </Link>
                            )}
                            {!isDraft && !isVoid && auth.permissions.includes('bills.void') && (
                                <button type="button" onClick={handleVoid} className={`${headerBtn} text-terracotta`}>Void</button>
                            )}
                            {!isDraft && (
                                <Link href={route('bills.show', bill.id)} className={headerBtn}>View bill</Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`Bill ${bill.bill_number}`} />
            <BillForm
                formId="bill-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                suppliers={suppliers}
                expenseAccounts={expenseAccounts}
                bankAccounts={bankAccounts}
                products={products}
                showKind={false}
                disabled={!isDraft}
                billId={bill.id}
                receiptUrl={receiptUrl}
                receiptIsPdf={receiptIsPdf}
                onViewReceipt={receiptUrl ? () => setShowReceiptModal(true) : undefined}
                onReceiptUploaded={(_ocr, url) => {
                    if (url) setReceiptUrl(url);
                    router.reload({ only: ['bill'] });
                }}
            />

            <Modal show={showReceiptModal} onClose={() => setShowReceiptModal(false)} maxWidth="2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-display font-medium text-ink">Receipt</h3>
                        <button type="button" onClick={() => setShowReceiptModal(false)} className="p-2 text-ink-muted hover:text-ink">
                            <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div className="bg-surface-alt rounded-xl border border-border-warm flex items-center justify-center h-[70vh] overflow-hidden">
                        {receiptIsPdf ? (
                            <iframe src={`${receiptUrl}#view=FitH&toolbar=1`} title="Receipt PDF" className="w-full h-full bg-cream" />
                        ) : (
                            <img src={receiptUrl} alt="Receipt" className="max-w-full max-h-full object-contain p-4" />
                        )}
                    </div>
                    <div className="mt-4 flex justify-end">
                        <a href={receiptUrl} target="_blank" rel="noopener noreferrer" className="px-4 py-2 bg-terracotta text-white rounded-lg text-sm font-semibold">
                            Open in new tab
                        </a>
                    </div>
                </div>
            </Modal>

            {showPaymentModal && (
                <div className="fixed inset-0 bg-ink/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                    <div className="bg-surface rounded-2xl shadow-2xl max-w-md w-full p-8 border border-border-warm">
                        <h3 className="text-xl font-display font-medium text-ink">Record payment</h3>
                        <p className="text-sm text-ink-muted mb-6">Bill {bill.bill_number} · due {formatMoney(balanceDue)}</p>
                        <form
                            className="space-y-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                paymentForm.post(route('bills.record-payment', bill.id), {
                                    onSuccess: () => setShowPaymentModal(false),
                                });
                            }}
                        >
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Amount (RM)</label>
                                <input type="number" step="0.01" value={paymentForm.data.amount} onChange={(e) => paymentForm.setData('amount', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm" required />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Date</label>
                                    <input type="date" value={paymentForm.data.payment_date} onChange={(e) => paymentForm.setData('payment_date', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm" required />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Bank account</label>
                                    <select value={paymentForm.data.bank_account_code} onChange={(e) => paymentForm.setData('bank_account_code', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm">
                                        {(bankAccounts || []).map((a) => (
                                            <option key={a.value} value={a.value}>{a.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="flex gap-3 pt-2">
                                <button type="button" onClick={() => setShowPaymentModal(false)} className="flex-1 py-3 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream">Cancel</button>
                                <button type="submit" disabled={paymentForm.processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50">
                                    {paymentForm.processing ? 'Processing…' : 'Confirm payment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
