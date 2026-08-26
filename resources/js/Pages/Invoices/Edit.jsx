import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link, usePage } from '@inertiajs/react';
import InvoiceForm, { itemsFromInvoice } from './_Form';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Document: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    ArrowDownTray: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>,
    Eye: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    LockClosed: () => <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>,
};

function initialExchangeRate(invoice, baseCurrency) {
    const cur = (invoice.currency || 'MYR').toUpperCase();
    const base = (baseCurrency || 'MYR').toUpperCase();
    if (cur === base) return '1';
    const er = invoice.exchange_rate;
    if (er != null && Number(er) > 0) return String(Number(er));
    return '';
}

export default function Edit({
    auth,
    invoice,
    customers = [],
    lhdn_codes = [],
    journal_entry_id = null,
    base_currency = 'MYR',
    products = [],
}) {
    const { tax_codes: taxCodes = [] } = usePage().props;
    const isLocked = invoice.status === 'paid' || invoice.status === 'void';

    const { data, setData, put, processing, errors } = useForm({
        customer_id: invoice.customer_id || '',
        invoice_number: invoice.invoice_number || '',
        msic_code: invoice.msic_code || '62011',
        issue_date: invoice.issue_date || '',
        due_date: invoice.due_date || '',
        shipping_amount: parseFloat(invoice.shipping_amount || 0),
        customer_notes: invoice.customer_notes || '',
        show_signature: invoice.show_signature ?? true,
        currency: (invoice.currency || 'MYR').toUpperCase(),
        exchange_rate: initialExchangeRate(invoice, base_currency),
        items: itemsFromInvoice(invoice),
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('invoices.update', invoice.id));
    };

    const getStatusBadge = () => {
        const styles = {
            paid: 'bg-forest/10 text-forest',
            void: 'bg-surface-alt text-ink-muted',
            draft: 'bg-surface-alt text-ink',
            unpaid: 'bg-terracotta/10 text-terracotta',
            'partially paid': 'bg-surface-alt text-terracotta',
        };
        return styles[invoice.status] || 'bg-surface-alt text-ink';
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                isLocked ? (
                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div className="flex items-start sm:items-center gap-4">
                            <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                                <Icons.ChevronLeft />
                            </Link>
                            <div className="flex items-center gap-4">
                                <span className="p-2.5 rounded-xl bg-surface-alt text-ink-muted"><Icons.Document /></span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">{invoice.invoice_number}</h2>
                                        <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase ${getStatusBadge()}`}>{invoice.status}</span>
                                    </div>
                                    <p className="text-ink-muted text-sm font-medium mt-1">Document locked — read only</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {auth.planPermissions['general-ledger.view'] && journal_entry_id && (
                                <Link href={route('general-ledger.show', journal_entry_id)} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-terracotta bg-surface-alt border border-border-warm hover:bg-surface-alt transition-all duration-200">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                    View Accounting Entry
                                </Link>
                            )}
                            <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                <Icons.Eye /> Preview
                            </a>
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                <Icons.ArrowDownTray /> Download PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                Back to List
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div className="flex items-start sm:items-center gap-4">
                            <Link href={route('invoices.index')} className="p-2.5 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200">
                                <Icons.ChevronLeft />
                            </Link>
                            <div className="flex items-center gap-4">
                                <span className="p-2.5 rounded-xl bg-surface-alt text-terracotta"><Icons.Document /></span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Edit Invoice</h2>
                                        <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-semibold uppercase ${getStatusBadge()}`}>{invoice.status}</span>
                                    </div>
                                    <p className="text-ink-muted text-sm font-medium mt-1">{invoice.invoice_number} · {customers.find((c) => c.id == invoice.customer_id)?.name || 'Customer'}</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                <Icons.Eye /> Preview
                            </a>
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                <Icons.ArrowDownTray /> PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:border-border-warm hover:bg-cream transition-all duration-200">
                                Cancel
                            </Link>
                            <button type="submit" form="invoice-edit-form" disabled={processing} className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg transition-all duration-200">
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </div>
                )
            }
        >
            <Head title={`Edit ${invoice.invoice_number}`} />

            <div className="space-y-6 pb-12">
                {isLocked ? (
                    <div className="bg-surface p-12 rounded-2xl border border-border-warm/80 shadow-sm text-center space-y-6">
                        <div className="flex justify-center">
                            <span className="p-4 rounded-2xl bg-surface-alt text-ink-muted">
                                <Icons.LockClosed />
                            </span>
                        </div>
                        <div>
                            <h3 className="text-xl font-display font-medium text-ink mb-2">Document Locked</h3>
                            <p className="text-ink-muted max-w-md mx-auto leading-relaxed text-sm">
                                This invoice is marked as <strong className="text-ink">{invoice.status}</strong>.
                                To maintain audit integrity, finalized documents cannot be modified.
                                Issue a <strong>Credit Note</strong> to reverse charges.
                            </p>
                        </div>
                        <div className="flex gap-3 justify-center">
                            <a href={route('invoices.preview', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
                                <Icons.Eye /> Preview
                            </a>
                            <a href={route('invoices.pdf', invoice.id)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
                                <Icons.ArrowDownTray /> Download PDF
                            </a>
                            <Link href={route('invoices.index')} className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white bg-ink hover:bg-ink transition-colors">
                                Return to List
                            </Link>
                        </div>
                    </div>
                ) : (
                    <InvoiceForm
                        formId="invoice-edit-form"
                        data={data}
                        setData={setData}
                        errors={errors}
                        onSubmit={submit}
                        customers={customers}
                        lhdn_codes={lhdn_codes}
                        products={products}
                        taxCodes={taxCodes}
                        base_currency={base_currency}
                        mode="edit"
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
