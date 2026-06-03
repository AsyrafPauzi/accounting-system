import React, { useMemo, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Users: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
    Warning: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" /></svg>,
};

const inputClass = 'w-full border border-border-warm rounded-xl py-2.5 px-4 text-sm font-medium text-ink focus:ring-2 focus:ring-terracotta focus:border-terracotta transition-colors';
const moneyInputClass = inputClass + ' text-right font-mono font-tabular';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5';

const fmt = (n) => (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function MoneyField({ id, label, code, value, onChange, helper }) {
    return (
        <div>
            <label htmlFor={id} className={labelClass}>
                {label}
                {code && <span className="ml-2 text-ink-muted/70 normal-case font-normal tracking-normal">({code})</span>}
            </label>
            <div className="relative">
                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-mono text-ink-muted">RM</span>
                <input
                    id={id}
                    type="number"
                    step="0.01"
                    min="0"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className={moneyInputClass + ' pl-12'}
                    placeholder="0.00"
                />
            </div>
            {helper && <p className="mt-1.5 text-xs text-ink-muted">{helper}</p>}
        </div>
    );
}

export default function Run({ auth, bankAccounts = [], accounts = {}, todayIso }) {
    const { data, setData, post, processing, errors, reset } = useForm({
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

    const useSuggested = () => setData('net_pay', suggestedNetPay.toFixed(2));

    // Auto-fill Net Pay the first time the user enters a Gross — saves a click
    // for the common case where the suggestion is exactly right. They can still
    // override by typing.
    useEffect(() => {
        if (!data.net_pay && totalDebits > 0 && totalStatutory >= 0) {
            setData('net_pay', suggestedNetPay.toFixed(2));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [totalDebits, totalStatutory]);

    const submit = (e) => {
        e.preventDefault();
        post(route('payroll.store'));
    };

    const periodLabel = useMemo(() => {
        if (!data.period_date) return '';
        const d = new Date(data.period_date);
        return d.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });
    }, [data.period_date]);

    return (
        <AuthenticatedLayout user={auth?.user}>
            <Head title="Run Payroll" />

            <div className="max-w-4xl mx-auto p-4 sm:p-6">
                <Link href={route('journal.index')} className="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-ink mb-4">
                    <Icons.ChevronLeft /> Back to journals
                </Link>

                <div className="mb-6">
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-terracotta/10 text-terracotta text-xs font-semibold uppercase tracking-wider mb-3">
                        <Icons.Users /> Payroll
                    </div>
                    <h1 className="text-2xl sm:text-3xl font-display font-medium text-ink">Record a payroll run</h1>
                    <p className="text-sm text-ink-muted mt-2 max-w-2xl">
                        Key in the totals from your payroll system. We'll post a single balanced journal entry to the General Ledger using the standard Malaysian payroll account codes — no need to set up accounts first.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Period */}
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                            <h2 className="text-sm font-display font-medium text-ink">Pay period</h2>
                        </div>
                        <div className="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className={labelClass}>Period end date</label>
                                <input type="date" value={data.period_date} onChange={(e) => setData('period_date', e.target.value)} className={inputClass} required />
                                {periodLabel && <p className="mt-1.5 text-xs text-ink-muted">For {periodLabel}</p>}
                                {errors.period_date && <p className="mt-1 text-xs text-terracotta">{errors.period_date}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Description</label>
                                <input type="text" placeholder={`Payroll for ${periodLabel || 'the month'}`} value={data.description} onChange={(e) => setData('description', e.target.value)} className={inputClass} />
                                <p className="mt-1.5 text-xs text-ink-muted">Defaults to month/year if blank.</p>
                            </div>
                            <div>
                                <label className={labelClass}>Reference</label>
                                <input type="text" placeholder="PAY-2026-05" value={data.reference_number} onChange={(e) => setData('reference_number', e.target.value)} className={inputClass} />
                            </div>
                        </div>
                    </div>

                    {/* Expenses (debit) */}
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/50 flex items-center justify-between">
                            <h2 className="text-sm font-display font-medium text-ink">Cost to company (expenses)</h2>
                            <span className="text-xs font-mono font-tabular font-semibold text-ink">RM {fmt(totalDebits)}</span>
                        </div>
                        <div className="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <MoneyField id="gross" label="Gross salaries & wages" code={accounts.salaries_expense?.code} value={data.gross_salaries} onChange={(v) => setData('gross_salaries', v)} helper="Total gross pay across all employees, before any deductions." />
                            <MoneyField id="eepf" label="Employer EPF contribution" code={accounts.epf_expense?.code} value={data.employer_epf} onChange={(v) => setData('employer_epf', v)} />
                            <MoneyField id="esocso" label="Employer SOCSO contribution" code={accounts.socso_expense?.code} value={data.employer_socso} onChange={(v) => setData('employer_socso', v)} />
                            <MoneyField id="eeis" label="Employer EIS contribution" code={accounts.eis_expense?.code} value={data.employer_eis} onChange={(v) => setData('employer_eis', v)} />
                            <MoneyField id="ehrd" label="HRD Levy (optional)" code={accounts.hrd_expense?.code} value={data.employer_hrd} onChange={(v) => setData('employer_hrd', v)} helper="Only if registered with HRDF. Leave 0 otherwise." />
                        </div>
                        {errors.gross_salaries && <p className="px-6 pb-4 text-xs text-terracotta">{errors.gross_salaries}</p>}
                    </div>

                    {/* Statutory withholdings (credit) */}
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/50 flex items-center justify-between">
                            <h2 className="text-sm font-display font-medium text-ink">Statutory withholdings (to be remitted)</h2>
                            <span className="text-xs font-mono font-tabular font-semibold text-ink">RM {fmt(totalStatutory)}</span>
                        </div>
                        <div className="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <MoneyField id="epfp" label="EPF payable (employee + employer)" code={accounts.epf_payable?.code} value={data.epf_payable} onChange={(v) => setData('epf_payable', v)} helper="Combined amount you'll send to KWSP." />
                            <MoneyField id="socsop" label="SOCSO payable (employee + employer)" code={accounts.socso_payable?.code} value={data.socso_payable} onChange={(v) => setData('socso_payable', v)} />
                            <MoneyField id="eisp" label="EIS payable (employee + employer)" code={accounts.eis_payable?.code} value={data.eis_payable} onChange={(v) => setData('eis_payable', v)} />
                            <MoneyField id="pcbp" label="PCB payable (LHDN income tax)" code={accounts.pcb_payable?.code} value={data.pcb_payable} onChange={(v) => setData('pcb_payable', v)} />
                            <MoneyField id="hrdp" label="HRD Levy payable (optional)" code={accounts.hrd_payable?.code} value={data.hrd_payable} onChange={(v) => setData('hrd_payable', v)} />
                        </div>
                    </div>

                    {/* Net pay (credit Bank) */}
                    <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-border-warm bg-cream/50">
                            <h2 className="text-sm font-display font-medium text-ink">Bank payment to employees</h2>
                        </div>
                        <div className="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>Bank account</label>
                                <select value={data.bank_account_code} onChange={(e) => setData('bank_account_code', e.target.value)} className={inputClass} required>
                                    <option value="">Select bank account…</option>
                                    {bankAccounts.map((b) => (<option key={b.value} value={b.value}>{b.label}</option>))}
                                </select>
                                {errors.bank_account_code && <p className="mt-1 text-xs text-terracotta">{errors.bank_account_code}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Net pay (cash out)</label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-mono text-ink-muted">RM</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.net_pay}
                                        onChange={(e) => setData('net_pay', e.target.value)}
                                        className={moneyInputClass + ' pl-12'}
                                        placeholder="0.00"
                                        required
                                    />
                                </div>
                                <div className="mt-1.5 flex items-center justify-between text-xs text-ink-muted">
                                    <span>Suggested: RM {fmt(suggestedNetPay)}</span>
                                    {Math.abs(num(data.net_pay) - suggestedNetPay) > 0.01 && (
                                        <button type="button" onClick={useSuggested} className="font-semibold text-terracotta hover:underline">Use suggested</button>
                                    )}
                                </div>
                                {errors.net_pay && <p className="mt-1 text-xs text-terracotta">{errors.net_pay}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Balance check */}
                    <div className={`rounded-2xl border-2 p-5 transition-colors ${balanced ? 'border-forest/40 bg-forest/5' : 'border-terracotta/40 bg-terracotta/5'}`}>
                        <div className="flex items-start gap-3">
                            <div className={`mt-0.5 ${balanced ? 'text-forest' : 'text-terracotta'}`}>
                                {balanced ? <Icons.Check /> : <Icons.Warning />}
                            </div>
                            <div className="flex-1">
                                <p className={`text-sm font-semibold ${balanced ? 'text-forest' : 'text-terracotta'}`}>
                                    {balanced ? 'Entry is balanced — ready to post.' : `Off by RM ${fmt(Math.abs(drift))}`}
                                </p>
                                <div className="mt-2 grid grid-cols-2 gap-4 text-xs font-mono font-tabular text-ink">
                                    <div className="flex justify-between">
                                        <span className="text-ink-muted uppercase tracking-wider text-[10px] font-semibold">Total debits</span>
                                        <span className="font-semibold">RM {fmt(totalDebits)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-ink-muted uppercase tracking-wider text-[10px] font-semibold">Total credits</span>
                                        <span className="font-semibold">RM {fmt(totalCredits)}</span>
                                    </div>
                                </div>
                                {!balanced && (
                                    <p className="mt-2 text-xs text-ink-muted">
                                        Adjust Net Pay or your statutory amounts until both totals match. The most common cause is rounding the statutory amounts in your payroll system.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
                        <Link href={route('journal.index')} className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream transition-colors">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing || !balanced || num(data.gross_salaries) <= 0}
                            className="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {processing ? 'Posting…' : 'Post payroll entry'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
