import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { confirm } from '@/utils/swal';
import Modal from '@/Components/Modal';
import ReceiptUpload from '@/Components/ReceiptUpload';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    Currency: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
    ExternalLink: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>,
};

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const inputReadonlyClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink-muted bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

function formatMoney(n) {
    return (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Edit({ auth, bill, suppliers = [], expenseAccounts = [], bankAccounts = [], journal_entry_id = null }) {
    const isDraft = bill.status === 'draft';
    const balanceDue = isDraft || bill.status === 'void' ? 0 : Math.max(0, (parseFloat(bill.total_amount) || 0) - (parseFloat(bill.amount_paid) || 0));
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [showReceiptModal, setShowReceiptModal] = useState(false);

    const receiptIsPdf = !!bill.receipt_path && /\.pdf($|\?)/i.test(bill.receipt_path);

    const initialItems = (bill.items && bill.items.length > 0)
        ? bill.items.map((item) => ({
            id: item.id,
            account_code: item.account_code || '',
            description: item.description || '',
            quantity: parseFloat(item.quantity) || 1,
            unit_amount: parseFloat(item.unit_amount) || 0,
            amount: parseFloat(item.amount) || 0,
        }))
        : [{ account_code: (expenseAccounts && expenseAccounts[0]?.value) || '', description: '', quantity: 1, unit_amount: 0, amount: 0 }];

    const { data, setData, put, post, processing, errors } = useForm({
        bill_number: bill.bill_number || '',
        supplier_id: bill.supplier_id ? String(bill.supplier_id) : '',
        bill_date: bill.bill_date ? (typeof bill.bill_date === 'string' ? bill.bill_date.slice(0, 10) : bill.bill_date) : '',
        due_date: bill.due_date ? (typeof bill.due_date === 'string' ? bill.due_date.slice(0, 10) : bill.due_date) : '',
        tax_amount: parseFloat(bill.tax_amount) || 0,
        reference: bill.reference || '',
        private_notes: bill.private_notes || '',
        items: initialItems,
    });

    const paymentForm = useForm({
        amount: balanceDue > 0 ? balanceDue.toFixed(2) : 0,
        payment_date: new Date().toISOString().split('T')[0],
        bank_account_code: (bankAccounts && bankAccounts[0]?.value) || '',
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { account_code: (expenseAccounts && expenseAccounts[0]?.value) || '', description: '', quantity: 1, unit_amount: 0, amount: 0 },
        ]);
    };

    const removeItem = (index) => {
        if (data.items.length > 1) setData('items', data.items.filter((_, i) => i !== index));
    };

    const updateItem = (index, field, value) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };
        if (field === 'quantity' || field === 'unit_amount') {
            const qty = parseFloat(field === 'quantity' ? value : newItems[index].quantity) || 0;
            const unit = parseFloat(field === 'unit_amount' ? value : newItems[index].unit_amount) || 0;
            newItems[index].amount = Math.round(qty * unit * 100) / 100;
        }
        setData('items', newItems);
    };

    const subtotal = data.items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const tax = parseFloat(data.tax_amount) || 0;
    const total = Math.round((subtotal + tax) * 100) / 100;

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!isDraft) return;
        put(route('bills.update', bill.id));
    };

    const handlePost = async () => {
        const ok = await confirm({
            title: 'Post to Ledger?',
            text: 'This will create General Ledger entries. The bill will no longer be editable.',
            confirmText: 'Post',
            icon: 'question',
        });
        if (ok) router.post(route('bills.post', bill.id));
    };

    const handleVoid = async () => {
        const ok = await confirm({
            title: 'Void Bill?',
            text: 'This creates a reversal entry and sets the bill to void.',
            confirmText: 'Void',
            confirmColor: '#dc2626',
            icon: 'warning',
        });
        if (ok) router.post(route('bills.void', bill.id));
    };

    const handlePaymentSubmit = (e) => {
        e.preventDefault();
        paymentForm.post(route('bills.record-payment', bill.id), {
            onSuccess: () => setShowPaymentModal(false),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route('bills.index')} className="p-2 rounded-xl border border-border-warm text-ink hover:bg-cream">
                            <Icons.ChevronLeft />
                        </Link>
                        <div>
                            <h2 className="text-2xl font-display font-medium text-ink">{bill.bill_number}</h2>
                            <p className="text-ink-muted text-sm">
                                {bill.supplier?.name || 'No supplier'} · {bill.status}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {isDraft && (
                            <button type="button" onClick={handlePost} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta">
                                <Icons.Check /> Post to ledger
                            </button>
                        )}
                        {!isDraft && bill.status !== 'void' && balanceDue > 0 && (
                            <button type="button" onClick={() => setShowPaymentModal(true)} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-forest bg-forest/10 hover:bg-forest/10">
                                <Icons.Currency /> Record payment
                            </button>
                        )}
                        {auth.planPermissions['general-ledger.view'] && journal_entry_id && (
                            <Link href={route('general-ledger.show', journal_entry_id)} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                Accounting Entry
                            </Link>
                        )}
                        {!isDraft && bill.status !== 'void' && (
                            <button type="button" onClick={handleVoid} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream">
                                Void
                            </button>
                        )}
                        <Link href={route('bills.index')} className="px-4 py-2 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream">
                            Back to list
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Bill ${bill.bill_number}`} />

            <div className="space-y-8 max-w-4xl mx-auto">
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <h3 className="text-sm font-display font-medium text-ink">Bill details</h3>
                    </div>
                    <div className="p-6 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bill number</label>
                                <input type="text" value={data.bill_number} onChange={(e) => setData('bill_number', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} readOnly={!isDraft} required />
                                {errors.bill_number && <p className="text-terracotta text-xs mt-1">{errors.bill_number}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Supplier</label>
                                <select value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} disabled={!isDraft}>
                                    <option value="">— No supplier —</option>
                                    {suppliers.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bill date</label>
                                <input type="date" value={data.bill_date} onChange={(e) => setData('bill_date', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} readOnly={!isDraft} required />
                            </div>
                            <div>
                                <label className={labelClass}>Due date</label>
                                <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} readOnly={!isDraft} />
                            </div>
                        </div>
                        <div>
                            <label className={labelClass}>Vendor reference</label>
                            <input type="text" value={data.reference} onChange={(e) => setData('reference', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} readOnly={!isDraft} placeholder="Vendor invoice #" />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Tax amount (RM)</label>
                                <input type="number" step="0.01" min="0" value={data.tax_amount} onChange={(e) => setData('tax_amount', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} readOnly={!isDraft} />
                            </div>
                            {!isDraft && (
                                <div>
                                    <label className={labelClass}>Amount paid</label>
                                    <div className={inputReadonlyClass + ' py-2.5'}>{formatMoney(bill.amount_paid)}</div>
                                </div>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>Private notes</label>
                            <textarea value={data.private_notes} onChange={(e) => setData('private_notes', e.target.value)} className={isDraft ? inputClass : inputReadonlyClass} rows={2} readOnly={!isDraft} />
                        </div>
                    </div>
                </div>

                {/* Receipt Section — always shown so users can attach OR replace
                    a missing receipt without leaving the bill detail page. */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50 flex items-center justify-between">
                        <h3 className="text-sm font-display font-medium text-ink">
                            {bill.receipt_url ? 'Attached receipt' : 'No receipt attached'}
                        </h3>
                        {bill.receipt_url && (
                            <button
                                type="button"
                                onClick={() => setShowReceiptModal(true)}
                                className="text-xs font-semibold text-terracotta hover:underline flex items-center gap-1"
                            >
                                View full size <Icons.ExternalLink />
                            </button>
                        )}
                    </div>
                    <div className="p-6 space-y-4">
                        {bill.receipt_url && (
                            receiptIsPdf ? (
                                <div className="rounded-xl overflow-hidden border border-border-warm bg-cream relative group">
                                    <iframe
                                        src={`${bill.receipt_url}#view=FitH&toolbar=1`}
                                        title="Receipt PDF"
                                        className="w-full h-[600px] bg-cream"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowReceiptModal(true)}
                                        className="absolute top-3 right-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface/95 border border-border-warm text-xs font-semibold text-ink hover:bg-cream shadow-sm"
                                    >
                                        Expand <Icons.ExternalLink />
                                    </button>
                                </div>
                            ) : (
                                <div className="rounded-xl overflow-hidden border border-border-warm bg-cream max-h-96 flex items-center justify-center relative group">
                                    <img
                                        src={bill.receipt_url}
                                        alt="Receipt"
                                        className="max-w-full max-h-96 object-contain cursor-zoom-in transition-transform"
                                        onClick={() => setShowReceiptModal(true)}
                                        onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                    />
                                </div>
                            )
                        )}

                        <div>
                            <p className="text-xs text-ink-muted mb-3">
                                {bill.receipt_url
                                    ? 'Replace the attached receipt by uploading a new file. The old one will be overwritten.'
                                    : 'Upload a JPG, PNG, WebP or PDF up to 10 MB. The file is stored against this bill and visible only to your tenant.'}
                            </p>
                            <ReceiptUpload
                                billId={bill.id}
                                onOcrComplete={() => router.reload({ only: ['bill'] })}
                            />
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm bg-cream/50 flex items-center justify-between">
                        <h3 className="text-sm font-display font-medium text-ink">Line items</h3>
                        {isDraft && (
                            <button type="button" onClick={addItem} className="btn-app-secondary text-terracotta">
                                <Icons.Plus /> Add line
                            </button>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full table-fixed min-w-[820px]">
                            <colgroup>
                                <col className={isDraft ? "w-[24%]" : "w-[28%]"} />
                                <col className={isDraft ? "w-[28%]" : "w-[32%]"} />
                                <col className="w-[10%]" />
                                <col className="w-[14%]" />
                                <col className="w-[14%]" />
                                {isDraft && <col className="w-[10%]" />}
                            </colgroup>
                            <thead>
                                <tr className="text-left text-eyebrow font-semibold text-ink-muted uppercase border-b border-border-warm bg-cream/80">
                                    <th className="px-3 py-3">Account</th>
                                    <th className="px-3 py-3">Description</th>
                                    <th className="px-3 py-3">Qty</th>
                                    <th className="px-3 py-3">Unit amount</th>
                                    <th className="px-3 py-3 text-right">Amount</th>
                                    {isDraft && <th className="px-3 py-3 text-center">Remove</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {data.items.map((item, index) => (
                                    <tr key={index} className="border-b border-border-warm last:border-0">
                                        <td className="px-3 py-3">
                                            {isDraft ? (
                                                <select value={item.account_code} onChange={(e) => updateItem(index, 'account_code', e.target.value)} className={inputClass + ' w-full'} required>
                                                    <option value="">Select account</option>
                                                    {(expenseAccounts || []).map((a) => (
                                                        <option key={a.value} value={a.value}>{a.label}</option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <span className="text-ink text-sm">{item.account_code}</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3">{isDraft ? <input type="text" value={item.description} onChange={(e) => updateItem(index, 'description', e.target.value)} className={inputClass + ' w-full'} /> : <span className="text-sm">{item.description || '—'}</span>}</td>
                                        <td className="px-3 py-3">{isDraft ? <input type="number" step="0.01" min="0" value={item.quantity} onChange={(e) => updateItem(index, 'quantity', e.target.value)} className={inputClass + ' w-full'} /> : <span className="text-sm font-mono font-tabular">{item.quantity}</span>}</td>
                                        <td className="px-3 py-3">{isDraft ? <input type="number" step="0.01" min="0" value={item.unit_amount} onChange={(e) => updateItem(index, 'unit_amount', e.target.value)} className={inputClass + ' w-full'} /> : <span className="text-sm font-mono font-tabular">{formatMoney(item.unit_amount)}</span>}</td>
                                        <td className="px-3 py-3 text-right text-sm font-mono font-tabular">{formatMoney(item.amount)}</td>
                                        {isDraft && (
                                            <td className="px-3 py-3 text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(index)}
                                                    disabled={data.items.length <= 1}
                                                    title={data.items.length <= 1 ? 'A bill must have at least one line item' : 'Remove this line'}
                                                    aria-label="Remove line"
                                                    className="inline-flex items-center justify-center w-9 h-9 rounded-lg text-ink-muted hover:text-terracotta hover:bg-terracotta/10 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-ink-muted transition-colors"
                                                >
                                                    <Icons.Trash />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 sm:px-6 py-4 border-t border-border-warm bg-cream/50 flex justify-end">
                        <div className="w-full max-w-xs space-y-2">
                            <div className="flex justify-between items-baseline">
                                <span className="text-eyebrow font-semibold text-ink-muted uppercase">Subtotal</span>
                                <span className="text-sm font-mono font-tabular text-ink">RM {subtotal.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                            </div>
                            {tax > 0 && (
                                <div className="flex justify-between items-baseline">
                                    <span className="text-eyebrow font-semibold text-ink-muted uppercase">Tax</span>
                                    <span className="text-sm font-mono font-tabular text-ink">RM {tax.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                                </div>
                            )}
                            <div className="flex justify-between items-baseline pt-2 border-t border-border-warm">
                                <span className="text-eyebrow font-semibold text-ink uppercase">Total</span>
                                <span className="text-base font-mono font-tabular font-semibold text-terracotta">RM {total.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                            </div>
                            {!isDraft && balanceDue > 0 && (
                                <div className="flex justify-between items-baseline pt-2 border-t border-border-warm">
                                    <span className="text-eyebrow font-semibold text-terracotta uppercase">Balance due</span>
                                    <span className="text-base font-mono font-tabular font-semibold text-terracotta">RM {formatMoney(balanceDue)}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {isDraft && (
                    <div className="flex gap-2">
                        <Link href={route('bills.index')} className="btn-app-secondary">
                            Cancel
                        </Link>
                        <button type="submit" onClick={handleSubmit} disabled={processing} className="btn-app-primary">
                            {processing ? 'Saving…' : 'Save changes'}
                        </button>
                    </div>
                )}
            </div>

            {/* Receipt Modal */}
            <Modal show={showReceiptModal} onClose={() => setShowReceiptModal(false)} maxWidth="2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-display font-medium text-ink">Receipt Preview</h3>
                        <button onClick={() => setShowReceiptModal(false)} className="p-2 text-ink-muted hover:text-ink">
                            <IconX size={20} />
                        </button>
                    </div>
                    <div className="bg-surface-alt rounded-xl border border-border-warm flex items-center justify-center h-[70vh] overflow-hidden">
                        {receiptIsPdf ? (
                            <iframe
                                src={`${bill.receipt_url}#view=FitH&toolbar=1`}
                                title="Receipt PDF Full Size"
                                className="w-full h-full bg-cream"
                            />
                        ) : (
                            <img
                                src={bill.receipt_url}
                                alt="Receipt Full Size"
                                className="max-w-full max-h-full object-contain shadow-2xl transition-transform duration-300 p-4"
                            />
                        )}
                    </div>
                    <div className="mt-4 flex justify-end">
                        <a 
                            href={bill.receipt_url} 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            className="px-4 py-2 bg-terracotta text-white rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-terracotta"
                        >
                            <Icons.ExternalLink />
                            Open in New Tab
                        </a>
                    </div>
                </div>
            </Modal>

            {showPaymentModal && (
                <div className="fixed inset-0 bg-ink/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
                    <div className="bg-surface rounded-2xl shadow-2xl max-w-md w-full p-8 border border-border-warm">
                        <div className="flex items-center gap-3 mb-6">
                            <span className="p-2.5 rounded-xl bg-forest/10 text-forest"><Icons.Currency /></span>
                            <div>
                                <h3 className="text-xl font-display font-medium text-ink">Record payment</h3>
                                <p className="text-sm text-ink-muted">Bill {bill.bill_number}</p>
                            </div>
                        </div>
                        <form onSubmit={handlePaymentSubmit} className="space-y-5">
                            <div>
                                <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Amount (RM)</label>
                                <div className="relative">
                                    <span className="absolute inset-y-0 left-4 flex items-center text-ink-muted font-medium">RM</span>
                                    <input type="number" value={paymentForm.data.amount} onChange={(e) => paymentForm.setData('amount', e.target.value)} className="w-full pl-12 pr-4 py-3 border border-border-warm rounded-xl font-semibold text-ink" step="0.01" required />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Date</label>
                                    <input type="date" value={paymentForm.data.payment_date} onChange={(e) => paymentForm.setData('payment_date', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm" required />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-2">Bank account</label>
                                    <select value={paymentForm.data.bank_account_code} onChange={(e) => paymentForm.setData('bank_account_code', e.target.value)} className="w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm">
                                        {(bankAccounts || []).length === 0 && (
                                            <option value="">No bank/cash accounts — add one in Chart of Accounts</option>
                                        )}
                                        {(bankAccounts || []).map((a) => (
                                            <option key={a.value} value={a.value}>{a.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="flex gap-3 pt-4">
                                <button type="button" onClick={() => setShowPaymentModal(false)} className="flex-1 py-3 rounded-xl font-semibold text-ink border border-border-warm hover:bg-cream">
                                    Cancel
                                </button>
                                <button type="submit" disabled={paymentForm.processing} className="flex-[2] py-3 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50">
                                    {paymentForm.processing ? 'Processing...' : 'Confirm payment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

const IconX = ({ size = 20, ...props }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M18 6L6 18M6 6l12 12" />
    </svg>
);
