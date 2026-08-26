import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export default function DocumentNumbers({ auth, settings = [], canEdit, financial_year_start_month }) {
    const { data, setData, patch, processing } = useForm({
        settings: settings.map((row) => ({
            doc_type: row.doc_type,
            prefix: row.prefix,
            next_number: row.next_number,
            pad_width: row.pad_width,
            reset_on_fy: row.reset_on_fy,
        })),
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('settings.document-numbers.update'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="font-display text-2xl font-medium text-ink">Document numbering</h2>
                    <p className="text-sm text-ink-muted mt-1">
                        Prefix and next number for each document type. Financial year starts in month {financial_year_start_month}.
                    </p>
                </div>
            }
        >
            <Head title="Document numbering" />

            <div className="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-4">
                    {data.settings.map((row, index) => (
                        <div key={row.doc_type} className="rounded-2xl border border-border-warm bg-white p-4 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <div>
                                <div className="text-xs uppercase tracking-wide text-ink-muted">Type</div>
                                <div className="font-medium text-ink capitalize">{row.doc_type.replace(/_/g, ' ')}</div>
                            </div>
                            <div>
                                <InputLabel value="Prefix" />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={row.prefix}
                                    disabled={!canEdit}
                                    onChange={(e) => {
                                        const next = [...data.settings];
                                        next[index] = { ...next[index], prefix: e.target.value.toUpperCase() };
                                        setData('settings', next);
                                    }}
                                />
                            </div>
                            <div>
                                <InputLabel value="Next number" />
                                <TextInput
                                    type="number"
                                    min="1"
                                    className="mt-1 block w-full"
                                    value={row.next_number}
                                    disabled={!canEdit}
                                    onChange={(e) => {
                                        const next = [...data.settings];
                                        next[index] = { ...next[index], next_number: parseInt(e.target.value, 10) || 1 };
                                        setData('settings', next);
                                    }}
                                />
                            </div>
                            <div>
                                <InputLabel value="Pad width" />
                                <TextInput
                                    type="number"
                                    min="2"
                                    max="10"
                                    className="mt-1 block w-full"
                                    value={row.pad_width}
                                    disabled={!canEdit}
                                    onChange={(e) => {
                                        const next = [...data.settings];
                                        next[index] = { ...next[index], pad_width: parseInt(e.target.value, 10) || 4 };
                                        setData('settings', next);
                                    }}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-ink">
                                <input
                                    type="checkbox"
                                    checked={row.reset_on_fy}
                                    disabled={!canEdit}
                                    onChange={(e) => {
                                        const next = [...data.settings];
                                        next[index] = { ...next[index], reset_on_fy: e.target.checked };
                                        setData('settings', next);
                                    }}
                                />
                                Reset each FY
                            </label>
                        </div>
                    ))}

                    {canEdit && (
                        <div className="flex justify-end">
                            <PrimaryButton disabled={processing}>{processing ? 'Saving…' : 'Save settings'}</PrimaryButton>
                        </div>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
