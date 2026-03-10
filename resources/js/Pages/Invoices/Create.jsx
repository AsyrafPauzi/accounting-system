import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
};

const inputClass = "w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors";
const inputReadonlyClass = "w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-400 bg-slate-50";
const labelClass = "block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5";

const initialQuickCustomer = { name: '', code: '', email: '', tin: '', brn: '', billing_street: '', billing_city: '', billing_state: '', billing_zip: '' };

export default function Create({ auth, customers = [], lhdn_codes = [], customer_id: preselectedCustomerId = null, next_invoice_number: suggestedInvoiceNumber = null }) {
    const [showNewCustomerModal, setShowNewCustomerModal] = useState(false);
    const [newCustomers, setNewCustomers] = useState([]);
    const [quickCustomer, setQuickCustomer] = useState(initialQuickCustomer);
    const [quickCustomerErrors, setQuickCustomerErrors] = useState({});
    const [quickSubmitting, setQuickSubmitting] = useState(false);

    const customerOptions = [...customers, ...newCustomers];

    const { data, setData, post, processing, errors } = useForm({
        invoice_number: suggestedInvoiceNumber || 'INV-' + Date.now(),
        customer_id: preselectedCustomerId ? String(preselectedCustomerId) : '',
        msic_code: '62011', // Default MSIC for Tech/Programming
        issue_date: new Date().toISOString().split('T')[0],
        due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // Default Net 30
        shipping_amount: 0,
        customer_notes: '',
        items: [
            { 
                description: '', 
                quantity: 1, 
                unit_price: 0, 
                tax_rate: 8, 
                discount_amount: 0,
                item_classification: '011'
            }
        ],
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
        post(route('invoices.store'));
    };

    const submitQuickCustomer = async (e) => {
        e.preventDefault();
        setQuickCustomerErrors({});
        setQuickSubmitting(true);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const payload = {
            name: quickCustomer.name,
            email: quickCustomer.email,
            tin: quickCustomer.tin,
            brn: quickCustomer.brn,
            ...(quickCustomer.code && { code: quickCustomer.code }),
            ...(quickCustomer.billing_street && { billing_street: quickCustomer.billing_street }),
            ...(quickCustomer.billing_city && { billing_city: quickCustomer.billing_city }),
            ...(quickCustomer.billing_state && { billing_state: quickCustomer.billing_state }),
            ...(quickCustomer.billing_zip && { billing_zip: quickCustomer.billing_zip }),
        };
        try {
            const res = await fetch(route('customers.quick-store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok) {
                const customer = json.customer;
                setNewCustomers(prev => [...prev, customer]);
                setData('customer_id', String(customer.id));
                setShowNewCustomerModal(false);
                setQuickCustomer(initialQuickCustomer);
                setQuickCustomerErrors({});
            } else if (res.status === 422 && json.errors) {
                setQuickCustomerErrors(json.errors);
            } else {
                setQuickCustomerErrors({ form: json.message || 'Could not create customer.' });
            }
        } catch (err) {
            setQuickCustomerErrors({ form: 'Network error. Please try again.' });
        } finally {
            setQuickSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-blue-100 text-blue-600"><Icons.Document /></span>
                            <div>
                                <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">New Invoice</h2>
                                <p className="text-slate-500 text-sm font-medium mt-1">LHDN compliant · 5-sen rounding</p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200">
                            Cancel
                        </Link>
                        <button type="submit" form="invoice-create-form" disabled={processing} className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25 transition-all duration-200">
                            {processing ? 'Saving...' : 'Create Invoice'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="New Invoice" />

            <form id="invoice-create-form" onSubmit={submit} className="space-y-6 pb-12">
                {/* Section 1: Core Details */}
                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Document /></span>
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Invoice Details</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label className={labelClass}>Invoice Number</label>
                            <div className={`${inputReadonlyClass} font-mono text-blue-600`}>{data.invoice_number}</div>
                        </div>
                        <div>
                            <label className={labelClass}>MSIC Code</label>
                            <input type="text" value={data.msic_code} onChange={e => setData('msic_code', e.target.value)} className={inputClass} />
                        </div>
                        <div className="md:col-span-2">
                            <label className={labelClass}>Customer</label>
                            <div className="flex gap-2">
                                <select value={data.customer_id} onChange={e => setData('customer_id', e.target.value)} className={inputClass} required>
                                    <option value="">Select customer...</option>
                                    {customerOptions.map(c => <option key={c.id} value={c.id}>{c.name}{c.tin ? ` (${c.tin})` : ''}</option>)}
                                </select>
                                <button type="button" onClick={() => setShowNewCustomerModal(true)} className="shrink-0 inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-semibold text-sm text-blue-600 bg-blue-50 border border-blue-200 hover:bg-blue-100 transition-colors">
                                    <Icons.Plus className="w-4 h-4" /> New customer
                                </button>
                            </div>
                            {errors.customer_id && <p className="text-rose-500 text-xs font-medium mt-1">{errors.customer_id}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Issue Date</label>
                            <input type="date" value={data.issue_date} onChange={e => setData('issue_date', e.target.value)} className={inputClass} />
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
                                                placeholder="What are you selling?"
                                                className="w-full border-none focus:ring-0 p-2 text-sm font-bold text-slate-700 bg-transparent placeholder-slate-300"
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
                        <div className="bg-blue-50 border border-blue-200/80 p-6 rounded-2xl shadow-sm">
                            <h4 className="font-semibold text-blue-800 text-xs uppercase tracking-wider mb-2">Draft Mode</h4>
                            <p className="text-blue-700 text-sm leading-relaxed">
                                Invoice saves as <strong>Draft</strong>. Ledger entries post only after you validate and post.
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
                                    <span className="font-bold text-slate-400 uppercase tracking-tighter">SST (Service Tax)</span>
                                    <span className="font-mono font-bold text-slate-700">+ RM {totalTax.toLocaleString('en-MY', {minimumFractionDigits: 2})}</span>
                                </div>
                                <div className="flex justify-between items-center py-2 border-t border-slate-50">
                                    <span className="font-bold text-slate-400 uppercase tracking-tighter">Shipping/Handling</span>
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

            {/* New customer modal (quick-create from invoice) */}
            {showNewCustomerModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" onClick={() => !quickSubmitting && setShowNewCustomerModal(false)}>
                    <div className="bg-white rounded-2xl shadow-xl border border-slate-200/80 w-full max-w-lg max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                        <div className="p-6 border-b border-slate-200">
                            <h3 className="text-lg font-bold text-slate-900">New customer</h3>
                            <p className="text-sm text-slate-500 mt-0.5">Add a customer to use on this invoice. Required fields: Name, Email, TIN, BRN.</p>
                        </div>
                        <form onSubmit={submitQuickCustomer} className="p-6 space-y-4">
                            {quickCustomerErrors.form && (
                                <div className="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm">{quickCustomerErrors.form}</div>
                            )}
                            <div>
                                <label className={labelClass}>Name *</label>
                                <input type="text" value={quickCustomer.name} onChange={e => setQuickCustomer(c => ({ ...c, name: e.target.value }))} className={inputClass} required />
                                {quickCustomerErrors.name && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.name[0]}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Code (optional)</label>
                                <input type="text" value={quickCustomer.code} onChange={e => setQuickCustomer(c => ({ ...c, code: e.target.value }))} className={inputClass} placeholder="Auto-generated if blank" />
                                {quickCustomerErrors.code && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.code[0]}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Email *</label>
                                <input type="email" value={quickCustomer.email} onChange={e => setQuickCustomer(c => ({ ...c, email: e.target.value }))} className={inputClass} required />
                                {quickCustomerErrors.email && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.email[0]}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={labelClass}>TIN *</label>
                                    <input type="text" value={quickCustomer.tin} onChange={e => setQuickCustomer(c => ({ ...c, tin: e.target.value }))} className={inputClass} required />
                                    {quickCustomerErrors.tin && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.tin[0]}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>BRN *</label>
                                    <input type="text" value={quickCustomer.brn} onChange={e => setQuickCustomer(c => ({ ...c, brn: e.target.value }))} className={inputClass} required />
                                    {quickCustomerErrors.brn && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.brn[0]}</p>}
                                </div>
                            </div>
                            <div>
                                <label className={labelClass}>Billing street (optional)</label>
                                <input type="text" value={quickCustomer.billing_street} onChange={e => setQuickCustomer(c => ({ ...c, billing_street: e.target.value }))} className={inputClass} />
                                {quickCustomerErrors.billing_street && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.billing_street[0]}</p>}
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className={labelClass}>City</label>
                                    <input type="text" value={quickCustomer.billing_city} onChange={e => setQuickCustomer(c => ({ ...c, billing_city: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.billing_city && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.billing_city[0]}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>State</label>
                                    <input type="text" value={quickCustomer.billing_state} onChange={e => setQuickCustomer(c => ({ ...c, billing_state: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.billing_state && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.billing_state[0]}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Zip</label>
                                    <input type="text" value={quickCustomer.billing_zip} onChange={e => setQuickCustomer(c => ({ ...c, billing_zip: e.target.value }))} className={inputClass} />
                                    {quickCustomerErrors.billing_zip && <p className="text-rose-500 text-xs mt-1">{quickCustomerErrors.billing_zip[0]}</p>}
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
                                <button type="button" onClick={() => !quickSubmitting && (setShowNewCustomerModal(false), setQuickCustomer(initialQuickCustomer), setQuickCustomerErrors({}))} className="px-4 py-2.5 rounded-xl font-semibold text-slate-600 hover:bg-slate-100">
                                    Cancel
                                </button>
                                <button type="submit" disabled={quickSubmitting} className="px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                                    {quickSubmitting ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}