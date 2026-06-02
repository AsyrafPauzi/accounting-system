import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useMemo, useRef } from 'react';

const PROVIDER_DISABLED = 'disabled';
const PROVIDER_TESSERACT = 'tesseract';
const PROVIDER_GEMINI = 'gemini';

function ProviderCard({ option, selected, onSelect }) {
    const isSelected = selected === option.id;
    return (
        <button
            type="button"
            onClick={() => onSelect(option.id)}
            className={`text-left w-full rounded-2xl border p-5 transition-all ${
                isSelected
                    ? 'border-terracotta bg-terracotta/5 ring-1 ring-terracotta'
                    : 'border-border-warm bg-surface hover:border-ink/30'
            }`}
        >
            <div className="flex items-start gap-3">
                <span
                    className={`mt-1 h-4 w-4 rounded-full border-2 transition-all flex-shrink-0 ${
                        isSelected ? 'border-terracotta bg-terracotta' : 'border-border-warm'
                    }`}
                    aria-hidden
                />
                <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span className="font-display text-base font-medium text-ink">{option.name}</span>
                        <span className="text-eyebrow font-semibold uppercase text-ink-muted">
                            {option.tag}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-ink-muted leading-relaxed">{option.description}</p>
                </div>
            </div>
        </button>
    );
}

export default function OcrSettingsEdit({ settings, providerOptions, modelOptions, languageOptions }) {
    const initialLanguages = useMemo(
        () => (settings.tesseract_languages || 'eng+msa').split('+').filter(Boolean),
        [settings.tesseract_languages],
    );

    const { data, setData, post, processing, errors } = useForm({
        provider: settings.provider,
        gemini_api_key: '',
        gemini_model: settings.gemini_model || 'gemini-1.5-flash',
        tesseract_languages: settings.tesseract_languages || 'eng+msa',
        max_image_mb: settings.max_image_mb || 10,
        clear_api_key: false,
    });

    const [selectedLanguages, setSelectedLanguages] = useState(initialLanguages);
    const [showApiKeyInput, setShowApiKeyInput] = useState(!settings.has_gemini_api_key);

    const [testRunning, setTestRunning] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [testFile, setTestFile] = useState(null);
    const [testPreview, setTestPreview] = useState(null);
    const [dragActive, setDragActive] = useState(false);
    const testFileInputRef = useRef(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.ocr.update'), {
            preserveScroll: true,
            onSuccess: () => {
                setData('gemini_api_key', '');
                setData('clear_api_key', false);
                setShowApiKeyInput(!settings.has_gemini_api_key);
            },
        });
    };

    const toggleLanguage = (langId) => {
        const next = selectedLanguages.includes(langId)
            ? selectedLanguages.filter((l) => l !== langId)
            : [...selectedLanguages, langId];
        setSelectedLanguages(next);
        setData('tesseract_languages', next.join('+') || 'eng');
    };

    const pickTestFile = (file) => {
        if (!file) return;
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
        if (!isImage && !isPdf) {
            setTestResult({
                ok: false,
                body: { error: 'Please select an image (JPG, PNG, WebP) or PDF file.' },
            });
            return;
        }
        setTestFile(file);
        // Image previews use object URLs; PDFs just show an icon (cheaper, no embed).
        setTestPreview(isImage ? URL.createObjectURL(file) : null);
        setTestResult(null);
    };

    const handleTestDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') setDragActive(true);
        else if (e.type === 'dragleave') setDragActive(false);
    };

    const handleTestDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        const file = e.dataTransfer.files?.[0];
        if (file) pickTestFile(file);
    };

    const runTest = () => {
        setTestRunning(true);
        setTestResult(null);

        const formData = new FormData();
        if (testFile) formData.append('receipt', testFile);

        // Use window.axios (not raw fetch) — axios reads the XSRF-TOKEN cookie
        // and sends it as X-XSRF-TOKEN automatically, so the token is always in
        // sync with the current session. Raw fetch with X-CSRF-TOKEN from the
        // meta tag would go stale after session regeneration and cause 419s.
        window.axios
            .post(route('admin.ocr.test'), formData, {
                headers: { Accept: 'application/json' },
            })
            .then((r) => setTestResult({ ok: r.data.ok, body: r.data }))
            .catch((err) => {
                const body = err.response?.data ?? { error: err.message };
                setTestResult({ ok: false, body });
            })
            .finally(() => setTestRunning(false));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-eyebrow font-semibold uppercase text-terracotta">Admin · Receipt Intelligence</p>
                    <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight">
                        How receipts are read
                    </h1>
                    <p className="text-ink-muted text-sm max-w-2xl">
                        Choose how BukuCloud extracts data from uploaded receipts. This setting applies platform-wide,
                        across every tenant on this installation.
                    </p>
                </div>
            }
        >
            <Head title="OCR Settings" />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-5">
                    <div>
                        <h2 className="font-display text-xl font-medium text-ink">Provider</h2>
                        <p className="text-sm text-ink-muted mt-1">
                            Pick the engine that reads each uploaded receipt. You can switch any time.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {providerOptions.map((option) => (
                            <ProviderCard
                                key={option.id}
                                option={option}
                                selected={data.provider}
                                onSelect={(id) => setData('provider', id)}
                            />
                        ))}
                    </div>
                    {errors.provider && <p className="text-terracotta text-xs">{errors.provider}</p>}
                </div>

                {data.provider === PROVIDER_TESSERACT && (
                    <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                        <div>
                            <h2 className="font-display text-xl font-medium text-ink">Tesseract languages</h2>
                            <p className="text-sm text-ink-muted mt-1">
                                Pick the languages your receipts are printed in. Each adds a small accuracy boost.
                                Requires the matching <code className="font-mono text-xs px-1 py-0.5 bg-cream rounded">tesseract-ocr-{'{lang}'}</code> package on the server.
                            </p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {languageOptions.map((lang) => (
                                <label
                                    key={lang.id}
                                    className={`flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-colors ${
                                        selectedLanguages.includes(lang.id)
                                            ? 'border-terracotta bg-terracotta/5'
                                            : 'border-border-warm hover:bg-cream'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedLanguages.includes(lang.id)}
                                        onChange={() => toggleLanguage(lang.id)}
                                        className="rounded border-border-warm text-terracotta focus:ring-terracotta"
                                    />
                                    <span className="text-sm text-ink">{lang.name}</span>
                                    <span className="ml-auto text-xs font-mono text-ink-muted">{lang.id}</span>
                                </label>
                            ))}
                        </div>
                        <p className="text-xs text-ink-muted">
                            Active codes: <span className="font-mono">{data.tesseract_languages || 'eng'}</span>
                        </p>
                        {errors.tesseract_languages && (
                            <p className="text-terracotta text-xs">{errors.tesseract_languages}</p>
                        )}
                    </div>
                )}

                {data.provider === PROVIDER_GEMINI && (
                    <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                        <div>
                            <h2 className="font-display text-xl font-medium text-ink">Gemini configuration</h2>
                            <p className="text-sm text-ink-muted mt-1">
                                Get an API key at{' '}
                                <a
                                    href="https://aistudio.google.com/apikey"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-terracotta underline hover:no-underline"
                                >
                                    aistudio.google.com/apikey
                                </a>
                                . The key is encrypted at rest.
                            </p>
                        </div>

                        <div>
                            <label className="block text-eyebrow font-semibold uppercase text-ink-muted">API Key</label>
                            {settings.has_gemini_api_key && !showApiKeyInput && (
                                <div className="mt-1.5 flex items-center gap-3 rounded-xl border border-border-warm bg-cream px-4 py-2.5">
                                    <span className="font-mono text-sm text-ink flex-1">
                                        {settings.gemini_api_key_masked || '••••••••••••'}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setShowApiKeyInput(true)}
                                        className="text-xs font-semibold text-terracotta hover:text-terracotta-dark"
                                    >
                                        Change
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setData('clear_api_key', true);
                                            setShowApiKeyInput(true);
                                        }}
                                        className="text-xs font-semibold text-ink-muted hover:text-ink"
                                    >
                                        Clear
                                    </button>
                                </div>
                            )}
                            {showApiKeyInput && (
                                <input
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder="AIzaSy••••••••••••••••••••••••"
                                    value={data.gemini_api_key}
                                    onChange={(e) => setData('gemini_api_key', e.target.value)}
                                    className="mt-1.5 w-full rounded-xl border-border-warm bg-surface text-sm font-mono text-ink placeholder-ink-muted/60 focus:border-terracotta focus:ring-terracotta"
                                />
                            )}
                            {errors.gemini_api_key && (
                                <p className="text-terracotta text-xs mt-1">{errors.gemini_api_key}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-eyebrow font-semibold uppercase text-ink-muted">Model</label>
                            <select
                                value={data.gemini_model}
                                onChange={(e) => setData('gemini_model', e.target.value)}
                                className="mt-1.5 w-full rounded-xl border-border-warm bg-surface text-sm text-ink focus:border-terracotta focus:ring-terracotta"
                            >
                                {modelOptions.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                            {errors.gemini_model && <p className="text-terracotta text-xs mt-1">{errors.gemini_model}</p>}
                        </div>
                    </div>
                )}

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                    <h2 className="font-display text-xl font-medium text-ink">Upload limits</h2>
                    <div>
                        <label className="block text-eyebrow font-semibold uppercase text-ink-muted">
                            Max receipt size (MB)
                        </label>
                        <input
                            type="number"
                            min="1"
                            max="50"
                            value={data.max_image_mb}
                            onChange={(e) => setData('max_image_mb', e.target.value)}
                            className="mt-1.5 w-32 rounded-xl border-border-warm bg-surface text-sm text-ink focus:border-terracotta focus:ring-terracotta"
                        />
                        <p className="text-xs text-ink-muted mt-1">
                            Larger images are rejected at upload time. Default: 10 MB.
                        </p>
                        {errors.max_image_mb && <p className="text-terracotta text-xs mt-1">{errors.max_image_mb}</p>}
                    </div>
                </div>

                <div className="bg-surface p-6 sm:p-8 rounded-2xl border border-border-warm space-y-4">
                    <div>
                        <h2 className="font-display text-xl font-medium text-ink">Test the active provider</h2>
                        <p className="text-sm text-ink-muted mt-1">
                            Drop or pick a real receipt below and we'll run it through the currently saved provider.
                            Nothing is written to any bill — this is a dry run to verify configuration.
                            Save your changes first if you just switched providers.
                        </p>
                    </div>

                    <div
                        onClick={() => testFileInputRef.current?.click()}
                        onDragEnter={handleTestDrag}
                        onDragLeave={handleTestDrag}
                        onDragOver={handleTestDrag}
                        onDrop={handleTestDrop}
                        className={`border-2 border-dashed rounded-xl p-6 cursor-pointer transition-colors ${
                            dragActive
                                ? 'border-terracotta bg-terracotta/5'
                                : testFile
                                ? 'border-forest/40 bg-forest/5'
                                : 'border-border-warm hover:border-ink/30 hover:bg-cream'
                        }`}
                    >
                        <input
                            ref={testFileInputRef}
                            type="file"
                            accept="image/*,application/pdf"
                            className="hidden"
                            onChange={(e) => pickTestFile(e.target.files?.[0])}
                        />
                        {testFile ? (
                            <div className="flex items-start gap-4">
                                {testPreview ? (
                                    <img
                                        src={testPreview}
                                        alt="Test receipt"
                                        className="h-24 w-24 object-cover rounded-lg border border-border-warm flex-shrink-0"
                                    />
                                ) : (
                                    <div className="h-24 w-24 rounded-lg border border-border-warm bg-cream flex flex-col items-center justify-center flex-shrink-0">
                                        <svg className="w-8 h-8 text-terracotta" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span className="text-eyebrow font-semibold text-ink-muted mt-1">PDF</span>
                                    </div>
                                )}
                                <div className="flex-1 min-w-0">
                                    <p className="text-eyebrow font-semibold uppercase text-forest">Ready to test</p>
                                    <p className="font-mono text-sm text-ink truncate mt-1">{testFile.name}</p>
                                    <p className="text-xs text-ink-muted mt-0.5">
                                        {(testFile.size / 1024).toFixed(1)} KB · click again to swap
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-2">
                                <p className="text-sm font-medium text-ink">Drop a receipt here</p>
                                <p className="text-xs text-ink-muted mt-1">
                                    or click to browse · JPG / PNG / WebP / PDF · max 10 MB
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={runTest}
                            disabled={testRunning || !testFile}
                            className="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-terracotta hover:bg-terracotta-dark dark:hover:bg-terracotta-light disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            {testRunning ? 'Running…' : 'Run test'}
                        </button>
                        {testFile && (
                            <button
                                type="button"
                                onClick={() => {
                                    setTestFile(null);
                                    setTestPreview(null);
                                    setTestResult(null);
                                }}
                                className="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-ink-muted hover:text-ink hover:bg-surface-alt transition-colors"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    {testResult && (
                        <div
                            className={`rounded-xl border p-4 ${
                                testResult.ok
                                    ? 'border-forest/30 bg-forest/5'
                                    : 'border-terracotta/30 bg-terracotta/5'
                            }`}
                        >
                            <p
                                className={`text-eyebrow font-semibold uppercase mb-2 ${
                                    testResult.ok ? 'text-forest' : 'text-terracotta'
                                }`}
                            >
                                {testResult.ok ? `Success · provider: ${testResult.body?.provider || '—'}` : 'Failed'}
                            </p>
                            {testResult.ok && testResult.body?.result ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                    {[
                                        ['Vendor', testResult.body.result.vendor_name],
                                        ['Date', testResult.body.result.bill_date],
                                        ['Subtotal', testResult.body.result.subtotal],
                                        ['Tax', testResult.body.result.tax_amount],
                                        ['Total', testResult.body.result.total_amount],
                                        ['Currency', testResult.body.result.currency],
                                        ['Reference', testResult.body.result.reference],
                                        ['Confidence', testResult.body.result.confidence],
                                    ].map(([label, value]) => (
                                        <div key={label} className="bg-surface rounded-lg px-3 py-2 border border-border-warm">
                                            <p className="text-eyebrow font-semibold uppercase text-ink-muted">{label}</p>
                                            <p className="text-sm font-mono text-ink mt-0.5">
                                                {value === null || value === undefined || value === ''
                                                    ? <span className="text-ink-muted/50">—</span>
                                                    : String(value)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            {testResult.ok && Array.isArray(testResult.body?.result?.items) && testResult.body.result.items.length > 0 && (
                                <div className="bg-surface rounded-lg border border-border-warm overflow-hidden mb-3">
                                    <div className="px-3 py-2 border-b border-border-warm bg-cream/50">
                                        <p className="text-eyebrow font-semibold uppercase text-ink-muted">
                                            Line items ({testResult.body.result.items.length})
                                        </p>
                                    </div>
                                    <table className="w-full text-xs">
                                        <thead className="bg-cream/30">
                                            <tr className="text-left text-ink-muted">
                                                <th className="px-3 py-1.5 font-semibold">Description</th>
                                                <th className="px-3 py-1.5 font-semibold text-right w-16">Qty</th>
                                                <th className="px-3 py-1.5 font-semibold text-right w-24">Unit</th>
                                                <th className="px-3 py-1.5 font-semibold text-right w-24">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody className="font-mono text-ink">
                                            {testResult.body.result.items.map((item, idx) => (
                                                <tr key={idx} className="border-t border-border-warm/50">
                                                    <td className="px-3 py-1.5 break-words">{item.description}</td>
                                                    <td className="px-3 py-1.5 text-right">
                                                        {item.quantity ?? <span className="text-ink-muted/50">—</span>}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-right">
                                                        {item.unit_amount != null ? Number(item.unit_amount).toFixed(2) : <span className="text-ink-muted/50">—</span>}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-right">
                                                        {Number(item.amount ?? 0).toFixed(2)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <details className="text-xs">
                                <summary className="cursor-pointer text-ink-muted hover:text-ink font-medium">
                                    Raw response
                                </summary>
                                <pre className="text-xs font-mono text-ink whitespace-pre-wrap break-words mt-2 bg-cream p-3 rounded-lg">
                                    {JSON.stringify(testResult.body, null, 2)}
                                </pre>
                            </details>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold text-white bg-terracotta hover:bg-terracotta-dark dark:hover:bg-terracotta-light disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Saving…' : 'Save changes'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
