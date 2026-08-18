import React, { useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import DocumentFormHeader from '@/Components/DocumentFormHeader';

const Icons = {
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    Warning: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" /></svg>,
};

const inputClass = 'w-full h-11 border border-border-warm rounded-xl px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none';

const fmt = (n) => (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function AmountCell({ id, value, onChange, autoFocus = false }) {
    return (
        <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-mono text-ink-muted">RM</span>
            <input
                id={id}
                type="number"
                step="0.01"
                min="0"
                inputMode="decimal"
                autoFocus={autoFocus}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onFocus={(e) => e.target.select()}
                className="w-full h-10 pl-10 pr-3 text-right font-mono text-sm font-semibold text-ink border border-border-warm rounded-xl focus:ring-2 focus:ring-terracotta focus:border-terracotta bg-surface"
                placeholder="0.00"
            />
        </div>
    );
}

function AmountRow({ id, label, code, required = false, optional = false, value, onChange, autoFocus = false }) {
    return (
        <tr className="border-b border-border-warm/70 last:border-0">
            <td className="py-2.5 pr-4 align-middle">
                <label htmlFor={id} className="block text-sm font-medium text-ink cursor-pointer">
                    {label}
                    {required && <span className="text-terracotta ml-1">*</span>}
                    {optional && <span className="ml-1.5 text-[10px] font-semibold uppercase tracking-wide text-ink-muted">Optional</span>}
                </label>
                {code && <p className="text-[11px] font-mono text-ink-muted mt-0.5">{code}</p>}
            </td>
            <td className="py-2.5 w-[11.5rem] align-middle">
                <AmountCell id={id} value={value} onChange={onChange} autoFocus={autoFocus} />
            </td>
        </tr>
    );
}

export default function Run({ auth, bankAccounts = [], accounts = {}, todayIso }) {
    const netTouched = useRef(false);
    const [showOptional, setShowOptional] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        period_date: todayIso || new Date().toISOString().slice(0, 10),
        description: '',
        reference_number: '',
        bank_account_code: bankAccounts[0]?.value || '',
        gross_salaries: '',
        employer_epf: '',
        employer_socso: '',
        employer_eis: '',
        employer_hrd: '',
        epf_payable: '',
        socso_payable: '',
        eis_payable: '',
        pcb_payable: '',
        hrd_payable: '',
        net_pay: '',
    });

    const num = (v) => (parseFloat(v) || 0);
    const code = (key) => accounts[key]?.code;

    const totalDebits = useMemo(() => (
        num(data.gross_salaries) + num(data.employer_epf) + num(data.employer_socso) + num(data.employer_eis) + num(data.employer_hrd)
    ), [data.gross_salaries, data.employer_epf, data.employer_socso, data.employer_eis, data.employer_hrd]);

    const totalStatutory = useMemo(() => (
        num(data.epf_payable) + num(data.socso_payable) + num(data.eis_payable) + num(data.pcb_payable) + num(data.hrd_payable)
    ), [data.epf_payable, data.socso_payable, data.eis_payable, data.pcb_payable, data.hrd_payable]);

    const suggestedNetPay = useMemo(() => Math.max(0, totalDebits - totalStatutory), [totalDebits, totalStatutory]);
    const totalCredits = useMemo(() => totalStatutory + num(data.net_pay), [totalStatutory, data.net_pay]);
    const drift = useMemo(() => Math.round((totalDebits - totalCredits) * 100) / 100, [totalDebits, totalCredits]);
    const balanced = Math.abs(drift) < 0.005;

    const setAmount = (field, value) => {
        if (! netTouched.current) {
            const next = { ...data, [field]: value };
            const debits = num(next.gross_salaries) + num(next.employer_epf) + num(next.employer_socso) + num(next.employer_eis) + num(next.employer_hrd);
            const statutory = num(next.epf_payable) + num(next.socso_payable) + num(next.eis_payable) + num(next.pcb_payable) + num(next.hrd_payable);
            setData({
                [field]: value,
                net_pay: Math.max(0, debits - statutory).toFixed(2),
            });
            return;
        }
        setData(field, value);
    };

    const setNetPay = (value) => {
        netTouched.current = true;
        setData('net_pay', value);
    };

    const useSuggested = () => {
        netTouched.current = false;
        setData('net_pay', suggestedNetPay.toFixed(2));
    };

    const periodLabel = useMemo(() => {
        if (! data.period_date) return '';
        return new Date(data.period_date).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });
    }, [data.period_date]);

    const selectedBank = bankAccounts.find((b) => b.value === data.bank_account_code);

    const previewLines = [
        { label: 'Gross salaries', code: code('salaries_expense'), amount: num(data.gross_salaries), side: 'debit' },
        { label: 'Employer EPF', code: code('epf_expense'), amount: num(data.employer_epf), side: 'debit' },
        { label: 'Employer SOCSO', code: code('socso_expense'), amount: num(data.employer_socso), side: 'debit' },
        { label: 'Employer EIS', code: code('eis_expense'), amount: num(data.employer_eis), side: 'debit' },
        { label: 'HRD levy', code: code('hrd_expense'), amount: num(data.employer_hrd), side: 'debit' },
        { label: 'EPF payable', code: code('epf_payable'), amount: num(data.epf_payable), side: 'credit' },
        { label: 'SOCSO payable', code: code('socso_payable'), amount: num(data.socso_payable), side: 'credit' },
        { label: 'EIS payable', code: code('eis_payable'), amount: num(data.eis_payable), side: 'credit' },
        { label: 'PCB payable', code: code('pcb_payable'), amount: num(data.pcb_payable), side: 'credit' },
        { label: 'HRD payable', code: code('hrd_payable'), amount: num(data.hrd_payable), side: 'credit' },
        { label: selectedBank?.label || 'Bank', code: data.bank_account_code, amount: num(data.net_pay), side: 'credit' },
    ].filter((line) => line.amount > 0);

    const submit = (e) => {
        e.preventDefault();
        post(route('payroll.store'));
    };

    const hasOptionalValues = num(data.employer_hrd) > 0 || num(data.hrd_payable) > 0;

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={
                <DocumentFormHeader
                    backHref={route('journal.index')}
                    title="Record payroll"
                    subtitle="Key totals from your payroll slip. One balanced journal is posted."
                    formId="payroll-run-form"
                    processing={processing}
                    submitLabel="Post payroll"
                    submitDisabled={! balanced || num(data.gross_salaries) <= 0}
                    actions={
                        <>
                            <Link
                                href={route('payroll.batch')}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200"
                            >
                                Batch
                            </Link>
                            <Link
                                href={route('journal.index')}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-all duration-200"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                form="payroll-run-form"
                                disabled={processing || ! balanced || num(data.gross_salaries) <= 0}
                                className="inline-flex items-center gap-2 px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta disabled:opacity-50 shadow-lg transition-all duration-200"
                            >
                                {processing ? 'Saving…' : 'Post payroll'}
                            </button>
                        </>
                    }
                />
            }
        >
            <Head title="Run Payroll" />

            <form id="payroll-run-form" onSubmit={submit} className="space-y-6 pb-12 min-w-0">
                <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
                    <div className="space-y-5">
                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5 sm:p-6">
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label className={labelClass}>Period end</label>
                                    <input type="date" value={data.period_date} onChange={(e) => setData('period_date', e.target.value)} className={inputClass} required />
                                    {periodLabel && <p className="mt-1 text-xs text-ink-muted">{periodLabel}</p>}
                                    {errors.period_date && <p className="mt-1 text-xs text-terracotta">{errors.period_date}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Pay from</label>
                                    <select value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} className={inputClass} required>
                                        <option value="">Select bank…</option>
                                        {bankAccounts.map((b) => (
                                            <option key={b.value} value={b.value}>{b.label}</option>
                                        ))}
                                    </select>
                                    {errors.bank_account_code && <p className="mt-1 text-xs text-terracotta">{errors.bank_account_code}</p>}
                                </div>
                                <div>
                                    <label className={labelClass}>Description</label>
                                    <input
                                        type="text"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder={`Payroll for ${periodLabel || 'the month'}`}
                                        className={inputClass}
                                    />
                                </div>
                                <div>
                                    <label className={labelClass}>Reference</label>
                                    <input
                                        type="text"
                                        value={data.reference_number}
                                        onChange={(e) => setData('reference_number', e.target.value)}
                                        placeholder="PAY-2026-08"
                                        className={inputClass}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                            <div className="px-5 sm:px-6 py-3.5 border-b border-border-warm bg-cream/40 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-semibold text-ink">Cost to company</p>
                                    <p className="text-xs text-ink-muted">Debit expenses. Tab down the amounts.</p>
                                </div>
                                <p className="font-mono text-sm font-bold tabular-nums text-ink">RM {fmt(totalDebits)}</p>
                            </div>
                            <div className="px-5 sm:px-6">
                                <table className="w-full">
                                    <tbody>
                                        <AmountRow id="gross" label="Gross salaries & wages" code={code('salaries_expense')} required value={data.gross_salaries} onChange={(v) => setAmount('gross_salaries', v)} autoFocus />
                                        <AmountRow id="eepf" label="Employer EPF" code={code('epf_expense')} value={data.employer_epf} onChange={(v) => setAmount('employer_epf', v)} />
                                        <AmountRow id="esocso" label="Employer SOCSO" code={code('socso_expense')} value={data.employer_socso} onChange={(v) => setAmount('employer_socso', v)} />
                                        <AmountRow id="eeis" label="Employer EIS" code={code('eis_expense')} value={data.employer_eis} onChange={(v) => setAmount('employer_eis', v)} />
                                        {(showOptional || hasOptionalValues) && (
                                            <AmountRow id="ehrd" label="HRD levy" code={code('hrd_expense')} optional value={data.employer_hrd} onChange={(v) => setAmount('employer_hrd', v)} />
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {errors.gross_salaries && <p className="px-6 pb-4 text-xs text-terracotta">{errors.gross_salaries}</p>}
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                            <div className="px-5 sm:px-6 py-3.5 border-b border-border-warm bg-cream/40 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-semibold text-ink">Statutory to remit</p>
                                    <p className="text-xs text-ink-muted">Employee + employer amounts you will pay out.</p>
                                </div>
                                <p className="font-mono text-sm font-bold tabular-nums text-ink">RM {fmt(totalStatutory)}</p>
                            </div>
                            <div className="px-5 sm:px-6">
                                <table className="w-full">
                                    <tbody>
                                        <AmountRow id="epfp" label="EPF payable" code={code('epf_payable')} value={data.epf_payable} onChange={(v) => setAmount('epf_payable', v)} />
                                        <AmountRow id="socsop" label="SOCSO payable" code={code('socso_payable')} value={data.socso_payable} onChange={(v) => setAmount('socso_payable', v)} />
                                        <AmountRow id="eisp" label="EIS payable" code={code('eis_payable')} value={data.eis_payable} onChange={(v) => setAmount('eis_payable', v)} />
                                        <AmountRow id="pcbp" label="PCB / LHDN tax" code={code('pcb_payable')} value={data.pcb_payable} onChange={(v) => setAmount('pcb_payable', v)} />
                                        {(showOptional || hasOptionalValues) && (
                                            <AmountRow id="hrdp" label="HRD levy payable" code={code('hrd_payable')} optional value={data.hrd_payable} onChange={(v) => setAmount('hrd_payable', v)} />
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {! showOptional && ! hasOptionalValues && (
                                <div className="px-5 sm:px-6 py-3 border-t border-border-warm">
                                    <button type="button" onClick={() => setShowOptional(true)} className="text-xs font-semibold text-terracotta hover:underline">
                                        + Add HRD levy
                                    </button>
                                </div>
                            )}
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm overflow-hidden">
                            <div className="px-5 sm:px-6 py-3.5 border-b border-border-warm bg-cream/40">
                                <p className="text-sm font-semibold text-ink">Net pay to employees</p>
                                <p className="text-xs text-ink-muted">Credited to the bank account above. Auto-fills as you type.</p>
                            </div>
                            <div className="px-5 sm:px-6">
                                <table className="w-full">
                                    <tbody>
                                        <tr>
                                            <td className="py-3 pr-4 align-middle">
                                                <label htmlFor="netpay" className="block text-sm font-semibold text-ink cursor-pointer">
                                                    Net pay <span className="text-terracotta">*</span>
                                                </label>
                                                <p className="text-[11px] text-ink-muted mt-0.5">Suggested RM {fmt(suggestedNetPay)}</p>
                                            </td>
                                            <td className="py-3 w-[11.5rem] align-middle">
                                                <AmountCell id="netpay" value={data.net_pay} onChange={setNetPay} />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            {Math.abs(num(data.net_pay) - suggestedNetPay) > 0.01 && (
                                <div className="px-5 sm:px-6 pb-4">
                                    <button type="button" onClick={useSuggested} className="text-xs font-semibold text-terracotta hover:underline">
                                        Reset to suggested RM {fmt(suggestedNetPay)}
                                    </button>
                                </div>
                            )}
                            {errors.net_pay && <p className="px-6 pb-4 text-xs text-terracotta">{errors.net_pay}</p>}
                        </div>
                    </div>

                    <aside className="xl:sticky xl:top-24 space-y-4">
                        <div className={`rounded-2xl border p-5 ${balanced ? 'border-forest/30 bg-forest/5' : 'border-terracotta/30 bg-terracotta/5'}`}>
                            <div className="flex items-center gap-2">
                                <span className={balanced ? 'text-forest' : 'text-terracotta'}>
                                    {balanced ? <Icons.Check /> : <Icons.Warning />}
                                </span>
                                <p className={`text-sm font-semibold ${balanced ? 'text-forest' : 'text-terracotta'}`}>
                                    {balanced ? 'Balanced — ready to post' : `Off by RM ${fmt(Math.abs(drift))}`}
                                </p>
                            </div>
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">Debits</dt>
                                    <dd className="font-mono font-semibold tabular-nums">RM {fmt(totalDebits)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">Credits</dt>
                                    <dd className="font-mono font-semibold tabular-nums">RM {fmt(totalCredits)}</dd>
                                </div>
                                <div className="flex justify-between border-t border-border-warm pt-2">
                                    <dt className="text-ink-muted">Net pay</dt>
                                    <dd className="font-mono font-semibold tabular-nums">RM {fmt(data.net_pay)}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className="bg-surface rounded-2xl border border-border-warm/80 shadow-sm p-5">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">What gets posted</p>
                            {previewLines.length === 0 ? (
                                <p className="mt-3 text-sm text-ink-muted">Start with gross salaries. Lines appear here as you type.</p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {previewLines.map((line) => (
                                        <li key={`${line.side}-${line.label}`} className="flex items-start justify-between gap-3 text-sm">
                                            <span className="min-w-0">
                                                <span className="block text-ink truncate">{line.label}</span>
                                                {line.code && <span className="block text-[11px] font-mono text-ink-muted">{line.code}</span>}
                                            </span>
                                            <span className={`shrink-0 font-mono tabular-nums font-semibold ${line.side === 'debit' ? 'text-ink' : 'text-ink'}`}>
                                                {line.side === 'debit' ? 'Dr' : 'Cr'} {fmt(line.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </aside>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
