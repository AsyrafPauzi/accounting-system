import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    BuildingOffice: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    DocumentText: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
    Phone: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>,
    Location: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>,
};

const inputClass = 'w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-medium text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors';
const labelClass = 'block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5';

export default function Edit({ auth, supplier }) {
    const { data, setData, put, processing, errors } = useForm({
        name: supplier.name || '',
        code: supplier.code || '',
        contact_person: supplier.contact_person || '',
        phone: supplier.phone || '',
        email: supplier.email || '',
        tin: supplier.tin || '',
        brn: supplier.brn || '',
        payment_terms: supplier.payment_terms ?? 30,
        currency: supplier.currency || 'MYR',
        billing_street: supplier.billing_street || '',
        billing_city: supplier.billing_city || '',
        billing_state: supplier.billing_state || '',
        billing_zip: supplier.billing_zip || '',
        billing_country: supplier.billing_country || 'Malaysia',
        website: supplier.website || '',
        region: supplier.region || '',
        segment: supplier.segment || '',
        is_active: supplier.is_active === 1 || supplier.is_active === true,
        internal_notes: supplier.internal_notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('suppliers.update', supplier.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div className="flex items-start sm:items-center gap-4">
                        <Link href={route('suppliers.show', supplier.id)} className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200">
                            <Icons.ChevronLeft />
                        </Link>
                        <div>
                            <h2 className="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">Edit supplier</h2>
                            <p className="text-slate-500 text-sm font-medium mt-1">{supplier.name} — {supplier.code}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('suppliers.show', supplier.id)} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
                            Cancel
                        </Link>
                        <button type="submit" form="supplier-edit-form" disabled={processing} className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 shadow-lg shadow-blue-500/25 transition-all duration-200">
                            {processing ? 'Saving...' : 'Save changes'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={`Edit ${supplier.name}`} />

            <form id="supplier-edit-form" onSubmit={submit} className="space-y-6">
                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.BuildingOffice /></span>
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Identity & compliance</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div className="md:col-span-2">
                            <label className={labelClass}>Supplier name</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} required />
                            {errors.name && <p className="text-rose-500 text-xs font-medium mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Supplier code</label>
                            <input type="text" value={data.code} onChange={e => setData('code', e.target.value)} className={inputClass + ' font-mono'} required />
                            {errors.code && <p className="text-rose-500 text-xs font-medium mt-1">{errors.code}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Payment terms (days)</label>
                            <input type="number" min="0" max="365" value={data.payment_terms} onChange={e => setData('payment_terms', parseInt(e.target.value, 10) || 0)} className={inputClass} required />
                        </div>
                        <div>
                            <label className={labelClass}>TIN</label>
                            <input type="text" value={data.tin} onChange={e => setData('tin', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>BRN</label>
                            <input type="text" value={data.brn} onChange={e => setData('brn', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>Currency</label>
                            <input type="text" value={data.currency} onChange={e => setData('currency', e.target.value)} className={inputClass} maxLength={3} />
                        </div>
                        <div>
                            <label className={labelClass}>Website</label>
                            <input type="url" value={data.website} onChange={e => setData('website', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>Region</label>
                            <input type="text" value={data.region} onChange={e => setData('region', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>Segment</label>
                            <input type="text" value={data.segment} onChange={e => setData('segment', e.target.value)} className={inputClass} />
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Phone /></span>
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Contact</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label className={labelClass}>Contact person</label>
                            <input type="text" value={data.contact_person} onChange={e => setData('contact_person', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>Email</label>
                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className={inputClass} />
                            {errors.email && <p className="text-rose-500 text-xs font-medium mt-1">{errors.email}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Phone</label>
                            <input type="text" value={data.phone} onChange={e => setData('phone', e.target.value)} className={inputClass} />
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.Location /></span>
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Billing address</h3>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div className="md:col-span-4">
                            <label className={labelClass}>Street</label>
                            <input type="text" value={data.billing_street} onChange={e => setData('billing_street', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>City</label>
                            <input type="text" value={data.billing_city} onChange={e => setData('billing_city', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>State</label>
                            <input type="text" value={data.billing_state} onChange={e => setData('billing_state', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>Postcode</label>
                            <input type="text" value={data.billing_zip} onChange={e => setData('billing_zip', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className={labelClass}>Country</label>
                            <input type="text" value={data.billing_country} onChange={e => setData('billing_country', e.target.value)} className={inputClass} />
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div className="flex items-center gap-2 mb-6">
                        <span className="p-2 rounded-xl bg-slate-100 text-slate-600"><Icons.DocumentText /></span>
                        <h3 className="font-semibold text-slate-800 text-sm uppercase tracking-wider">Other</h3>
                    </div>
                    <div className="space-y-4">
                        <div>
                            <label className={labelClass}>Internal notes</label>
                            <textarea value={data.internal_notes} onChange={e => setData('internal_notes', e.target.value)} className={inputClass} rows={3} />
                        </div>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
                            <span className="text-sm font-medium text-slate-700">Active (can be used on new bills)</span>
                        </label>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
