import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none';

const emptyForm = {
    employee_number: '',
    name: '',
    nric: '',
    epf_number: '',
    tax_category: '1',
    basic_salary: '',
    is_active: true,
};

export default function Employees({ auth, employees = [], taxCategories = [] }) {
    const { flash } = usePage().props;
    const [editingId, setEditingId] = useState(null);

    const { data, setData, post, patch, processing, errors, reset } = useForm({ ...emptyForm });

    const startCreate = () => {
        setEditingId(null);
        reset();
        setData({ ...emptyForm });
    };

    const startEdit = (employee) => {
        setEditingId(employee.id);
        setData({
            employee_number: employee.employee_number || '',
            name: employee.name || '',
            nric: employee.nric || '',
            epf_number: employee.epf_number || '',
            tax_category: employee.tax_category || '1',
            basic_salary: employee.basic_salary?.toString() || '',
            is_active: employee.is_active !== false,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (editingId) {
            patch(route('payroll.employees.update', editingId), {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    reset();
                },
            });
            return;
        }

        post(route('payroll.employees.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setData({ ...emptyForm });
            },
        });
    };

    const removeEmployee = (employee) => {
        if (! window.confirm(`Remove ${employee.name}?`)) {
            return;
        }
        router.delete(route('payroll.employees.destroy', employee.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h2 className="text-2xl lg:text-3xl font-display font-medium text-ink tracking-tight">Employees</h2>
                        <p className="text-ink-muted text-sm font-medium mt-1">NRIC, EPF number, and PCB category for statutory exports.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('payroll.create')} className="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm hover:bg-cream">
                            Run payroll
                        </Link>
                        <button type="button" onClick={startCreate} className="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-forest hover:bg-forest/90">
                            New employee
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Employees" />

            {flash?.success && <div className="mb-4 rounded-xl bg-forest/10 text-forest px-4 py-3 text-sm">{flash.success}</div>}
            {flash?.error && <div className="mb-4 rounded-xl bg-terracotta/10 text-terracotta px-4 py-3 text-sm">{flash.error}</div>}

            <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
                <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted border-b border-border-warm bg-cream/50">
                                    <th className="px-4 py-3 text-left">Employee</th>
                                    <th className="px-4 py-3 text-left">NRIC</th>
                                    <th className="px-4 py-3 text-left">EPF no.</th>
                                    <th className="px-4 py-3 text-left">PCB cat.</th>
                                    <th className="px-4 py-3 text-right">Basic</th>
                                    <th className="px-4 py-3 text-left">Status</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.length === 0 ? (
                                    <tr><td colSpan={7} className="px-4 py-10 text-center text-ink-muted">No employees yet. Add your first employee to break down payroll runs.</td></tr>
                                ) : employees.map((employee) => (
                                    <tr key={employee.id} className="border-b border-border-warm last:border-0">
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-ink">{employee.name}</div>
                                            {employee.employee_number && <div className="text-xs text-ink-muted font-mono">{employee.employee_number}</div>}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">{employee.nric || '—'}</td>
                                        <td className="px-4 py-3 font-mono text-xs">{employee.epf_number || '—'}</td>
                                        <td className="px-4 py-3">{employee.tax_category}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{formatCurrency(employee.basic_salary)}</td>
                                        <td className="px-4 py-3 capitalize">{employee.is_active ? 'Active' : 'Inactive'}</td>
                                        <td className="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                            <button type="button" onClick={() => startEdit(employee)} className="text-terracotta hover:underline text-xs font-semibold">Edit</button>
                                            <button type="button" onClick={() => removeEmployee(employee)} className="text-ink-muted hover:underline text-xs font-semibold">Remove</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <form onSubmit={submit} className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5 space-y-4">
                    <h3 className="text-sm font-semibold text-ink">{editingId ? 'Edit employee' : 'Add employee'}</h3>

                    <div>
                        <label className={labelClass}>Name</label>
                        <input className={inputClass} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        {errors.name && <p className="mt-1 text-xs text-terracotta">{errors.name}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Employee no.</label>
                        <input className={inputClass} value={data.employee_number} onChange={(e) => setData('employee_number', e.target.value)} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelClass}>NRIC</label>
                            <input className={inputClass} value={data.nric} onChange={(e) => setData('nric', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>EPF no.</label>
                            <input className={inputClass} value={data.epf_number} onChange={(e) => setData('epf_number', e.target.value)} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelClass}>PCB category</label>
                            <select className={inputClass} value={data.tax_category} onChange={(e) => setData('tax_category', e.target.value)}>
                                {taxCategories.map((cat) => (
                                    <option key={cat.value} value={cat.value}>{cat.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Basic salary</label>
                            <input type="number" step="0.01" min="0" className={inputClass} value={data.basic_salary} onChange={(e) => setData('basic_salary', e.target.value)} />
                        </div>
                    </div>

                    <label className="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-border-warm text-terracotta focus:ring-terracotta" />
                        Active on payroll runs
                    </label>

                    <div className="flex gap-2 pt-2">
                        <button type="submit" disabled={processing} className="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50">
                            {processing ? 'Saving…' : editingId ? 'Update' : 'Add employee'}
                        </button>
                        {editingId && (
                            <button type="button" onClick={startCreate} className="px-4 py-2 rounded-xl text-sm font-semibold text-ink bg-surface border border-border-warm">
                                Cancel
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
