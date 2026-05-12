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

const inputClass = 'w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors';
const labelClass = 'block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5';

export default function Create({ auth, suppliers = [], expenseAccounts = [], nextBillNumber = 'BILL-1', preselectedSupplierId = null }) {
    const today = new Date().toISOString().split('T')[0];
    const dueDefault = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        bill_number: nextBillNumber,
        supplier_id: preselectedSupplierId ? String(preselectedSupplierId) : '',
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
            updates.items = ocrData.items.map(item => ({
                account_code: (expenseAccounts && expenseAccounts[0]?.value) || '',
                description: item.description || '',
                quantity: 1,
                unit_amount: item.amount || 0,
                amount: item.amount || 0,
            }));
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
                        <Link href={route('bills.index')} className="p-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">
                            <Icons.ChevronLeft />
                        </Link>
                        <div>
                            <h2 className="text-2xl font-bold text-slate-900">Create bill</h2>
                            <p className="text-slate-500 text-sm">Add a new bill or expense</p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Create bill" />

            <div className="max-w-4xl mx-auto">
                <form onSubmit={submit} className="space-y-8">
                <ReceiptUpload onOcrComplete={handleOcrComplete} />

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <h3 className="text-sm font-bold text-slate-800">Bill details</h3>
                    </div>
                    <div className="p-6 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bill number</label>
                                <input type="text" value={data.bill_number} onChange={(e) => setData('bill_number', e.target.value)} className={inputClass} required />
                                {errors.bill_number && <p className="text-rose-500 text-xs mt-1">{errors.bill_number}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Supplier (optional)</label>
                                <select value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} className={inputClass}>
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
                                <input type="date" value={data.bill_date} onChange={(e) => setData('bill_date', e.target.value)} className={inputClass} required />
                                {errors.bill_date && <p className="text-rose-500 text-xs mt-1">{errors.bill_date}</p>}
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

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-slate-800">Line items</h3>
                        <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100">
                            <Icons.Plus /> Add line
                        </button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-3">Account</th>
                                    <th className="px-6 py-3">Description</th>
                                    <th className="px-6 py-3 w-32">Qty</th>
                                    <th className="px-6 py-3 w-32">Unit amount</th>
                                    <th className="px-6 py-3 w-32 text-right">Amount</th>
                                    <th className="px-6 py-3 w-12" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.items.map((item, index) => (
                                    <tr key={index} className="border-b border-slate-50 last:border-0">
                                        <td className="px-6 py-3">
                                            <select
                                                value={item.account_code}
                                                onChange={(e) => updateItem(index, 'account_code', e.target.value)}
                                                className={inputClass}
                                                required
                                            >
                                                <option value="">Select account</option>
                                                {(expenseAccounts || []).map((a) => (
                                                    <option key={a.value} value={a.value}>{a.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-6 py-3">
                                            <input type="text" value={item.description} onChange={(e) => updateItem(index, 'description', e.target.value)} className={inputClass} placeholder="Description" />
                                        </td>
                                        <td className="px-6 py-3">
                                            <input type="number" step="0.01" min="0" value={item.quantity} onChange={(e) => updateItem(index, 'quantity', e.target.value)} className={inputClass} />
                                        </td>
                                        <td className="px-6 py-3">
                                            <input type="number" step="0.01" min="0" value={item.unit_amount} onChange={(e) => updateItem(index, 'unit_amount', e.target.value)} className={inputClass} />
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            <input type="number" step="0.01" min="0" value={item.amount} onChange={(e) => updateItem(index, 'amount', parseFloat(e.target.value) || 0)} className={inputClass + ' text-right'} />
                                        </td>
                                        <td className="px-6 py-3">
                                            <button type="button" onClick={() => removeItem(index)} className="p-2 text-slate-400 hover:text-rose-600 rounded-lg" title="Remove line">
                                                <Icons.Trash />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {errors.items && <p className="text-rose-500 text-xs px-6 py-2">{errors.items}</p>}
                    <div className="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-6">
                        <span className="text-sm font-medium text-slate-600">Subtotal: RM {subtotal.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                        {tax > 0 && <span className="text-sm font-medium text-slate-600">Tax: RM {tax.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>}
                        <span className="text-lg font-bold text-slate-800">Total: RM {total.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                    </div>
                </div>

                <div className="flex gap-3">
                    <Link href={route('bills.index')} className="px-5 py-2.5 rounded-xl font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button type="submit" disabled={processing} className="px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25">
                        {processing ? 'Saving...' : 'Save as draft'}
                    </button>
                </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
