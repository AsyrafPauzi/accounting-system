import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

const inputClass = 'mt-1 block w-full rounded-xl border-border-warm bg-surface text-sm';

export default function BankRecImport({ auth, bank_accounts = [] }) {
    const form = useForm({
        account_id: bank_accounts[0]?.id ?? '',
        file: null,
        opening_balance: '',
        closing_balance: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('bank-rec.import.store'), {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="font-display text-2xl font-medium text-ink">Import bank statement</h2>
                    <p className="text-sm text-ink-muted mt-1">Upload a CSV with date, description, and amount columns.</p>
                </div>
            }
        >
            <Head title="Import bank statement" />

            <div className="py-6 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="rounded-2xl border border-border-warm bg-white p-6 space-y-5">
                    <div>
                        <InputLabel htmlFor="account_id" value="Bank account" />
                        <select
                            id="account_id"
                            className={inputClass}
                            value={form.data.account_id}
                            onChange={(e) => form.setData('account_id', e.target.value)}
                            required
                        >
                            {bank_accounts.map((a) => (
                                <option key={a.id} value={a.id}>{a.label}</option>
                            ))}
                        </select>
                        {form.errors.account_id && <p className="text-sm text-red-600 mt-1">{form.errors.account_id}</p>}
                    </div>

                    <div>
                        <InputLabel htmlFor="file" value="CSV file" />
                        <input
                            id="file"
                            type="file"
                            accept=".csv,text/csv"
                            className={inputClass}
                            onChange={(e) => form.setData('file', e.target.files[0])}
                            required
                        />
                        {form.errors.file && <p className="text-sm text-red-600 mt-1">{form.errors.file}</p>}
                        <p className="text-xs text-ink-muted mt-2">
                            Required columns: date, amount. Optional: description, reference.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="opening_balance" value="Opening balance (optional)" />
                            <TextInput
                                id="opening_balance"
                                type="number"
                                step="0.01"
                                className={inputClass}
                                value={form.data.opening_balance}
                                onChange={(e) => form.setData('opening_balance', e.target.value)}
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="closing_balance" value="Closing balance (optional)" />
                            <TextInput
                                id="closing_balance"
                                type="number"
                                step="0.01"
                                className={inputClass}
                                value={form.data.closing_balance}
                                onChange={(e) => form.setData('closing_balance', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <PrimaryButton disabled={form.processing}>Import</PrimaryButton>
                        <Link href={route('bank-rec.index')} className="text-sm text-ink-muted hover:text-ink">Cancel</Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
