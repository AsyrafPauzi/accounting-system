import React, { useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'block w-full h-10 border border-border-warm rounded-xl px-3 text-sm font-medium text-ink bg-white focus:ring-2 focus:ring-terracotta focus:border-terracotta';
const labelClass = 'block text-[10px] font-semibold text-ink-muted uppercase tracking-wider mb-1.5 leading-none';
const lineControl = 'w-full h-8 border border-border-warm rounded-lg px-1.5 text-xs font-medium text-ink bg-white focus:ring-1 focus:ring-terracotta';
const lineNumber = `${lineControl} font-mono tabular-nums text-right [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`;

const Icons = {
    ChevronLeft: () => <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>,
    Plus: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>,
    Trash: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>,
    Check: () => <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>,
};

const fmt = (n) => (Number(n) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v) => parseFloat(v) || 0;

const MONEY_FIELDS = [
    'gross_salaries', 'employer_epf', 'employer_socso', 'employer_eis', 'employer_hrd',
    'epf_payable', 'socso_payable', 'eis_payable', 'pcb_payable', 'hrd_payable', 'net_pay',
];

function pad(n) {
    return String(n).padStart(2, '0');
}

function formatLocalDate(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function endOfMonth(year, monthIndex) {
    return new Date(year, monthIndex + 1, 0);
}

function endOfNextMonth(iso) {
    const [y, m] = String(iso || '').split('-').map(Number);
    if (! y || ! m) {
        const now = new Date();
        return formatLocalDate(endOfMonth(now.getFullYear(), now.getMonth()));
    }
    return formatLocalDate(endOfMonth(y, m));
}

function periodLabel(iso) {
    if (! iso) return '';
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });
}

function blankRun({ period_date, bank_account_code }) {
    return {
        period_date: period_date || '',
        description: '',
        reference_number: '',
        bank_account_code: bank_account_code || '',
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
    };
}

function rowMoney(row) {
    const debits = num(row.gross_salaries) + num(row.employer_epf) + num(row.employer_socso) + num(row.employer_eis) + num(row.employer_hrd);
    const statutory = num(row.epf_payable) + num(row.socso_payable) + num(row.eis_payable) + num(row.pcb_payable) + num(row.hrd_payable);
    const suggestedNet = Math.max(0, debits - statutory);
    const credits = statutory + num(row.net_pay);
    const drift = Math.round((debits - credits) * 100) / 100;
    return { debits, statutory, suggestedNet, credits, drift, balanced: Math.abs(drift) < 0.005 };
}

function rowIsReady(row) {
    return Boolean(row.period_date && row.bank_account_code && num(row.gross_salaries) > 0 && rowMoney(row).balanced);
}

function MoneyField({ label, optional = false, value, onChange, error }) {
    return (
        <div className="min-w-0">
            <label className={labelClass}>
                {label}
                {optional && <span className="ml-1 normal-case tracking-normal text-ink-muted/80">opt</span>}
            </label>
            <input
                type="number"
                step="0.01"
                min="0"
                inputMode="decimal"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onFocus={(e) => e.target.select()}
                className={lineNumber}
                placeholder="0.00"
            />
            {error && <p className="mt-1 text-[11px] text-terracotta">{error}</p>}
        </div>
    );
}

function fieldError(errors, index, field) {
    return errors[`rows.${index}.${field}`];
}

export default function Batch({ auth, bankAccounts = [], todayIso }) {
    const netTouched = useRef(new Set());
    const defaultBank = bankAccounts[0]?.value || '';
    const { data, setData, post, processing, errors } = useForm({
        rows: [blankRun({ period_date: todayIso, bank_account_code: defaultBank })],
    });

    const setRows = (rows) => setData('rows', rows);

    const updateRow = (index, key, value) => {
        setRows(data.rows.map((row, i) => {
            if (i !== index) return row;
            const next = { ...row, [key]: value };
            if (MONEY_FIELDS.includes(key) && key !== 'net_pay' && ! netTouched.current.has(index)) {
                next.net_pay = rowMoney(next).suggestedNet.toFixed(2);
            }
            return next;
        }));
    };

    const setNetPay = (index, value) => {
        netTouched.current.add(index);
        updateRow(index, 'net_pay', value);
    };

    const useSuggested = (index) => {
        netTouched.current.delete(index);
        const row = data.rows[index];
        updateRow(index, 'net_pay', rowMoney(row).suggestedNet.toFixed(2));
    };

    const addRun = () => {
        const last = data.rows[data.rows.length - 1];
        const copied = last
            ? {
                ...last,
                period_date: endOfNextMonth(last.period_date),
                description: '',
                reference_number: '',
            }
            : blankRun({ period_date: todayIso, bank_account_code: defaultBank });
        setRows([...data.rows, copied]);
    };

    const removeRun = (index) => {
        const nextTouched = new Set();
        data.rows.forEach((_, i) => {
            if (i === index || ! netTouched.current.has(i)) return;
            nextTouched.add(i > index ? i - 1 : i);
        });
        netTouched.current = nextTouched;
        if (data.rows.length <= 1) {
            setRows([blankRun({ period_date: todayIso, bank_account_code: defaultBank })]);
            return;
        }
        setRows(data.rows.filter((_, i) => i !== index));
    };

    const readyCount = data.rows.filter(rowIsReady).length;
    const grandNet = data.rows.reduce((sum, row) => sum + num(row.net_pay), 0);
    const grandCost = data.rows.reduce((sum, row) => sum + rowMoney(row).debits, 0);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div className="flex items-center gap-2 min-w-0">
                        <Link href={route('payroll.create')} className="p-2 rounded-xl text-ink-muted hover:text-ink hover:bg-surface-alt transition-all duration-200 shrink-0">
                            <Icons.ChevronLeft />
                        </Link>
                        <div className="min-w-0">
                            <h2 className="text-xl sm:text-2xl font-display font-medium text-ink tracking-tight">Batch payroll</h2>
                            <p className="text-ink-muted text-sm font-medium mt-1">
                                Each card is one monthly run — copy last month, then post them together
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 shrink-0">
                        <Link
                            href={route('payroll.create')}
                            className="inline-flex items-center px-4 py-2 rounded-xl font-semibold text-ink bg-surface border border-border-warm hover:bg-cream"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            form="payroll-batch-form"
                            disabled={processing || readyCount === 0}
                            className="inline-flex items-center px-5 py-2 rounded-xl font-semibold text-white bg-terracotta hover:bg-terracotta-dark disabled:opacity-50"
                        >
                            {processing ? 'Posting…' : readyCount === 1 ? 'Post 1 run' : `Post ${readyCount} runs`}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Batch payroll" />

            <form
                id="payroll-batch-form"
                className="space-y-5 pb-8 min-w-0"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('payroll.batch.store'));
                }}
            >
                {errors.rows && (
                    <div className="rounded-xl border border-terracotta/30 bg-terracotta/5 px-4 py-3 text-sm text-terracotta">
                        {typeof errors.rows === 'string' ? errors.rows : 'Check each payroll run and try again.'}
                    </div>
                )}

                {data.rows.map((row, i) => {
                    const money = rowMoney(row);
                    const label = periodLabel(row.period_date);
                    return (
                        <article key={i} className="bg-white rounded-2xl border border-border-warm/70 shadow-[0_8px_30px_rgba(28,25,23,0.06)] overflow-hidden">
                            <div className="h-1 bg-terracotta" />

                            <div className="px-5 sm:px-7 pt-5 pb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                <div>
                                    <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink-muted">Run {i + 1}</p>
                                    <p className="mt-1 text-lg font-display font-medium text-ink">{label || 'Payroll period'}</p>
                                    <p className={`mt-1 inline-flex items-center gap-1 text-xs font-semibold ${money.balanced ? 'text-forest' : 'text-terracotta'}`}>
                                        {money.balanced ? <Icons.Check /> : null}
                                        {money.balanced ? 'Balanced' : `Off by RM ${fmt(Math.abs(money.drift))}`}
                                    </p>
                                </div>
                                <div className="flex items-start gap-4">
                                    <div className="text-left sm:text-right">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted">Net pay</p>
                                        <p className="mt-0.5 text-xl font-display font-medium tabular-nums text-ink">RM {fmt(row.net_pay)}</p>
                                        <p className="text-[11px] text-ink-muted">Cost RM {fmt(money.debits)}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeRun(i)}
                                        className="p-2 rounded-xl text-ink-muted hover:text-terracotta hover:bg-cream"
                                        aria-label={`Remove run ${i + 1}`}
                                    >
                                        <Icons.Trash />
                                    </button>
                                </div>
                            </div>

                            <div className="px-5 sm:px-7 pb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4">
                                <div className="lg:col-span-3 min-w-0">
                                    <label className={labelClass}>Period end</label>
                                    <input
                                        type="date"
                                        className={inputClass}
                                        value={row.period_date}
                                        onChange={(e) => updateRow(i, 'period_date', e.target.value)}
                                        required
                                    />
                                    {fieldError(errors, i, 'period_date') && <p className="mt-1 text-xs text-terracotta">{fieldError(errors, i, 'period_date')}</p>}
                                </div>
                                <div className="lg:col-span-3 min-w-0">
                                    <label className={labelClass}>Pay from</label>
                                    <select
                                        className={inputClass}
                                        value={row.bank_account_code}
                                        onChange={(e) => updateRow(i, 'bank_account_code', e.target.value)}
                                        required
                                    >
                                        <option value="">Select bank…</option>
                                        {bankAccounts.map((b) => (
                                            <option key={b.value} value={b.value}>{b.label}</option>
                                        ))}
                                    </select>
                                    {fieldError(errors, i, 'bank_account_code') && <p className="mt-1 text-xs text-terracotta">{fieldError(errors, i, 'bank_account_code')}</p>}
                                </div>
                                <div className="lg:col-span-4 min-w-0">
                                    <label className={labelClass}>Description</label>
                                    <input
                                        type="text"
                                        className={inputClass}
                                        value={row.description}
                                        onChange={(e) => updateRow(i, 'description', e.target.value)}
                                        placeholder={label ? `Payroll for ${label}` : 'Payroll for the month'}
                                    />
                                </div>
                                <div className="lg:col-span-2 min-w-0">
                                    <label className={labelClass}>Reference</label>
                                    <input
                                        type="text"
                                        className={inputClass}
                                        value={row.reference_number}
                                        onChange={(e) => updateRow(i, 'reference_number', e.target.value)}
                                        placeholder="PAY-2026-08"
                                    />
                                </div>
                            </div>

                            <div className="px-5 sm:px-7 pb-5">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted mb-3">Cost to company</p>
                                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                    <MoneyField label="Gross *" value={row.gross_salaries} onChange={(v) => updateRow(i, 'gross_salaries', v)} error={fieldError(errors, i, 'gross_salaries')} />
                                    <MoneyField label="Employer EPF" value={row.employer_epf} onChange={(v) => updateRow(i, 'employer_epf', v)} />
                                    <MoneyField label="Employer SOCSO" value={row.employer_socso} onChange={(v) => updateRow(i, 'employer_socso', v)} />
                                    <MoneyField label="Employer EIS" value={row.employer_eis} onChange={(v) => updateRow(i, 'employer_eis', v)} />
                                    <MoneyField label="HRD levy" optional value={row.employer_hrd} onChange={(v) => updateRow(i, 'employer_hrd', v)} />
                                </div>
                            </div>

                            <div className="px-5 sm:px-7 pb-5">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-ink-muted mb-3">Statutory to remit</p>
                                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                    <MoneyField label="EPF payable" value={row.epf_payable} onChange={(v) => updateRow(i, 'epf_payable', v)} />
                                    <MoneyField label="SOCSO payable" value={row.socso_payable} onChange={(v) => updateRow(i, 'socso_payable', v)} />
                                    <MoneyField label="EIS payable" value={row.eis_payable} onChange={(v) => updateRow(i, 'eis_payable', v)} />
                                    <MoneyField label="PCB / LHDN" value={row.pcb_payable} onChange={(v) => updateRow(i, 'pcb_payable', v)} />
                                    <MoneyField label="HRD payable" optional value={row.hrd_payable} onChange={(v) => updateRow(i, 'hrd_payable', v)} />
                                </div>
                            </div>

                            <div className="px-5 sm:px-7 py-4 border-t border-border-warm/70 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                                <div className="sm:w-56">
                                    <MoneyField label="Net pay *" value={row.net_pay} onChange={(v) => setNetPay(i, v)} error={fieldError(errors, i, 'net_pay')} />
                                    {Math.abs(num(row.net_pay) - money.suggestedNet) > 0.01 && (
                                        <button type="button" onClick={() => useSuggested(i)} className="mt-1.5 text-xs font-semibold text-terracotta hover:underline">
                                            Reset to RM {fmt(money.suggestedNet)}
                                        </button>
                                    )}
                                </div>
                                <dl className="w-full sm:w-56 space-y-1.5 text-sm shrink-0">
                                    <div className="flex justify-between gap-6 text-ink-muted">
                                        <dt>Debits</dt>
                                        <dd className="font-mono tabular-nums text-ink">RM {fmt(money.debits)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-6 text-ink-muted">
                                        <dt>Credits</dt>
                                        <dd className="font-mono tabular-nums text-ink">RM {fmt(money.credits)}</dd>
                                    </div>
                                </dl>
                            </div>
                        </article>
                    );
                })}

                <button
                    type="button"
                    onClick={addRun}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-3.5 rounded-2xl border border-dashed border-border-warm bg-white/60 text-sm font-semibold text-ink-muted hover:text-terracotta hover:border-terracotta/40 hover:bg-white"
                >
                    <Icons.Plus /> Add another run
                </button>

                <p className="text-xs text-ink-muted">
                    {readyCount} ready · RM {fmt(grandNet)} net pay · RM {fmt(grandCost)} cost to company. Posted journals appear on Manual Journals.
                </p>
            </form>
        </AuthenticatedLayout>
    );
}
