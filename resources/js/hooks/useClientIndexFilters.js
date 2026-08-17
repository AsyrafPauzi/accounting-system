import { useMemo, useState } from 'react';

export default function useClientIndexFilters(rows = [], {
    searchKeys = [],
    statusKey = 'status',
    defaultPerPage = 25,
} = {}) {
    const [searchInput, setSearchInput] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [status, setStatus] = useState('');
    const [perPage, setPerPage] = useState(defaultPerPage);
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        const q = appliedSearch.trim().toLowerCase();
        return (rows || []).filter((row) => {
            const matchesStatus = !status || String(row?.[statusKey] || '') === status;
            if (!matchesStatus) return false;
            if (!q) return true;
            return searchKeys.some((key) => {
                const value = typeof key === 'function' ? key(row) : key.split('.').reduce((acc, part) => acc?.[part], row);
                return String(value || '').toLowerCase().includes(q);
            });
        });
    }, [rows, appliedSearch, status, searchKeys, statusKey]);

    const total = filtered.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage) || 1);
    const currentPage = Math.min(page, lastPage);
    const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);
    const items = filtered.slice(total === 0 ? 0 : from - 1, to);

    const apply = (overrides = {}) => {
        if (overrides.search !== undefined) {
            setSearchInput(overrides.search);
            setAppliedSearch(overrides.search);
        } else {
            setAppliedSearch(searchInput);
        }
        if (overrides.status !== undefined) setStatus(overrides.status);
        if (overrides.per_page !== undefined) setPerPage(Number(overrides.per_page) || defaultPerPage);
        setPage(overrides.page ?? 1);
    };

    return {
        searchInput,
        setSearchInput,
        status,
        perPage,
        items,
        from,
        to,
        total,
        currentPage,
        lastPage,
        apply,
    };
}
