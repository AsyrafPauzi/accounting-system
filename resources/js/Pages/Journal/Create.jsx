import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const Icons = {
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Journal: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
};

const inputClass = "w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors";
const labelClass = "block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5";
const moneyInputClass = "w-full h-11 text-right border border-border-warm rounded-xl px-4 text-sm font-bold font-mono text-ink focus:ring-2 focus:ring-terracotta";

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
                <DocumentFormHeader
                    backHref={route('journal.index')}
                    title="New Journal Entry"
                    subtitle="Record a manual double-entry transaction"
                    formId="journal-create-form"
                    processing={processing}
                    submitLabel="Save journal"
                    submitDisabled={!isBalanced}
                />
            }
        >
            <Head title="New Journal Entry" />

            <form id="journal-create-form" onSubmit={submit} className="space-y-6 pb-12">
                <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
                    <div className="space-y-6">
                        <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                            <div className="flex items-center gap-3 mb-5">
                                <span className="p-2 rounded-xl bg-surface-alt text-terracotta"><Icons.Journal /></span>
                                <div>
                                    <h3 className="font-semibold text-ink">Journal header</h3>
                                    <p className="text-sm text-ink-muted mt-0.5">Set the overall context for this manual posting.</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="md:col-span-2">
                                    <label className={labelClass}>General description</label>
                                    <input
                                        type="text"
                                        value={data.description}
                                        onChange={e => setData('description', e.target.value)}
                                        className={inputClass}
                                        placeholder="E.g., Monthly depreciation, year-end adjustment..."
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
                            <div className="px-6 py-5 border-b border-border-warm bg-cream/40 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="font-semibold text-ink">Journal lines</h3>
                                    <p className="text-sm text-ink-muted mt-0.5">Each line needs one account and either a debit or a credit amount.</p>
                                </div>
                                <button type="button" onClick={addItem} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-terracotta bg-surface-alt hover:bg-cream border border-border-warm transition-colors">
                                    <Icons.Plus /> Add line
                                </button>
                            </div>

                            <div className="p-4 sm:p-6 space-y-4">
                                {data.items.map((item, index) => (
                                    <div key={index} className="rounded-2xl border border-border-warm bg-cream/20 p-4 sm:p-5">
                                        <div className="flex items-center justify-between gap-3 mb-4">
                                            <p className="text-xs font-semibold uppercase tracking-wider text-ink-muted">Line {index + 1}</p>
                                            <button
                                                type="button"
                                                onClick={() => removeItem(index)}
                                                className={`inline-flex items-center gap-1 text-sm text-ink-muted hover:text-terracotta transition-colors ${data.items.length <= 2 ? 'invisible' : ''}`}
                                            >
                                                <Icons.Trash /> Remove
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label className={labelClass}>Account</label>
                                                <select
                                                    value={item.account_id}
                                                    onChange={e => updateItem(index, 'account_id', e.target.value)}
                                                    className={inputClass}
                                                    required
                                                >
                                                    <option value="">Select account...</option>
                                                    {accounts.map(acc => (
                                                        <option key={acc.id} value={acc.id}>{acc.code} - {acc.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className={labelClass}>Line description</label>
                                                <input
                                                    type="text"
                                                    value={item.description}
                                                    onChange={e => updateItem(index, 'description', e.target.value)}
                                                    className={inputClass}
                                                    placeholder="Optional line note"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label className={labelClass}>Debit (RM)</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={item.debit}
                                                    onChange={e => updateItem(index, 'debit', e.target.value)}
                                                    className={moneyInputClass}
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <div>
                                                <label className={labelClass}>Credit (RM)</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={item.credit}
                                                    onChange={e => updateItem(index, 'credit', e.target.value)}
                                                    className={moneyInputClass}
                                                    placeholder="0.00"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Totals</p>
                            <div className="mt-4 space-y-3">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-ink-muted">Debit</span>
                                    <span className="font-mono font-semibold text-ink">{totals.debit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-ink-muted">Credit</span>
                                    <span className="font-mono font-semibold text-ink">{totals.credit.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="pt-3 border-t border-border-warm flex items-center justify-between text-sm">
                                    <span className="text-ink-muted">Difference</span>
                                    <span className={`font-mono font-semibold ${totals.difference > 0 ? 'text-terracotta' : 'text-forest'}`}>
                                        {totals.difference.toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            {totals.difference > 0 ? (
                                <div className="rounded-xl bg-terracotta/10 border border-terracotta/20 p-4">
                                    <p className="text-sm font-semibold text-terracotta">Out of balance</p>
                                    <p className="text-sm text-ink-muted mt-1">Add or adjust lines until debit and credit totals match before saving.</p>
                                </div>
                            ) : totals.debit > 0 ? (
                                <div className="rounded-xl bg-forest/10 border border-forest/20 p-4">
                                    <p className="text-sm font-semibold text-forest">Balanced</p>
                                    <p className="text-sm text-ink-muted mt-1">This journal is ready to save as a draft.</p>
                                </div>
                            ) : (
                                <div className="rounded-xl bg-cream/50 border border-border-warm p-4">
                                    <p className="text-sm font-semibold text-ink">Start entering lines</p>
                                    <p className="text-sm text-ink-muted mt-1">Use one or more debit lines and matching credit lines.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
                {errors.items && <p className="text-terracotta text-sm font-bold text-center mt-2">{errors.items}</p>}
            </form>
        </AuthenticatedLayout>
    );
}
