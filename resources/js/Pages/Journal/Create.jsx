import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Journal: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
};

const inputClass = "w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors";
const labelClass = "block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5";

export default function Create({ auth, accounts }) {
    const { data, setData, post, processing, errors } = useForm({
        date: new Date().toISOString().split('T')[0],
        description: '',
        reference_number: 'JNL-' + Date.now().toString().slice(-6),
        items: [
            { account_id: '', debit: 0, credit: 0, description: '' },
            { account_id: '', debit: 0, credit: 0, description: '' },
        ],
    });

    const [totals, setTotals] = useState({ debit: 0, credit: 0, difference: 0 });

    useEffect(() => {
        const debit = data.items.reduce((sum, item) => sum + parseFloat(item.debit || 0), 0);
        const credit = data.items.reduce((sum, item) => sum + parseFloat(item.credit || 0), 0);
        setTotals({
            debit,
            credit,
            difference: Math.abs(debit - credit)
        });
    }, [data.items]);

    const addItem = () => {
        setData('items', [
            ...data.items,
            { account_id: '', debit: 0, credit: 0, description: '' }
        ]);
    };

    const removeItem = (index) => {
        if (data.items.length > 2) {
            const newItems = data.items.filter((_, i) => i !== index);
            setData('items', newItems);
        }
    };

    const updateItem = (index, field, value) => {
        const newItems = [...data.items];
        
        // If updating debit, zero out credit and vice versa (typical manual journal behavior)
        if (field === 'debit' && value > 0) {
            newItems[index]['credit'] = 0;
        } else if (field === 'credit' && value > 0) {
            newItems[index]['debit'] = 0;
        }

        newItems[index][field] = value;
        setData('items', newItems);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('journal.store'));
    };

    const isBalanced = totals.difference < 0.001 && (totals.debit > 0 || totals.credit > 0);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link href={route('journal.index')} className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta"><Icons.Journal /></span>
                            <div>
                                <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">New Journal Entry</h2>
                                <p className="text-ink-muted text-sm font-medium mt-1">Record a manual double-entry transaction</p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('journal.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            form="journal-create-form" 
                            disabled={processing || !isBalanced} 
                            className={`inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white transition-all duration-200 shadow-lg ${
                                isBalanced 
                                    ? 'bg-terracotta hover:bg-terracotta ' 
                                    : 'bg-ink cursor-not-allowed shadow-none'
                            }`}
                        >
                            {processing ? 'Saving...' : 'Save Journal'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="New Journal Entry" />

            <form id="journal-create-form" onSubmit={submit} className="space-y-6 pb-12">
                <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="md:col-span-2">
                            <label className={labelClass}>General Description</label>
                            <input 
                                type="text" 
                                value={data.description} 
                                onChange={e => setData('description', e.target.value)} 
                                className={inputClass} 
                                placeholder="E.g., Monthly depreciation, Year-end adjustments..."
                                required 
                            />
                            {errors.description && <p className="text-terracotta text-xs font-medium mt-1">{errors.description}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Date</label>
                                <input 
                                    type="date" 
                                    value={data.date} 
                                    onChange={e => setData('date', e.target.value)} 
                                    className={inputClass} 
                                    required 
                                />
                                {errors.date && <p className="text-terracotta text-xs font-medium mt-1">{errors.date}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Reference</label>
                                <input 
                                    type="text" 
                                    value={data.reference_number} 
                                    onChange={e => setData('reference_number', e.target.value)} 
                                    className={inputClass} 
                                />
                                {errors.reference_number && <p className="text-terracotta text-xs font-medium mt-1">{errors.reference_number}</p>}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-surface rounded-2xl shadow-sm border border-border-warm/80 overflow-hidden">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-cream/80 border-b border-border-warm text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest">
                                <th className="p-6">Account</th>
                                <th className="p-6">Description (Line)</th>
                                <th className="p-6 w-40 text-right">Debit (RM)</th>
                                <th className="p-6 w-40 text-right">Credit (RM)</th>
                                <th className="p-6 w-16"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-warm">
                            {data.items.map((item, index) => (
                                <tr key={index} className="group hover:bg-cream/50 transition-colors duration-200">
                                    <td className="p-4 w-1/3">
                                        <select 
                                            value={item.account_id} 
                                            onChange={e => updateItem(index, 'account_id', e.target.value)}
                                            className="w-full border-border-warm rounded-xl text-sm font-medium focus:ring-terracotta"
                                            required
                                        >
                                            <option value="">Select Account...</option>
                                            {accounts.map(acc => (
                                                <option key={acc.id} value={acc.id}>{acc.code} - {acc.name}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="p-4">
                                        <input 
                                            type="text" 
                                            value={item.description} 
                                            onChange={e => updateItem(index, 'description', e.target.value)}
                                            className="w-full border-none focus:ring-0 p-2 text-sm font-medium text-ink bg-transparent placeholder-ink-muted/60"
                                            placeholder="Optional line description"
                                        />
                                    </td>
                                    <td className="p-4">
                                        <input 
                                            type="number" 
                                            step="0.01"
                                            value={item.debit} 
                                            onChange={e => updateItem(index, 'debit', e.target.value)} 
                                            className="w-full text-right border-border-warm rounded-xl text-sm font-bold font-mono focus:ring-terracotta" 
                                        />
                                    </td>
                                    <td className="p-4">
                                        <input 
                                            type="number" 
                                            step="0.01"
                                            value={item.credit} 
                                            onChange={e => updateItem(index, 'credit', e.target.value)} 
                                            className="w-full text-right border-border-warm rounded-xl text-sm font-bold font-mono focus:ring-terracotta" 
                                        />
                                    </td>
                                    <td className="p-4 text-center">
                                        <button 
                                            type="button" 
                                            onClick={() => removeItem(index)} 
                                            className={`text-ink-muted hover:text-terracotta transition-colors ${data.items.length <= 2 ? 'invisible' : 'visible group-hover:opacity-100 opacity-0'}`}
                                        >
                                            <Icons.Trash />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-cream/50 font-bold">
                                <td colSpan="2" className="p-6 text-right text-[10px] text-ink-muted uppercase tracking-widest">Totals</td>
                                <td className="p-6 text-right font-mono text-lg text-ink">
                                    {totals.debit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                </td>
                                <td className="p-6 text-right font-mono text-lg text-ink">
                                    {totals.credit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <div className="p-6 bg-cream/80 border-t border-border-warm flex justify-between items-center">
                        <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-surface-alt border border-border-warm transition-colors">
                            <Icons.Plus /> Add Line
                        </button>

                        <div className="flex items-center gap-4">
                            {totals.difference > 0 ? (
                                <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-terracotta/10 text-terracotta text-xs font-bold border border-terracotta/30">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Out of Balance: RM {totals.difference.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                </div>
                            ) : totals.debit > 0 ? (
                                <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-forest/10 text-forest text-xs font-bold border border-forest/30">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                    Balanced
                                </div>
                            ) : null}
                        </div>
                    </div>
                </div>
                {errors.items && <p className="text-terracotta text-sm font-bold text-center mt-2">{errors.items}</p>}
            </form>
        </AuthenticatedLayout>
    );
}
