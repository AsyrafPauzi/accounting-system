import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ReceiptUpload from '@/Components/ReceiptUpload';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
};

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, suppliers = [], expenseAccounts = [], bankAccounts = [], nextBillNumber = 'BILL-1', preselectedSupplierId = null }) {
    const today = new Date().toISOString().split('T')[0];
    const dueDefault = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        bill_number: nextBillNumber,
        purchase_kind: 'credit',
        supplier_id: preselectedSupplierId ? String(preselectedSupplierId) : '',
        bank_account_code: (bankAccounts && bankAccounts[0]?.value) || '',
        bill_date: today,
        due_date: dueDefault,
        tax_amount: 0,
        reference: '',
        private_notes: '',
        receipt_path: '',
        ocr_status: 'none',
        ocr_data: null,
        items: [
            { account_code: (expenseAccounts && expenseAccounts[0]?.value) || '', description: '', quantity: 1, unit_amount: 0, amount: 0 },
        ],
    });

    const handleOcrComplete = (ocrData, url, path) => {
        if (!ocrData) return;

        // Auto-populate form
        const updates = {
            receipt_path: path || url,
            ocr_status: 'completed',
            ocr_data: ocrData,
        };

        if (ocrData.bill_date) updates.bill_date = ocrData.bill_date;
        if (ocrData.reference) updates.reference = ocrData.reference;
        if (ocrData.tax_amount) updates.tax_amount = ocrData.tax_amount;

        // Try to match supplier if it's a mock or we have a name
        if (ocrData.supplier_name) {
            const supplier = suppliers.find(s => 
                s.name.toLowerCase().includes(ocrData.supplier_name.toLowerCase()) || 
                ocrData.supplier_name.toLowerCase().includes(s.name.toLowerCase())
            );
            if (supplier) {
                updates.supplier_id = String(supplier.id);
            }
        }

        // Handle items
        if (ocrData.items && ocrData.items.length > 0) {
            updates.items = ocrData.items.map(item => {
                const amount = parseFloat(item.amount) || 0;
                // Honor qty/unit if OCR provided them; otherwise default to 1 × amount.
                const quantity = parseFloat(item.quantity) > 0 ? parseFloat(item.quantity) : 1;
                const unit = parseFloat(item.unit_amount) > 0
                    ? parseFloat(item.unit_amount)
                    : (quantity > 0 ? Math.round((amount / quantity) * 100) / 100 : amount);
                return {
                    account_code: (expenseAccounts && expenseAccounts[0]?.value) || '',
                    description: item.description || '',
                    quantity,
                    unit_amount: unit,
                    amount,
                };
            });
        } else if (ocrData.total_amount) {
            // If no items but total amount, update the first item
            const newItems = [...data.items];
            newItems[0] = { 
                ...newItems[0], 
                amount: ocrData.total_amount,
                unit_amount: ocrData.total_amount,
                description: 'Extracted from receipt'
            };
            updates.items = newItems;
        }

        // Apply all updates
        Object.entries(updates).forEach(([key, value]) => {
            setData(key, value);
        });

        // Special case for mass update ( setData doesn't batch well in old Inertia, 
        // but let's assume it works or we use a manual object merge)
        setData(prev => ({ ...prev, ...updates }));
    };

    const addItem = () => {
        setData('items', [
            ...data.items,
            { account_code: (expenseAccounts && expenseAccounts[0]?.value) || '', description: '', quantity: 1, unit_amount: 0, amount: 0 },
        ]);
    };

    const removeItem = (index) => {
        if (data.items.length > 1) {
            setData('items', data.items.filter((_, i) => i !== index));
        }
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

    const submit = (e) => {
        e.preventDefault();
        post(route('bills.store'));
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
                            <h2 className="text-2xl font-display font-medium text-ink">
                                {data.purchase_kind === 'cash' ? 'Cash purchase' : data.purchase_kind === 'claim' ? 'Expense claim' : 'Create bill'}
                            </h2>
                            <p className="text-ink-muted text-sm">
                                {data.purchase_kind === 'cash' ? 'Paid immediately from bank or cash' : data.purchase_kind === 'claim' ? 'Staff or owner paid personally — reimburse later' : 'Add a new bill or expense'}
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Create bill" />

            <div className="max-w-4xl mx-auto">
                <form onSubmit={submit} className="space-y-8">
                <ReceiptUpload onOcrComplete={handleOcrComplete} />

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                        <h3 className="text-sm font-display font-medium text-ink">Bill details</h3>
                    </div>
                    <div className="p-6 space-y-4">
                        <div>
                            <label className={labelClass}>Type</label>
                            <div className="flex flex-wrap gap-2">
                                {[
                                    { id: 'credit', label: 'Credit purchase' },
                                    { id: 'cash', label: 'Cash purchase' },
                                    { id: 'claim', label: 'Expense claim' },
                                ].map((opt) => (
                                    <button
                                        key={opt.id}
                                        type="button"
                                        onClick={() => setData('purchase_kind', opt.id)}
                                        className={`px-4 py-2 rounded-xl text-sm font-semibold border ${data.purchase_kind === opt.id ? 'bg-terracotta text-white border-terracotta' : 'bg-surface border-border-warm text-ink hover:bg-cream'}`}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bill number</label>
                                <input type="text" value={data.bill_number} onChange={(e) => setData('bill_number', e.target.value)} className={inputClass} required />
                                {errors.bill_number && <p className="text-terracotta text-xs mt-1">{errors.bill_number}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>{data.purchase_kind === 'claim' ? 'Claimant' : 'Supplier'}{data.purchase_kind !== 'credit' ? ' *' : ' (optional)'}</label>
                                <select value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} className={inputClass} required={data.purchase_kind !== 'credit'}>
                                    <option value="">{data.purchase_kind === 'claim' ? '— Select claimant —' : '— No supplier —'}</option>
                                    {suppliers.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                                    ))}
                                </select>
                                {errors.supplier_id && <p className="text-terracotta text-xs mt-1">{errors.supplier_id}</p>}
                            </div>
                        </div>
                        {data.purchase_kind === 'cash' && (
                            <div>
                                <label className={labelClass}>Pay from *</label>
                                <select value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} className={inputClass} required>
                                    {(bankAccounts || []).map((a) => (
                                        <option key={a.value} value={a.value}>{a.label}</option>
                                    ))}
                                </select>
                                {errors.bank_account_code && <p className="text-terracotta text-xs mt-1">{errors.bank_account_code}</p>}
                            </div>
                        )}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bill date</label>
                                <input type="date" value={data.bill_date} onChange={(e) => setData('bill_date', e.target.value)} className={inputClass} required />
                                {errors.bill_date && <p className="text-terracotta text-xs mt-1">{errors.bill_date}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Due date</label>
                                <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className={inputClass} />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Vendor reference</label>
                                <input type="text" value={data.reference} onChange={(e) => setData('reference', e.target.value)} className={inputClass} placeholder="Vendor invoice #" />
                            </div>
                            <div>
                                <label className={labelClass}>Tax amount (RM)</label>
                                <input type="number" step="0.01" min="0" value={data.tax_amount} onChange={(e) => setData('tax_amount', e.target.value)} className={inputClass} />
                            </div>
                        </div>
                        <div>
                            <label className={labelClass}>Private notes</label>
                            <textarea value={data.private_notes} onChange={(e) => setData('private_notes', e.target.value)} className={inputClass} rows={2} />
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-4 sm:px-6 py-3 border-b border-border-warm bg-cream/50 flex items-center justify-between">
                        <h3 className="text-sm font-display font-medium text-ink">Line items</h3>
                        <button type="button" onClick={addItem} className="btn-app-secondary text-terracotta">
                            <Icons.Plus /> Add line
                        </button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full table-fixed min-w-[760px]">
                            <colgroup>
                                <col className="w-[28%]" />
                                <col className="w-[32%]" />
                                <col className="w-[10%]" />
                                <col className="w-[14%]" />
                                <col className="w-[14%]" />
                                <col className="w-[2%]" />
                            </colgroup>
                            <thead>
                                <tr className="text-left text-eyebrow font-semibold text-ink-muted uppercase border-b border-border-warm bg-cream/80">
                                    <th className="px-3 py-3">Account</th>
                                    <th className="px-3 py-3">Description</th>
                                    <th className="px-3 py-3">Qty</th>
                                    <th className="px-3 py-3">Unit amount</th>
                                    <th className="px-3 py-3 text-right">Amount</th>
                                    <th className="px-3 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.items.map((item, index) => (
                                    <tr key={index} className="border-b border-border-warm last:border-0">
                                        <td className="px-3 py-3">
                                            <select
                                                value={item.account_code}
                                                onChange={(e) => updateItem(index, 'account_code', e.target.value)}
                                                className={inputClass + ' w-full'}
                                                required
                                            >
                                                <option value="">Select account</option>
                                                {(expenseAccounts || []).map((a) => (
                                                    <option key={a.value} value={a.value}>{a.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-3 py-3">
                                            <input type="text" value={item.description} maxLength={255} onChange={(e) => updateItem(index, 'description', e.target.value)} className={inputClass + ' w-full'} placeholder="Description" />
                                            {errors[`items.${index}.description`] && <p className="mt-1 text-xs text-terracotta">{errors[`items.${index}.description`]}</p>}
                                        </td>
                                        <td className="px-3 py-3">
                                            <input type="number" step="0.01" min="0" value={item.quantity} onChange={(e) => updateItem(index, 'quantity', e.target.value)} className={inputClass + ' w-full'} />
                                        </td>
                                        <td className="px-3 py-3">
                                            <input type="number" step="0.01" min="0" value={item.unit_amount} onChange={(e) => updateItem(index, 'unit_amount', e.target.value)} className={inputClass + ' w-full'} />
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <input type="number" step="0.01" min="0" value={item.amount} onChange={(e) => updateItem(index, 'amount', parseFloat(e.target.value) || 0)} className={inputClass + ' w-full text-right'} />
                                        </td>
                                        <td className="px-2 py-3">
                                            <button type="button" onClick={() => removeItem(index)} className="p-2 text-ink-muted hover:text-terracotta rounded-lg" title="Remove line">
                                                <Icons.Trash />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {errors.items && <p className="text-terracotta text-xs px-6 py-2">{errors.items}</p>}
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
                        </div>
                    </div>
                </div>

                <div className="flex gap-2">
                    <Link href={route('bills.index')} className="btn-app-secondary">
                        Cancel
                    </Link>
                    <button type="submit" disabled={processing} className="btn-app-primary">
                        {processing ? 'Saving…' : data.purchase_kind === 'cash' ? 'Save and pay' : data.purchase_kind === 'claim' ? 'Save claim' : 'Save as draft'}
                    </button>
                </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
