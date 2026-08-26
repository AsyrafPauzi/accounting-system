import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

const inputClass = 'mt-1 block w-full rounded-xl border-border-warm bg-surface text-sm';

export default function TaxCodesIndex({ auth, taxCodes = [], canEdit }) {
    const createForm = useForm({
        code: '',
        name: '',
        rate: 8,
        type: 'standard',
        output_account_code: '2100',
        input_account_code: '1110',
    });

    const submitCreate = (e) => {
        e.preventDefault();
        createForm.post(route('settings.tax-codes.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const updateRow = (row) => {
        router.patch(route('settings.tax-codes.update', row.id), {
            name: row.name,
            rate: row.rate,
            type: row.type,
            output_account_code: row.output_account_code,
            input_account_code: row.input_account_code,
            is_active: row.is_active,
        }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="font-display text-2xl font-medium text-ink">Tax codes</h2>
                    <p className="text-sm text-ink-muted mt-1">Malaysian SST codes for sales and purchases posting.</p>
                </div>
            }
        >
            <Head title="Tax codes" />

            <div className="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div className="rounded-2xl border border-border-warm bg-white overflow-hidden">
                    <table className="min-w-full text-sm">
                        <thead className="bg-surface-alt text-ink-muted uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left">Code</th>
                                <th className="px-4 py-3 text-left">Name</th>
                                <th className="px-4 py-3 text-right">Rate</th>
                                <th className="px-4 py-3 text-left">Output GL</th>
                                <th className="px-4 py-3 text-left">Input GL</th>
                                <th className="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {taxCodes.map((row) => (
                                <tr key={row.id} className="border-t border-border-warm">
                                    <td className="px-4 py-3 font-mono font-medium">{row.code}</td>
                                    <td className="px-4 py-3">{row.name}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{row.rate}%</td>
                                    <td className="px-4 py-3 font-mono">{row.output_account_code || '—'}</td>
                                    <td className="px-4 py-3 font-mono">{row.input_account_code || '—'}</td>
                                    <td className="px-4 py-3">
                                        {canEdit && !row.is_system ? (
                                            <label className="inline-flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={row.is_active}
                                                    onChange={(e) => updateRow({ ...row, is_active: e.target.checked })}
                                                />
                                                Active
                                            </label>
                                        ) : (
                                            <span className={row.is_active ? 'text-emerald-700' : 'text-ink-muted'}>
                                                {row.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {canEdit && (
                    <form onSubmit={submitCreate} className="rounded-2xl border border-border-warm bg-white p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Code" />
                            <TextInput className={inputClass} value={createForm.data.code} onChange={(e) => createForm.setData('code', e.target.value.toUpperCase())} required />
                        </div>
                        <div>
                            <InputLabel value="Name" />
                            <TextInput className={inputClass} value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} required />
                        </div>
                        <div>
                            <InputLabel value="Rate (%)" />
                            <TextInput type="number" step="0.01" className={inputClass} value={createForm.data.rate} onChange={(e) => createForm.setData('rate', e.target.value)} required />
                        </div>
                        <div className="md:col-span-3">
                            <PrimaryButton disabled={createForm.processing}>Add tax code</PrimaryButton>
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
