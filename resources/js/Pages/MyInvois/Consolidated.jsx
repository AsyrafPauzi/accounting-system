import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Consolidated({ auth, invoices = [], batches = [], gaps = [] }) {
    const { data, setData, post, processing } = useForm({
        invoice_ids: [],
        period_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
        period_to: new Date().toISOString().split('T')[0],
    });
    const toggle = (id) => {
        const ids = data.invoice_ids.includes(id) ? data.invoice_ids.filter((x) => x !== id) : [...data.invoice_ids, id];
        setData('invoice_ids', ids);
    };
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div>
                    <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Consolidated e-invoice</h2>
                    <p className="text-ink-muted text-sm font-medium mt-1">Bundle posted invoices for a single MyInvois submission</p>
                </div>
            }
        >
            <Head title="Consolidated e-invoice" />
            {gaps.length > 0 && <p className="text-sm text-terracotta mb-4">Complete MyInvois profile first: {gaps.join(', ')}</p>}
            <div className="flex gap-2 text-sm mb-4">
                <Link href={route('myinvois.submissions.index')} className="text-terracotta font-semibold hover:underline">Submission vault</Link>
            </div>
            <form className="space-y-4 max-w-3xl" onSubmit={(e) => { e.preventDefault(); post(route('myinvois.consolidated.store')); }}>
                <p className="text-sm text-ink-muted">Bundle posted invoices that have not been submitted individually.</p>
                <div className="grid grid-cols-2 gap-3">
                    <input type="date" className="border rounded-xl px-3 py-2 text-sm" value={data.period_from} onChange={(e) => setData('period_from', e.target.value)} />
                    <input type="date" className="border rounded-xl px-3 py-2 text-sm" value={data.period_to} onChange={(e) => setData('period_to', e.target.value)} />
                </div>
                <ul className="bg-surface rounded-2xl border divide-y">
                    {invoices.map((inv) => (
                        <li key={inv.id} className="px-4 py-3 flex items-center gap-3">
                            <input type="checkbox" checked={data.invoice_ids.includes(inv.id)} onChange={() => toggle(inv.id)} />
                            <span className="font-semibold">{inv.invoice_number}</span>
                            <span className="text-ink-muted text-sm">{inv.customer_name}</span>
                            <span className="ml-auto font-mono text-sm">{Number(inv.total_amount).toFixed(2)}</span>
                        </li>
                    ))}
                    {invoices.length === 0 && <li className="px-4 py-6 text-ink-muted text-sm">No unsubmitted posted invoices.</li>}
                </ul>
                <button disabled={processing || data.invoice_ids.length === 0} className="px-4 py-2 rounded-xl bg-terracotta text-white font-semibold">Submit batch</button>
            </form>
            {batches.length > 0 && (
                <div className="mt-8">
                    <h3 className="font-semibold mb-2">Previous batches</h3>
                    <ul className="text-sm space-y-1">
                        {batches.map((b) => <li key={b.id}>{b.batch_number} · {b.status} · {b.lhdn_uuid || '—'}</li>)}
                    </ul>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
