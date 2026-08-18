import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

const RANGE_CHIPS = [
    { id: 'this_month', label: 'This month' },
    { id: 'last_month', label: 'Last month' },
    { id: 'this_quarter', label: 'This quarter' },
    { id: 'ytd', label: 'Year to date' },
];

export default function ReportPeriodChips({
    action,
    preset = 'custom',
    fromKey = 'date_from',
    toKey = 'date_to',
    dateFrom = '',
    dateTo = '',
    extraChips = [],
    extraParams = {},
    mode = 'range', // 'range' | 'as_of'
    asOfKey = 'as_of_date',
    asOf = '',
}) {
    const chips = [...RANGE_CHIPS, ...extraChips];

    const [localFrom, setLocalFrom] = useState(dateFrom);
    const [localTo, setLocalTo] = useState(dateTo);

    useEffect(() => {
        setLocalFrom(dateFrom);
        setLocalTo(dateTo);
    }, [dateFrom, dateTo]);

    const visit = (next) => {
        router.get(action, { ...extraParams, ...next }, { preserveScroll: true, preserveState: false });
    };

    const tryVisitRange = (from, to) => {
        if (from && to && from <= to) {
            visit({ preset: 'custom', [fromKey]: from, [toKey]: to });
        }
    };

    const applyPreset = (id) => {
        if (mode === 'as_of') {
            visit({ preset: id, [asOfKey]: undefined });
            return;
        }
        visit({ preset: id, [fromKey]: undefined, [toKey]: undefined });
    };

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-wrap gap-1.5">
                {chips.map((chip) => (
                    <button
                        key={chip.id}
                        type="button"
                        onClick={() => applyPreset(chip.id)}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                            preset === chip.id
                                ? 'bg-terracotta text-white border-terracotta'
                                : 'bg-surface text-ink border-border-warm hover:bg-cream'
                        }`}
                    >
                        {chip.label}
                    </button>
                ))}
            </div>
            {mode === 'as_of' ? (
                <label className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                    As of
                    <input
                        type="date"
                        value={asOf}
                        onChange={(e) => visit({ preset: 'custom', [asOfKey]: e.target.value })}
                        className="mt-1 block border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink"
                    />
                </label>
            ) : (
                <>
                    <label className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                        From
                        <input
                            type="date"
                            value={localFrom}
                            onChange={(e) => {
                                const from = e.target.value;
                                setLocalFrom(from);
                                tryVisitRange(from, localTo);
                            }}
                            className="mt-1 block border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink"
                        />
                    </label>
                    <label className="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">
                        To
                        <input
                            type="date"
                            value={localTo}
                            onChange={(e) => {
                                const to = e.target.value;
                                setLocalTo(to);
                                tryVisitRange(localFrom, to);
                            }}
                            className="mt-1 block border border-border-warm rounded-xl py-2 px-3 text-sm font-medium text-ink"
                        />
                    </label>
                </>
            )}
        </div>
    );
}
