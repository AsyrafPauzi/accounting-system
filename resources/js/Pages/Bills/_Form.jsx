import React, { useState } from 'react';
import ReceiptUpload from '@/Components/ReceiptUpload';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';
import DocumentFormNotesTotals, { computeDocTotals } from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    ),
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

const KINDS = [
    { id: 'credit', label: 'Credit', hint: 'Pay later' },
    { id: 'cash', label: 'Cash', hint: 'Paid now' },
    { id: 'claim', label: 'Claim', hint: 'Reimburse' },
];

export const KIND_COPY = {
    credit: {
        title: 'New bill',
        subtitle: 'Supplier invoice on credit — post when you are ready',
        submit: 'Save as draft',
        bannerTitle: 'Draft until you post',
        bannerText: 'Saving keeps this as a draft. Post from the bill page when you want it on the ledger.',
    },
    cash: {
        title: 'Cash purchase',
        subtitle: 'Paid immediately from bank or cash',
        submit: 'Save and pay',
        bannerTitle: 'Paid on save',
        bannerText: 'Saving posts the purchase and records the payment from the account you pick.',
    },
    claim: {
        title: 'Expense claim',
        subtitle: 'Staff or owner paid personally — reimburse later',
        submit: 'Save claim',
        bannerTitle: 'Reimburse later',
        bannerText: 'Save as a draft claim, then record the reimbursement from the claim page.',
    },
};

export function defaultAccountCode(expenseAccounts = []) {
    return expenseAccounts[0]?.code || expenseAccounts[0]?.value || '5000';
}

export function normalizeExpenseAccounts(expenseAccounts = []) {
    return expenseAccounts.map((a) => ({
        code: a.code || a.value,
        name: a.name || a.label || a.code || a.value,
    }));
}

export function itemsFromBill(items, accountCode) {
    if (!items?.length) return [blankPurchaseLine(accountCode)];
    return items.map((item) => ({
        id: item.id,
        account_code: item.account_code || accountCode,
        description: item.description || '',
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_amount ?? item.unit_price) || 0,
        tax_rate: parseFloat(item.tax_rate) || 0,
        tax_code_id: item.tax_code_id ?? null,
        discount_amount: 0,
        product_id: item.product_id || null,
    }));
}

export function toBillPayload(data) {
    const totals = computeDocTotals(data.items);
    const taxAmount = totals.tax > 0 ? totals.tax : (Number(data.tax_amount) || 0);
    return {
        ...data,
        tax_amount: taxAmount,
        items: data.items.map((item) => {
            const unit = Number(item.unit_price ?? item.unit_amount) || 0;
            const qty = Number(item.quantity) || 0;
            const disc = Number(item.discount_amount) || 0;
            return {
                id: item.id,
                account_code: item.account_code,
                description: item.description,
                quantity: item.quantity,
                unit_amount: unit,
                amount: Math.round((qty * unit - disc) * 100) / 100,
                tax_code_id: item.tax_code_id ?? null,
                tax_rate: Number(item.tax_rate) || 0,
            };
        }),
    };
}

function snapOcrItems(ocrItems, accountCode) {
    return ocrItems.map((item) => {
        const amount = parseFloat(item.amount) || 0;
        const quantity = parseFloat(item.quantity) > 0 ? parseFloat(item.quantity) : 1;
        const unit = parseFloat(item.unit_amount) > 0
            ? parseFloat(item.unit_amount)
            : (quantity > 0 ? Math.round((amount / quantity) * 100) / 100 : amount);
        return {
            ...blankPurchaseLine(accountCode),
            description: item.description || '',
            quantity,
            unit_price: unit,
        };
    });
}

export default function BillForm({
    formId = 'bill-form',
    data,
    setData,
    errors = {},
    onSubmit,
    suppliers = [],
    expenseAccounts = [],
    bankAccounts = [],
    products = [],
    taxCodes = [],
    showKind = true,
    disabled = false,
    receiptUrl = null,
    receiptIsPdf = false,
    billId = null,
    onViewReceipt,
    onReceiptUploaded,
}) {
    const accounts = normalizeExpenseAccounts(expenseAccounts);
    const accountCode = defaultAccountCode(accounts);
    const kind = data.purchase_kind || 'credit';
    const copy = KIND_COPY[kind] || KIND_COPY.credit;
    const supplierLabel = kind === 'claim' ? 'Claimant' : 'Supplier';
    const supplierRequired = kind !== 'credit';
    const [previewUrl, setPreviewUrl] = useState(receiptUrl);
    const [previewPdf, setPreviewPdf] = useState(receiptIsPdf);
    const shownUrl = previewUrl || receiptUrl;
    const shownPdf = shownUrl ? (/\.pdf($|\?)/i.test(shownUrl) || previewPdf) : false;

    const handleOcrComplete = (ocrData, url, path) => {
        if (onReceiptUploaded) onReceiptUploaded(ocrData, url, path);
        if (url) {
            setPreviewUrl(url);
            setPreviewPdf(/\.(pdf)($|\?)/i.test(url));
        }
        if (!ocrData && !path) return;

        setData((prev) => {
            const updates = {
                ...prev,
                receipt_path: path || url || prev.receipt_path,
                ocr_status: ocrData ? 'completed' : prev.ocr_status,
                ocr_data: ocrData || prev.ocr_data,
            };
            if (!ocrData) return updates;

            if (ocrData.bill_date) updates.bill_date = ocrData.bill_date;
            if (ocrData.reference) updates.reference = ocrData.reference;
            if (ocrData.tax_amount != null) updates.tax_amount = ocrData.tax_amount;

            if (ocrData.supplier_name) {
                const supplier = suppliers.find((s) =>
                    s.name.toLowerCase().includes(ocrData.supplier_name.toLowerCase())
                    || ocrData.supplier_name.toLowerCase().includes(s.name.toLowerCase()),
                );
                if (supplier) updates.supplier_id = String(supplier.id);
            }

            if (ocrData.items?.length > 0) {
                updates.items = snapOcrItems(ocrData.items, accountCode);
            } else if (ocrData.total_amount) {
                const tax = parseFloat(ocrData.tax_amount) || 0;
                const net = Math.max(0, parseFloat(ocrData.total_amount) - tax);
                updates.items = [{
                    ...blankPurchaseLine(accountCode),
                    description: 'Extracted from receipt',
                    quantity: 1,
                    unit_price: net,
                }];
            }

            return updates;
        });
    };

    return (
        <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
            <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-4 sm:p-5">
                <div className="flex items-center justify-between gap-3 mb-3">
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Receipt</p>
                        <p className="text-sm text-ink-muted">Optional — drop a file to fill dates, supplier, and lines</p>
                    </div>
                    {shownUrl && onViewReceipt && (
                        <button type="button" onClick={onViewReceipt} className="text-xs font-semibold text-terracotta hover:underline shrink-0">
                            View full size
                        </button>
                    )}
                </div>
                {shownUrl && (
                    shownPdf ? (
                        <div className="mb-3 rounded-xl overflow-hidden border border-border-warm bg-cream">
                            <iframe src={`${shownUrl}#view=FitH&toolbar=0`} title="Receipt PDF" className="w-full h-40 bg-cream" />
                        </div>
                    ) : (
                        <div className="mb-3 rounded-xl overflow-hidden border border-border-warm bg-cream max-h-40 w-full flex items-center justify-center">
                            <img src={shownUrl} alt="Receipt" className="max-h-40 object-contain" onError={(e) => { e.currentTarget.style.display = 'none'; }} />
                        </div>
                    )
                )}
                <ReceiptUpload compact billId={billId} onOcrComplete={handleOcrComplete} />
            </div>

            <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                    <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                    <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Bill details</h3>
                </div>

                {showKind && (
                    <div className="mb-6">
                        <label className={labelClass}>Type</label>
                        <div className="grid grid-cols-3 gap-2">
                            {KINDS.map((opt) => {
                                const on = kind === opt.id;
                                return (
                                    <button
                                        key={opt.id}
                                        type="button"
                                        disabled={disabled}
                                        onClick={() => setData('purchase_kind', opt.id)}
                                        className={`rounded-xl border px-3 py-2.5 text-left transition-colors ${on ? 'bg-terracotta text-white border-terracotta' : 'bg-surface border-border-warm text-ink hover:bg-cream'}`}
                                    >
                                        <span className="block text-sm font-semibold">{opt.label}</span>
                                        <span className={`block text-[11px] ${on ? 'text-white/80' : 'text-ink-muted'}`}>{opt.hint}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <div className="min-w-0">
                        <label className={labelClass}>Bill number</label>
                        <input type="text" value={data.bill_number} onChange={(e) => setData('bill_number', e.target.value)} className={`${inputClass} font-mono text-terracotta`} required disabled={disabled} />
                        {errors.bill_number && <p className="text-terracotta text-xs font-medium mt-1">{errors.bill_number}</p>}
                    </div>
                    <div className="md:col-span-2 min-w-0">
                        <label className={labelClass}>
                            {supplierLabel}
                            {supplierRequired ? '' : ' (optional)'}
                        </label>
                        <select
                            value={data.supplier_id}
                            onChange={(e) => setData('supplier_id', e.target.value)}
                            className={inputClass}
                            required={supplierRequired}
                            disabled={disabled}
                        >
                            <option value="">{kind === 'claim' ? 'Select claimant…' : 'No supplier'}</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}{s.code ? ` (${s.code})` : ''}</option>
                            ))}
                        </select>
                        {errors.supplier_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.supplier_id}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Bill date</label>
                        <input type="date" value={data.bill_date} onChange={(e) => setData('bill_date', e.target.value)} className={inputClass} required disabled={disabled} />
                        {errors.bill_date && <p className="text-terracotta text-xs font-medium mt-1">{errors.bill_date}</p>}
                    </div>
                    {kind !== 'cash' && (
                        <div className="min-w-0">
                            <label className={labelClass}>Due date</label>
                            <input type="date" value={data.due_date || ''} onChange={(e) => setData('due_date', e.target.value)} className={inputClass} disabled={disabled} />
                        </div>
                    )}
                    {kind === 'cash' && (
                        <div className="md:col-span-2 min-w-0">
                            <label className={labelClass}>Pay from</label>
                            <select value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} className={inputClass} required disabled={disabled}>
                                {(bankAccounts || []).map((a) => (
                                    <option key={a.value || a.code} value={a.value || a.code}>{a.label || `${a.name} (${a.code})`}</option>
                                ))}
                            </select>
                            {errors.bank_account_code && <p className="text-terracotta text-xs font-medium mt-1">{errors.bank_account_code}</p>}
                        </div>
                    )}
                    <div className="md:col-span-2 min-w-0">
                        <label className={labelClass}>Vendor reference</label>
                        <input type="text" value={data.reference || ''} onChange={(e) => setData('reference', e.target.value)} className={inputClass} placeholder="Vendor invoice #" disabled={disabled} />
                    </div>
                </div>
            </div>

            <PurchasesDocLines
                items={data.items}
                onChange={(items) => setData('items', items)}
                products={products}
                expenseAccounts={accounts}
                taxCodes={taxCodes}
                disabled={disabled}
            />
            {errors.items && <p className="text-xs text-terracotta">{errors.items}</p>}

            <DocumentFormNotesTotals
                bannerTitle={copy.bannerTitle}
                bannerText={copy.bannerText}
                notesLabel="Private notes"
                notesValue={data.private_notes || ''}
                onNotesChange={(value) => setData('private_notes', value)}
                notesPlaceholder="Not printed — for your team only"
                notesDisabled={disabled}
                items={data.items}
            />
        </form>
    );
}
