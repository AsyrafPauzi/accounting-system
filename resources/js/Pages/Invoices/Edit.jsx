import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    LockClosed: () => <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
};

const inputClass = "w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors";
const inputReadonlyClass = "w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-400 bg-slate-50";
const labelClass = "block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5";

export default function Edit({ auth, invoice, customers = [], lhdn_codes = [] }) {
    // Initialize form with existing invoice data and its nested items
    const { data, setData, put, processing, errors } = useForm({
        customer_id: invoice.customer_id || '',
        invoice_number: invoice.invoice_number || '',
        msic_code: invoice.msic_code || '62011',
        issue_date: invoice.issue_date || '',
        due_date: invoice.due_date || '',
        shipping_amount: parseFloat(invoice.shipping_amount || 0),
        customer_notes: invoice.customer_notes || '',
        // Map existing items from the database to the form state
        items: invoice.items && invoice.items.length > 0 
            ? invoice.items.map(item => ({
                description: item.description,
                quantity: parseFloat(item.quantity),
                unit_price: parseFloat(item.unit_price),
                tax_rate: parseFloat(item.tax_rate),
                discount_amount: parseFloat(item.discount_amount || 0),
                item_classification: item.item_classification || '011'
            })) 
            : [{ description: '', quantity: 1, unit_price: 0, tax_rate: 8, discount_amount: 0, item_classification: '011' }],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { description: '', quantity: 1, unit_price: 0, tax_rate: 8, discount_amount: 0, item_classification: '011' }
        ]);
    };

    const removeItem = (index) => {
        if (data.items.length > 1) {
            const newItems = data.items.filter((_, i) => i !== index);
            setData('items', newItems);
        }
    };

    const updateItem = (index, field, value) => {
        const newItems = [...data.items];
        newItems[index][field] = value;
        setData('items', newItems);
    };

    // --- Enterprise Math Engine ---
    const calculateSubtotal = () => {
        return data.items.reduce((sum, item) => sum + (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)), 0);
    };

    const calculateTotalDiscount = () => {
        return data.items.reduce((sum, item) => sum + (parseFloat(item.discount_amount || 0)), 0);
    };

    const calculateTax = () => {
        return data.items.reduce((sum, item) => {
            const lineAmount = (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - parseFloat(item.discount_amount || 0);
            return sum + (Math.max(0, lineAmount) * parseFloat(item.tax_rate || 0) / 100);
        }, 0);
    };

    const subtotal = calculateSubtotal();
    const totalDiscount = calculateTotalDiscount();
    const totalTax = calculateTax();
    const shipping = parseFloat(data.shipping_amount || 0);
    
    // Calculate Rounding (Malaysia 5-Sen Rule)
    const rawTotal = (subtotal - totalDiscount) + totalTax + shipping;
    const roundedTotal = (Math.round(rawTotal / 0.05) * 0.05);
    const roundingAdjustment = roundedTotal - rawTotal;

    const submit = (e) => {
        e.preventDefault();
        put(route('invoices.update', invoice.id));
    };

    const getStatusBadge = () => {
        const styles = {
            paid: 'bg-emerald-100 text-emerald-700',
            void: 'bg-slate-200 text-slate-500',
            draft: 'bg-slate-100 text-slate-600',
            unpaid: 'bg-rose-100 text-rose-700',
            'partially paid': 'bg-blue-100 text-blue-700',
        };
        return styles[invoice.status] || 'bg-slate-100 text-slate-600';
    };

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                (invoice.status === 'paid' || invoice.status === 'void') ? (
                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div className="flex items-start sm:items-center gap-4">
                            <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200">
                                <Icons.ChevronLeft />
                            </Link>
                            <div className="flex items-center gap-4">
                                <span className="p-2.5 rounded-xl bg-slate-100 text-slate-500"><Icons.Document /></span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">{invoice.invoice_number}</h2>
                                        <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase ${getStatusBadge()}`}>{invoice.status}</span>
                                    </div>
                                    <p className="text-slate-500 text-sm font-medium mt-1">Document locked — read only</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200">
                                <Icons.ArrowDownTray /> Download PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200">
                                Back to List
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div className="flex items-start sm:items-center gap-4">
                            <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200">
                                <Icons.ChevronLeft />
                            </Link>
                            <div className="flex items-center gap-4">
                                <span className="p-2.5 rounded-xl bg-blue-100 text-blue-600"><Icons.Document /></span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Edit Invoice</h2>
                                        <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase ${getStatusBadge()}`}>{invoice.status}</span>
                                    </div>
                                    <p className="text-slate-500 text-sm font-medium mt-1">{invoice.invoice_number} · {customers.find(c => c.id == invoice.customer_id)?.name || 'Customer'}</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200">
                                <Icons.ArrowDownTray /> PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200">
                                Cancel
                            </Link>
                            <button type="submit" form="invoice-edit-form" disabled={processing} className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25 transition-all duration-200">
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </div>
                )
            }
        >
            <Head title={`Edit ${invoice.invoice_number}`} />

            <div className="space-y-6 pb-12">
                {/* Locked state: Paid or Void */}
                {(invoice.status === 'paid' || invoice.status === 'void') ? (
                    <div className="bg-white p-12 rounded-2xl border border-slate-200/80 shadow-sm text-center space-y-6">
                        <div className="flex justify-center">
                            <span className="p-4 rounded-2xl bg-slate-100 text-slate-500">
                                <Icons.LockClosed />
                            </span>
                        </div>
                        <div>
                            <h3 className="text-xl font-bold text-slate-800 mb-2">Document Locked</h3>
                            <p className="text-slate-500 max-w-md mx-auto leading-relaxed text-sm">
                                This invoice is marked as <strong className="text-slate-700">{invoice.status}</strong>. 
                                To maintain audit integrity, finalized documents cannot be modified. 
                                Issue a <strong>Credit Note</strong> to reverse charges.
                            </p>
                        </div>
                        <div className="flex gap-3 justify-center">
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
                                <Icons.ArrowDownTray /> Download PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white bg-slate-900 hover:bg-slate-800 transition-colors">
                                Return to List
                            </Link>
                        </div>
                    </div>
                ) : (
                    <form id="invoice-edit-form" onSubmit={submit} className="space-y-6">
                        {/* Section 1: Core Details */}
                        <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                            <div className="flex items-center gap-2 mb-6">
                                <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Document /></span>
                                <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Invoice Details</h3>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div>
                                    <label className={labelClass}>Invoice Number</label>
                                    <div className={inputReadonlyClass}>{data.invoice_number}</div>
                                </div>
                                <div>
                                    <label className={labelClass}>MSIC Code</label>
                                    <input type="text" value={data.msic_code} onChange={e => setData('msic_code', e.target.value)} className={inputClass} />
                                    {errors.msic_code && <p className="text-rose-500 text-xs font-medium mt-1">{errors.msic_code}</p>}
                                </div>
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Customer</label>
                                    <select value={data.customer_id} onChange={e => setData('customer_id', e.target.value)} className={inputClass} required>
                                        <option value="">Select customer...</option>
                                        {customers.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                    {errors.customer_id && <p className="text-rose-500 text-xs font-medium mt-1">{errors.customer_id}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Issue Date</label>
                                    <input type="date" value={data.issue_date} onChange={e => setData('issue_date', e.target.value)} className={inputClass} required />
                                </div>
                                <div>
                                    <label className={labelClass}>Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className={inputClass} />
                                </div>
                            </div>
                        </div>

                        {/* Section 2: Line Items */}
                        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-slate-50/80 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        <th className="p-6">LHDN classification</th>
                                        <th className="p-6">Description</th>
                                        <th className="p-6 text-center w-24">Qty</th>
                                        <th className="p-6 w-32">Price (RM)</th>
                                        <th className="p-6 w-32">Disc (RM)</th>
                                        <th className="p-6 text-center w-24">Tax</th>
                                        <th className="p-6 text-right w-40">Total</th>
                                        <th className="p-6 w-16"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {data.items.map((item, index) => (
                                        <tr key={index} className="group hover:bg-blue-50/20 transition-all duration-200">
                                            <td className="p-4">
                                                <select 
                                                    value={item.item_classification} 
                                                    onChange={e => updateItem(index, 'item_classification', e.target.value)}
                                                    className="w-full border-slate-200 rounded-xl text-[10px] font-bold text-slate-500 focus:ring-blue-500 py-2"
                                                >
                                                    {lhdn_codes.map(code => (
                                                        <option key={code.id} value={code.id}>{code.id} - {code.name}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="p-4">
                                                <input 
                                                    type="text" 
                                                    value={item.description} 
                                                    onChange={e => updateItem(index, 'description', e.target.value)}
                                                    className="w-full border-none focus:ring-0 p-2 text-sm font-bold text-slate-700 bg-transparent"
                                                    required
                                                />
                                            </td>
                                            <td className="p-4">
                                                <input type="number" value={item.quantity} onChange={e => updateItem(index, 'quantity', e.target.value)} className="w-full border-slate-100 rounded-xl text-sm text-center py-2 focus:ring-blue-500 font-bold" />
                                            </td>
                                            <td className="p-4">
                                                <input type="number" value={item.unit_price} onChange={e => updateItem(index, 'unit_price', e.target.value)} className="w-full border-slate-100 rounded-xl text-sm py-2 focus:ring-blue-500 font-mono font-bold" />
                                            </td>
                                            <td className="p-4">
                                                <input type="number" value={item.discount_amount} onChange={e => updateItem(index, 'discount_amount', e.target.value)} className="w-full border-slate-100 rounded-xl text-sm py-2 focus:ring-rose-500 font-mono text-rose-500 font-bold" />
                                            </td>
                                            <td className="p-4">
                                                <select value={item.tax_rate} onChange={e => updateItem(index, 'tax_rate', e.target.value)} className="w-full border-slate-100 rounded-xl text-xs font-bold text-slate-600 focus:ring-blue-500 py-2">
                                                    <option value="0">0%</option>
                                                    <option value="6">6%</option>
                                                    <option value="8">8%</option>
                                                </select>
                                            </td>
                                            <td className="p-4 text-right">
                                                <div className="text-sm font-black text-slate-900 font-mono">
                                                    {((parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - (parseFloat(item.discount_amount || 0))).toLocaleString('en-MY', {minimumFractionDigits: 2})}
                                                </div>
                                            </td>
                                            <td className="p-4 text-center">
                                                <button type="button" onClick={() => removeItem(index)} className="text-slate-300 hover:text-rose-500 transition-colors opacity-0 group-hover:opacity-100">
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="p-6 bg-slate-50/80 border-t border-slate-200">
                                <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors">
                                    <Icons.Plus /> Add Line Item
                                </button>
                            </div>
                        </div>

                        {/* Section 3: Footer & Calculations */}
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div className="lg:col-span-2 space-y-6">
                                <div className="bg-amber-50 border border-amber-200/80 p-6 rounded-2xl shadow-sm">
                                    <h4 className="font-semibold text-amber-800 text-xs uppercase tracking-wider mb-2">Audit Notice</h4>
                                    <p className="text-amber-700 text-sm leading-relaxed">
                                        Draft edits won&apos;t affect the ledger. Posted invoices will <strong>auto-sync</strong> GL entries on save.
                                    </p>
                                </div>
                                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                                    <label className={labelClass}>Customer Notes (on PDF)</label>
                                    <textarea 
                                        value={data.customer_notes} 
                                        onChange={e => setData('customer_notes', e.target.value)}
                                        className={`${inputClass} resize-none h-28`}
                                        placeholder="Payment instructions, thank you message..."
                                    />
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                                    <div className="flex justify-between text-sm">
                                        <span className="font-bold text-slate-500 uppercase tracking-tighter">Subtotal (Gross)</span>
                                        <span className="font-mono font-bold text-slate-700">RM {subtotal.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="font-bold text-rose-400 uppercase tracking-tighter">Line Discounts</span>
                                        <span className="font-mono font-bold text-rose-500">- RM {totalDiscount.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="font-bold text-slate-400 uppercase tracking-tighter">SST (Tax)</span>
                                        <span className="font-mono font-bold text-slate-700">+ RM {totalTax.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                    </div>
                                    <div className="flex justify-between items-center py-2 border-t border-slate-50">
                                        <span className="font-bold text-slate-400 uppercase tracking-tighter">Shipping</span>
                                        <input 
                                            type="number" 
                                            value={data.shipping_amount} 
                                            onChange={e => setData('shipping_amount', e.target.value)}
                                            className="w-32 text-right border-slate-200 rounded-xl font-mono font-bold text-slate-700" 
                                        />
                                    </div>
                                    <div className="flex justify-between text-xs text-slate-400">
                                        <span>5-Sen Rounding</span>
                                        <span className="font-mono">{roundingAdjustment.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between items-center pt-4 border-t-2 border-slate-100">
                                        <span className="font-bold text-slate-800 uppercase tracking-tighter">Grand Total</span>
                                        <span className="text-2xl font-bold text-blue-600 font-mono tabular-nums">
                                            RM {roundedTotal.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}