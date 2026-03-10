import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = "w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors";
const inputReadonlyClass = "w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-400 bg-slate-50";
const labelClass = "block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5";

export default function Create({ auth, invoice, lhdn_reasons }) {
    const { data, setData, post, processing, errors } = useForm({
        invoice_id: invoice.id,
        customer_id: invoice.customer_id,
        cn_number: 'CN-' + Date.now(),
        reason_code: '',
        reason_description: '',
        items: (invoice.items || []).map(item => ({
            description: item.description,
            quantity: item.quantity,
            unit_price: item.unit_price,
            tax_rate: item.tax_rate,
            amount: item.amount,
        }))
    });

    const updateItemAmount = (index, value) => {
        const newItems = [...data.items];
        newItems[index].amount = value;
        setData('items', newItems);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('credit-notes.store'));
    };

    const totalCN = data.items.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link 
                            href={route('invoices.edit', invoice.id)} 
                            className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200"
                        >
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-amber-100 text-amber-600">
                                <Icons.Document />
                            </span>
                            <div>
                                <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Issue Credit Note</h2>
                                <p className="text-slate-500 text-sm font-medium mt-1">
                                    Against {invoice.invoice_number} · {invoice.customer?.name || 'Customer'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link 
                            href={route('invoices.edit', invoice.id)} 
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all duration-200"
                        >
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            form="credit-note-create-form"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50 shadow-lg shadow-amber-500/25 transition-all duration-200"
                        >
                            {processing ? 'Issuing...' : 'Issue Credit Note'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Issue Credit Note" />
            <form id="credit-note-create-form" onSubmit={submit} className="space-y-6 max-w-4xl">
                {/* Reference Info */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                        <label className={labelClass}>Original Invoice</label>
                        <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 font-medium text-slate-700">
                            {invoice.invoice_number} · RM {parseFloat(invoice.total_amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                        <label className={labelClass}>Credit Note #</label>
                        <input type="text" value={data.cn_number} className={inputReadonlyClass} readOnly />
                    </div>
                </div>

                {/* Reason */}
                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <label className={labelClass}>LHDN Reason for Credit</label>
                    <select 
                        value={data.reason_code} 
                        onChange={e => setData('reason_code', e.target.value)}
                        className={inputClass}
                        required
                    >
                        <option value="">Select reason...</option>
                        {lhdn_reasons.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                    {errors.reason_code && <p className="text-rose-500 text-xs font-medium mt-1">{errors.reason_code}</p>}
                </div>

                {/* Items */}
                <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Items to Credit</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Description</th>
                                    <th className="px-6 py-4 text-right">Amount (RM)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.items.map((item, i) => (
                                    <tr key={i} className="border-b border-slate-50 last:border-0">
                                        <td className="px-6 py-4 text-sm font-medium text-slate-700">{item.description}</td>
                                        <td className="px-6 py-4 text-right">
                                            <input 
                                                type="number" 
                                                value={item.amount} 
                                                onChange={e => updateItemAmount(i, e.target.value)}
                                                className="w-28 text-right border border-slate-200 rounded-lg py-2 px-3 text-sm font-semibold text-rose-600 focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                                                step="0.01"
                                                min="0"
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Total Summary */}
                <div className="bg-rose-50 p-6 rounded-2xl border border-rose-200/80 shadow-sm flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div>
                        <p className="text-[10px] font-semibold text-rose-500 uppercase tracking-wider mb-1">Total to Credit</p>
                        <p className="text-2xl font-bold text-rose-600 font-mono tabular-nums">
                            RM {totalCN.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                        </p>
                    </div>
                    <p className="text-xs text-rose-600/80 font-medium max-w-xs">
                        This amount will be deducted from the customer&apos;s balance.
                    </p>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}