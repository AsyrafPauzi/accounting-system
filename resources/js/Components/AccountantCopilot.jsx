import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function inlineMarkdown(text) {
    const escaped = escapeHtml(text);
    return escaped
        .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-ink">$1</strong>')
        .replace(/`([^`]+)`/g, '<code class="font-mono text-[11px] bg-surface-alt px-1 rounded">$1</code>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-terracotta underline font-semibold break-all">$1</a>')
        .replace(/(^|[\s(])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener noreferrer" class="text-terracotta underline break-all">$2</a>');
}

function ChatMarkdown({ text }) {
    const lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
    const blocks = [];
    let list = null;

    const flushList = () => {
        if (!list) return;
        blocks.push(list);
        list = null;
    };

    lines.forEach((raw) => {
        const line = raw.trimEnd();
        const bullet = line.match(/^[-*]\s+(.+)$/);
        const numbered = line.match(/^\d+\.\s+(.+)$/);
        if (bullet || numbered) {
            const kind = bullet ? 'ul' : 'ol';
            if (!list || list.kind !== kind) {
                flushList();
                list = { kind, items: [] };
            }
            list.items.push((bullet || numbered)[1]);
            return;
        }
        flushList();
        if (line.trim() === '') {
            return;
        }
        const heading = line.match(/^\*\*(.+)\*\*$/);
        if (heading && !line.includes(' · ')) {
            blocks.push({ kind: 'h', text: heading[1] });
            return;
        }
        blocks.push({ kind: 'p', text: line });
    });
    flushList();

    return (
        <div className="space-y-2 text-[13px] leading-relaxed text-ink">
            {blocks.map((block, i) => {
                if (block.kind === 'h') {
                    return (
                        <p key={i} className="text-eyebrow font-semibold uppercase tracking-wide text-ink-muted pt-1">
                            {block.text}
                        </p>
                    );
                }
                if (block.kind === 'ul' || block.kind === 'ol') {
                    const Tag = block.kind;
                    const isItems = blocks[i - 1]?.kind === 'h' && /item/i.test(blocks[i - 1]?.text || '');
                    return (
                        <Tag key={i} className={`space-y-1.5 ${block.kind === 'ol' ? 'list-decimal pl-4' : 'list-none'} `}>
                            {block.items.map((item, j) => (
                                <li
                                    key={j}
                                    className={
                                        isItems
                                            ? 'rounded-lg bg-surface px-2.5 py-1.5 border border-border-warm/70'
                                            : 'flex gap-2'
                                    }
                                >
                                    {!isItems && block.kind === 'ul' && (
                                        <span className="mt-1.5 h-1.5 w-1.5 rounded-full bg-terracotta flex-shrink-0" />
                                    )}
                                    <span dangerouslySetInnerHTML={{ __html: inlineMarkdown(item) }} />
                                </li>
                            ))}
                        </Tag>
                    );
                }
                return <p key={i} dangerouslySetInnerHTML={{ __html: inlineMarkdown(block.text) }} />;
            })}
        </div>
    );
}

function money(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return `RM${n.toFixed(2)}`;
}

function PendingCard({ pending, busy, onConfirm, onCancel }) {
    const payload = pending.payload || {};
    const items = Array.isArray(payload.items) ? payload.items : [];

    return (
        <div className="rounded-2xl border border-mustard/50 bg-mustard/10 p-3 space-y-2">
            <p className="text-eyebrow font-semibold uppercase text-ink-muted">{pending.risk} · confirm</p>
            <p className="text-sm font-medium text-ink">{pending.summary}</p>
            {payload.customer_id && (
                <p className="text-xs text-ink-muted">Customer #{payload.customer_id}</p>
            )}
            {(payload.vendor_name || payload.supplier_name) && (
                <p className="text-xs text-ink-muted">Supplier: {payload.vendor_name || payload.supplier_name}</p>
            )}
            {items.length > 0 && (
                <div className="overflow-hidden rounded-xl border border-border-warm bg-surface">
                    <p className="px-2.5 py-1.5 text-eyebrow font-semibold uppercase text-ink-muted border-b border-border-warm">Items</p>
                    <table className="w-full text-xs">
                        <thead className="text-ink-muted">
                            <tr>
                                <th className="text-left font-medium px-2.5 py-1">Description</th>
                                <th className="text-right font-medium px-2 py-1 w-10">Qty</th>
                                <th className="text-right font-medium px-2.5 py-1 w-20">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item, i) => (
                                <tr key={i} className="border-t border-border-warm/60">
                                    <td className="px-2.5 py-1.5 text-ink">{item.description || 'Item'}</td>
                                    <td className="px-2 py-1.5 text-right tabular-nums">{item.quantity ?? 1}</td>
                                    <td className="px-2.5 py-1.5 text-right tabular-nums">
                                        {money(item.unit_price ?? item.unit_amount ?? item.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <div className="flex gap-2">
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => onConfirm(pending.id)}
                    className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-forest text-white disabled:opacity-50"
                >
                    Confirm
                </button>
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => onCancel(pending.id)}
                    className="px-3 py-1.5 rounded-lg text-xs font-semibold border border-border-warm text-ink"
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}

export default function AccountantCopilot() {
    const { auth, copilot_credits: sharedCredits } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const planPermissions = auth?.planPermissions ?? {};
    const isAdmin = auth?.user?.role_name === 'super-admin';
    const canUse = isAdmin || (permissions.includes('copilot.use') && planPermissions['copilot.use']);

    const [open, setOpen] = useState(false);
    const [threadId, setThreadId] = useState(null);
    const [messages, setMessages] = useState([]);
    const [pending, setPending] = useState([]);
    const [credits, setCredits] = useState(sharedCredits || null);
    const [input, setInput] = useState('');
    const [file, setFile] = useState(null);
    const [filePreview, setFilePreview] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const bottomRef = useRef(null);
    const fileRef = useRef(null);

    useEffect(() => {
        if (sharedCredits) setCredits(sharedCredits);
    }, [sharedCredits]);

    const clearReceipt = () => {
        if (filePreview) {
            URL.revokeObjectURL(filePreview);
        }
        setFile(null);
        setFilePreview(null);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const pickReceipt = (picked) => {
        if (!picked) return;
        if (filePreview) {
            URL.revokeObjectURL(filePreview);
        }
        setFile(picked);
        setFilePreview(picked.type?.startsWith('image/') ? URL.createObjectURL(picked) : null);
    };

    const applyPayload = (data) => {
        if (data.thread_id) setThreadId(data.thread_id);
        if (Array.isArray(data.messages)) setMessages(data.messages);
        if (Array.isArray(data.pending_actions)) setPending(data.pending_actions);
        if (data.credits) setCredits(data.credits);
    };

    const load = useCallback(async () => {
        if (!canUse) return;
        try {
            const { data } = await axios.get(route('copilot.show'));
            applyPayload(data);
        } catch {
            // First send creates the thread.
        }
    }, [canUse]);

    useEffect(() => {
        if (open) load();
    }, [open, load]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, pending, open]);

    if (!canUse) return null;

    const metering = credits?.metering === true;
    const remaining = metering ? Number(credits?.remaining ?? 0) : null;
    const outOfCredits = metering && remaining !== null && remaining < 1;

    const send = async (e) => {
        e?.preventDefault();
        if (busy || outOfCredits) return;
        const text = input.trim();
        if (!text && !file) return;

        setBusy(true);
        setError(null);
        const form = new FormData();
        form.append('message', text);
        if (threadId) form.append('thread_id', String(threadId));
        if (file) form.append('image', file);

        try {
            const { data } = await axios.post(route('copilot.chat'), form);
            applyPayload(data);
            setInput('');
            clearReceipt();
        } catch (err) {
            if (err.response?.data?.credits) setCredits(err.response.data.credits);
            setError(err.response?.data?.error || 'Could not reach Accountant copilot.');
        } finally {
            setBusy(false);
        }
    };

    const confirm = async (id) => {
        setBusy(true);
        setError(null);
        try {
            await axios.post(route('copilot.confirm', id));
            await load();
        } catch (err) {
            setError(err.response?.data?.error || 'Confirm failed.');
        } finally {
            setBusy(false);
        }
    };

    const cancel = async (id) => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await axios.post(route('copilot.cancel', id));
            applyPayload(data);
        } catch (err) {
            setError(err.response?.data?.error || 'Cancel failed.');
        } finally {
            setBusy(false);
        }
    };

    const clearChat = async () => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await axios.post(route('copilot.clear'), { thread_id: threadId });
            applyPayload(data);
            setInput('');
            clearReceipt();
        } catch (err) {
            setError(err.response?.data?.error || 'Could not clear chat.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="fixed z-40 bottom-20 right-4 lg:bottom-6 lg:right-6">
            {!open && (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="h-12 w-12 rounded-2xl bg-terracotta text-white shadow-lg hover:bg-terracotta-dark flex items-center justify-center"
                    aria-label="Open Accountant copilot"
                >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.938L3 20l1.146-3.437A7.5 7.5 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </button>
            )}

            {open && (
                <div className="w-[22rem] sm:w-[24rem] h-[min(32rem,70vh)] bg-surface border border-border-warm rounded-2xl shadow-2xl flex flex-col overflow-hidden">
                    <header className="px-3 py-2.5 border-b border-border-warm flex items-center gap-2 bg-cream/60">
                        <div className="h-8 w-8 rounded-xl bg-terracotta text-white flex items-center justify-center flex-shrink-0">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.938L3 20l1.146-3.437A7.5 7.5 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-display text-sm text-ink leading-tight">Accountant copilot</p>
                            <p className="text-[10px] text-ink-muted truncate">
                                {metering
                                    ? `${remaining} credit${remaining === 1 ? '' : 's'} left`
                                    : 'Writes wait for Confirm'}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={clearChat}
                            disabled={busy}
                            className="px-2 py-1 rounded-lg text-[11px] font-semibold text-ink-muted hover:text-ink hover:bg-surface-alt disabled:opacity-50"
                        >
                            Clear
                        </button>
                        <button type="button" onClick={() => setOpen(false)} className="p-1.5 rounded-lg text-ink-muted hover:bg-surface-alt" aria-label="Close">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </header>

                    <div className="flex-1 overflow-y-auto px-3 py-3 space-y-2.5 bg-cream/30">
                        {messages.length === 0 && (
                            <p className="text-xs text-ink-muted">
                                Tanya overdue invoices, aging, atau draf invoice. Tindakan tulis perlu Confirm.
                            </p>
                        )}
                        {messages.map((m) => (
                            <div
                                key={m.id}
                                className={`max-w-[90%] rounded-2xl px-3 py-2 ${
                                    m.role === 'user'
                                        ? 'ml-auto bg-terracotta text-white'
                                        : 'mr-auto bg-surface border border-border-warm text-ink'
                                }`}
                            >
                                {m.role === 'user' ? (
                                    <p className="text-[13px] leading-relaxed whitespace-pre-wrap">{m.content}</p>
                                ) : (
                                    <ChatMarkdown text={m.content} />
                                )}
                            </div>
                        ))}
                        {pending.filter((p) => p.status === 'pending').map((p) => (
                            <PendingCard
                                key={p.id}
                                pending={p}
                                busy={busy}
                                onConfirm={confirm}
                                onCancel={cancel}
                            />
                        ))}
                        <div ref={bottomRef} />
                    </div>

                    {error && <p className="px-3 pb-1 text-[11px] text-terracotta">{error}</p>}
                    {outOfCredits && (
                        <div className="px-3 pb-2 text-[11px] text-ink">
                            Out of credits.{' '}
                            <a href={route('settings.plan.index')} className="font-semibold text-terracotta underline">
                                Buy more in Plan &amp; Usage
                            </a>
                        </div>
                    )}

                    <form onSubmit={send} className="border-t border-border-warm p-2.5 space-y-1.5 bg-surface">
                        {file && (
                            <div className="flex items-center gap-2 rounded-xl border border-border-warm bg-cream px-2 py-1.5">
                                {filePreview ? (
                                    <img src={filePreview} alt="" className="h-10 w-10 rounded-lg object-cover border border-border-warm flex-shrink-0" />
                                ) : (
                                    <div className="h-10 w-10 rounded-lg bg-surface-alt flex items-center justify-center text-[10px] font-semibold text-ink-muted flex-shrink-0">
                                        PDF
                                    </div>
                                )}
                                <p className="min-w-0 flex-1 text-[11px] text-ink truncate">{file.name}</p>
                                <button
                                    type="button"
                                    onClick={clearReceipt}
                                    className="px-2 py-1 rounded-lg text-[11px] font-semibold text-ink-muted hover:text-terracotta hover:bg-surface"
                                >
                                    Remove
                                </button>
                            </div>
                        )}
                        <textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    send(e);
                                }
                            }}
                            rows={2}
                            placeholder="Ask in BM or English…"
                            className="w-full rounded-xl border-border-warm text-sm resize-none"
                        />
                        <div className="flex items-center gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept="image/*,application/pdf"
                                className="hidden"
                                onChange={(e) => pickReceipt(e.target.files?.[0] ?? null)}
                            />
                            <button type="button" onClick={() => fileRef.current?.click()} className="text-[11px] font-semibold text-terracotta">
                                {file ? 'Change receipt' : 'Attach receipt'}
                            </button>
                            <button
                                type="submit"
                                disabled={busy || outOfCredits}
                                className="ml-auto px-3 py-1.5 rounded-xl bg-terracotta text-white text-xs font-semibold disabled:opacity-50"
                            >
                                {busy ? '…' : 'Send'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}
