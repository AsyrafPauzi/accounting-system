import React from 'react';
import PurchasesDocLines from '@/Components/PurchasesDocLines';
import DocumentFormNotesTotals from '@/Components/DocumentFormNotesTotals';

const Icons = {
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors disabled:bg-cream disabled:text-ink-muted';
const inputReadonlyClass = 'w-full h-11 flex items-center border border-border-warm rounded-xl px-4 text-sm font-medium font-mono text-terracotta bg-cream';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none h-4';

export default function PurchaseOrderForm({
    formId = 'po-form',
    data,
    setData,
    errors = {},
    onSubmit,
    suppliers = [],
    products = [],
    expenseAccounts = [],
    number,
    disabled = false,
    bannerTitle = 'Order first',
    bannerText = 'Save the order, then receive goods or convert to a bill. Nothing posts to the ledger until you bill.',
}) {
    return (
        <form id={formId} onSubmit={onSubmit} className="space-y-6 pb-12 min-w-0">
            <div className="bg-surface p-6 rounded-2xl border border-border-warm/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                    <span className="p-2 rounded-xl bg-surface-alt text-ink"><Icons.Document /></span>
                    <h3 className="font-semibold text-ink text-sm uppercase tracking-wider">Order details</h3>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-5 items-start">
                    <div className="min-w-0">
                        <label className={labelClass}>Number</label>
                        <div className={inputReadonlyClass}>{number}</div>
                    </div>
                    <div className="md:col-span-2 min-w-0">
                        <label className={labelClass}>Supplier</label>
                        <select
                            value={data.supplier_id}
                            onChange={(e) => setData('supplier_id', e.target.value)}
                            className={inputClass}
                            required
                            disabled={disabled}
                        >
                            <option value="">Select supplier...</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                        {errors.supplier_id && <p className="text-terracotta text-xs font-medium mt-1">{errors.supplier_id}</p>}
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Issue date</label>
                        <input type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} className={inputClass} required disabled={disabled} />
                    </div>
                    <div className="min-w-0">
                        <label className={labelClass}>Expected date</label>
                        <input type="date" value={data.expected_date || ''} onChange={(e) => setData('expected_date', e.target.value)} className={inputClass} disabled={disabled} />
                    </div>
                </div>
            </div>

            <PurchasesDocLines
                items={data.items}
                onChange={(items) => setData('items', items)}
                products={products}
                expenseAccounts={expenseAccounts}
                disabled={disabled}
            />

            <DocumentFormNotesTotals
                bannerTitle={bannerTitle}
                bannerText={bannerText}
                notesLabel="Notes (on PDF)"
                notesValue={data.notes || ''}
                onNotesChange={(value) => setData('notes', value)}
                notesPlaceholder="Delivery terms, thank you message…"
                notesDisabled={disabled}
                items={data.items}
            />
        </form>
    );
}
