import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';
import { blankPurchaseLine } from '@/Components/PurchasesDocLines';
import BillForm, { KIND_COPY, defaultAccountCode, toBillPayload } from './_Form';

export default function Create({
    auth,
    suppliers = [],
    expenseAccounts = [],
    bankAccounts = [],
    products = [],
    nextBillNumber = 'BILL-1',
    preselectedSupplierId = null,
}) {
    const today = new Date().toISOString().split('T')[0];
    const dueDefault = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    const accountCode = defaultAccountCode(expenseAccounts);

    const form = useForm({
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
        items: [blankPurchaseLine(accountCode)],
    });

    const { data, setData, processing, errors } = form;
    const copy = KIND_COPY[data.purchase_kind] || KIND_COPY.credit;

    const submit = (e) => {
        e.preventDefault();
        form.transform((current) => toBillPayload(current));
        form.post(route('bills.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <DocumentFormHeader
                    backHref={route('bills.index')}
                    title={copy.title}
                    subtitle={copy.subtitle}
                    formId="bill-form"
                    processing={processing}
                    submitLabel={copy.submit}
                />
            }
        >
            <Head title={copy.title} />
            <BillForm
                formId="bill-form"
                data={data}
                setData={setData}
                errors={errors}
                onSubmit={submit}
                suppliers={suppliers}
                expenseAccounts={expenseAccounts}
                bankAccounts={bankAccounts}
                products={products}
            />
        </AuthenticatedLayout>
    );
}
