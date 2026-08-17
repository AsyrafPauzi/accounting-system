import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PurchasesDocLines, { blankPurchaseLine } from '@/Components/PurchasesDocLines';

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

export default function Create({ auth, suppliers = [], expenseAccounts = [] }) {
    const form = useForm({
        name: '',
        supplier_id: '',
        cadence: 'monthly',
        interval: 1,
        start_date: new Date().toISOString().split('T')[0],
        payment_terms_days: 30,
        auto_post: false,
        items: [blankPurchaseLine(expenseAccounts[0]?.code || '5000')],
    });

    const { data, setData, processing, errors } = form;

    const submit = (event) => {
        event.preventDefault();
        form.transform((formData) => ({
            ...formData,
            items: formData.items.map((item) => ({
                account_code: item.account_code,
                description: item.description,
                quantity: item.quantity,
                unit_amount: item.unit_price,
                unit_price: item.unit_price,
                amount: (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
            })),
        }));
        form.post(route('recurring-bills.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user} header={
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                    <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">New recurring bill</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Set it up once. Each cycle creates a draft bill.</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('recurring-bills.index')} className="inline-flex items-center px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">Cancel</Link>
                    <button type="submit" form="recurring-bill-create-form" disabled={processing} className="inline-flex items-center px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta disabled:opacity-50 shadow-lg">
                        {processing ? 'Saving…' : 'Save recurring bill'}
                    </button>
                </div>
            </div>
        }>
            <Head title="New recurring bill" />
            <div className="max-w-6xl mx-auto p-4 sm:p-6">
                <form id="recurring-bill-create-form" className="space-y-6" onSubmit={submit}>
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                            <h2 className="text-sm font-display font-medium text-ink">Template details</h2>
                        </div>
                        <div className="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div className="sm:col-span-2">
                                <label className={labelClass}>Internal label</label>
                                <input
                                    className={inputClass}
                                    placeholder="e.g. Office rent — monthly"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="mt-1 text-xs text-terracotta">{errors.name}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Supplier *</label>
                                <select
                                    className={inputClass}
                                    value={data.supplier_id}
                                    onChange={(e) => setData('supplier_id', e.target.value)}
                                    required
                                >
                                    <option value="">— Select a supplier —</option>
                                    {suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}
                                </select>
                                {errors.supplier_id && <p className="mt-1 text-xs text-terracotta">{errors.supplier_id}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Cadence *</label>
                                <select className={inputClass} value={data.cadence} onChange={(e) => setData('cadence', e.target.value)}>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div>
                                <label className={labelClass}>Repeat every *</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="36"
                                    className={`${inputClass} font-mono text-right`}
                                    value={data.interval}
                                    onChange={(e) => setData('interval', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={labelClass}>Start date *</label>
                                <input
                                    type="date"
                                    className={inputClass}
                                    value={data.start_date}
                                    onChange={(e) => setData('start_date', e.target.value)}
                                    required
                                />
                                {errors.start_date && <p className="mt-1 text-xs text-terracotta">{errors.start_date}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Payment terms (days) *</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="365"
                                    className={`${inputClass} font-mono text-right`}
                                    value={data.payment_terms_days}
                                    onChange={(e) => setData('payment_terms_days', e.target.value)}
                                />
                            </div>
                            <label className="sm:col-span-2 inline-flex items-center gap-2 self-end min-h-[42px] text-sm text-ink cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.auto_post}
                                    onChange={(e) => setData('auto_post', e.target.checked)}
                                    className="w-4 h-4 rounded border-border-warm text-terracotta focus:ring-terracotta"
                                />
                                Post generated bills to the ledger automatically
                            </label>
                        </div>
                    </div>

                    <div className="space-y-2 min-w-0">
                        <h2 className="text-sm font-display font-medium text-ink">Line items</h2>
                        <PurchasesDocLines
                            items={data.items}
                            onChange={(items) => setData('items', items)}
                            expenseAccounts={expenseAccounts}
                        />
                        {errors.items && <p className="text-xs text-terracotta">{errors.items}</p>}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
