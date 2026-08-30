import React, { useRef, useState } from 'react';
import axios from 'axios';
import { IconPhoto, IconUpload, IconLoader2, IconCheck, IconX } from '@tabler/icons-react';
import Modal from '@/Components/Modal';

const SLOT_COPY = {
    supplier_invoice: {
        title: 'Supplier invoice',
        idle: 'Drop invoice to fill the bill · JPG, PNG, WebP, or PDF · 10 MB',
        idleTitle: 'Have a supplier invoice?',
        success: 'Invoice attached',
        successHint: 'Bill fields were filled where we could read them',
        uploadingPrefix: 'Uploading invoice',
    },
    payment_receipt: {
        title: 'Payment receipt',
        idle: 'Proof of payment (optional) · JPG, PNG, WebP, or PDF · 10 MB',
        idleTitle: 'Payment receipt (optional)',
        success: 'Payment receipt attached',
        successHint: 'Stored as payment evidence — not used for OCR',
        uploadingPrefix: 'Uploading receipt',
    },
};

export default function BillDocumentUpload({
    slot = 'supplier_invoice',
    billId = null,
    requireReason = false,
    currentUrl = null,
    compact = false,
    onComplete = null,
}) {
    const copy = SLOT_COPY[slot] || SLOT_COPY.supplier_invoice;
    const runsOcr = slot === 'supplier_invoice';
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [label, setLabel] = useState('');
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(Boolean(currentUrl));
    const [dragActive, setDragActive] = useState(false);
    const [pendingFile, setPendingFile] = useState(null);
    const [reason, setReason] = useState('');
    const [showReasonModal, setShowReasonModal] = useState(false);
    const fileInputRef = useRef();

    const browse = (e) => {
        e?.stopPropagation();
        fileInputRef.current?.click();
    };

    const queueFile = (file) => {
        if (!file) return;
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
        if (!isImage && !isPdf) {
            setError('Please upload an image (JPG, PNG, WebP) or PDF file');
            return;
        }
        if (requireReason) {
            setPendingFile(file);
            setReason('');
            setShowReasonModal(true);
            return;
        }
        processFile(file, null);
    };

    const processFile = async (file, reasonText) => {
        setUploading(true);
        setError(null);
        setSuccess(false);
        setProgress(0);
        setLabel(`${copy.uploadingPrefix}…`);

        const formData = new FormData();
        formData.append('slot', slot);
        formData.append('document', file);
        if (billId) formData.append('bill_id', billId);
        if (reasonText) formData.append('reason', reasonText);

        try {
            const response = await axios.post(route('bills.upload-document'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (progressEvent) => {
                    const pct = Math.round((progressEvent.loaded * 100) / (progressEvent.total || 1));
                    const overall = Math.round(pct * 0.25);
                    setProgress(overall);
                    setLabel(`${copy.uploadingPrefix}…`);
                },
            });

            if (!response.data.success) {
                setUploading(false);
                return;
            }

            const applyOcr = response.data.apply_ocr !== false;
            const pollPath = response.data.path;
            const pollUrl = response.data.url;

            if (response.data.status === 'pending' && runsOcr) {
                const startedAt = Date.now();
                setProgress(response.data.progress ?? 25);
                setLabel(response.data.label || 'Waiting for scan…');

                const pollInterval = setInterval(async () => {
                    try {
                        const pollResponse = await axios.get(route('bills.ocr-status'), {
                            params: { path: pollPath, started_at: startedAt },
                        });
                        if (typeof pollResponse.data.progress === 'number') {
                            setProgress(pollResponse.data.progress);
                        }
                        if (pollResponse.data.label) {
                            setLabel(pollResponse.data.label);
                        }

                        if (pollResponse.data.status === 'completed') {
                            clearInterval(pollInterval);
                            setProgress(100);
                            setSuccess(true);
                            setUploading(false);
                            onComplete?.({
                                path: pollPath,
                                url: pollUrl,
                                ocrData: pollResponse.data.ocr_data,
                                applyOcr,
                            });
                        } else if (pollResponse.data.status === 'failed') {
                            clearInterval(pollInterval);
                            setError(pollResponse.data.error || 'OCR scanning failed. Please enter details manually.');
                            setUploading(false);
                            onComplete?.({
                                path: pollPath,
                                url: pollUrl,
                                ocrData: null,
                                applyOcr: false,
                            });
                        }
                    } catch (pollErr) {
                        clearInterval(pollInterval);
                        setError(pollErr.response?.data?.message || 'Failed to retrieve OCR scan status');
                        setUploading(false);
                    }
                }, 1500);
            } else {
                setProgress(100);
                setSuccess(true);
                setUploading(false);
                onComplete?.({
                    path: pollPath,
                    url: pollUrl,
                    ocrData: response.data.ocr_data || null,
                    applyOcr: false,
                });
            }
        } catch (err) {
            const msg = err.response?.data?.errors?.reason?.[0]
                || err.response?.data?.message
                || 'Failed to upload document';
            setError(msg);
            setUploading(false);
        }
    };

    const tone = dragActive
        ? 'border-terracotta bg-surface-alt/50'
        : uploading
            ? 'border-terracotta bg-surface-alt/30'
            : success
                ? 'border-forest bg-forest/10'
                : error
                    ? 'border-terracotta bg-terracotta/10'
                    : 'border-border-warm hover:border-terracotta hover:bg-cream';

    let body;
    if (uploading) {
        body = compact ? (
            <>
                <IconLoader2 className="w-8 h-8 text-terracotta animate-spin shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-ink">{label || copy.uploadingPrefix}… {progress}%</p>
                    <div className="mt-1.5 h-1.5 rounded-full bg-cream overflow-hidden">
                        <div className="h-full bg-terracotta transition-all duration-300" style={{ width: `${progress}%` }} />
                    </div>
                </div>
            </>
        ) : (
            <>
                <div className="relative mb-4">
                    <IconLoader2 className="w-12 h-12 text-terracotta animate-spin" />
                    <div className="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-terracotta">{progress}%</div>
                </div>
                <h4 className="text-lg font-semibold text-ink">{label || `${copy.uploadingPrefix}…`}</h4>
                <div className="mt-3 w-48 h-1.5 rounded-full bg-cream overflow-hidden">
                    <div className="h-full bg-terracotta transition-all duration-300" style={{ width: `${progress}%` }} />
                </div>
            </>
        );
    } else if (success || currentUrl) {
        body = compact ? (
            <>
                <div className="w-10 h-10 bg-forest/10 rounded-full flex items-center justify-center shrink-0">
                    <IconCheck className="w-5 h-5 text-forest" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-ink">{copy.success}</p>
                    <p className="text-xs text-ink-muted">{copy.successHint}</p>
                </div>
                {currentUrl && (
                    <a href={currentUrl} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()} className="text-xs font-semibold text-ink hover:underline shrink-0">
                        View
                    </a>
                )}
                <button type="button" onClick={browse} className="text-xs font-semibold text-terracotta hover:underline shrink-0">
                    Replace
                </button>
            </>
        ) : (
            <>
                <div className="w-12 h-12 bg-forest/10 rounded-full flex items-center justify-center mb-4">
                    <IconCheck className="w-6 h-6 text-forest" />
                </div>
                <h4 className="text-lg font-semibold text-ink">{copy.success}</h4>
                <p className="text-sm text-ink-muted mt-1">{copy.successHint}</p>
                <button type="button" onClick={browse} className="mt-4 text-xs font-medium text-terracotta">Replace</button>
            </>
        );
    } else if (error) {
        body = compact ? (
            <>
                <div className="w-10 h-10 bg-terracotta/10 rounded-full flex items-center justify-center shrink-0">
                    <IconX className="w-5 h-5 text-terracotta" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-terracotta">Upload failed</p>
                    <p className="text-xs text-terracotta">{error}</p>
                </div>
                <button type="button" onClick={browse} className="text-xs font-semibold text-ink shrink-0">Try again</button>
            </>
        ) : (
            <>
                <div className="w-12 h-12 bg-terracotta/10 rounded-full flex items-center justify-center mb-4">
                    <IconX className="w-6 h-6 text-terracotta" />
                </div>
                <h4 className="text-lg font-semibold text-terracotta">Upload failed</h4>
                <p className="text-sm text-terracotta mt-1">{error}</p>
                <button type="button" onClick={browse} className="mt-4 px-4 py-2 bg-ink text-white text-xs font-medium rounded-lg">Try again</button>
            </>
        );
    } else {
        body = compact ? (
            <>
                <div className="w-10 h-10 bg-surface-alt rounded-full flex items-center justify-center shrink-0">
                    <IconUpload className="w-5 h-5 text-ink" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-ink">{copy.idleTitle}</p>
                    <p className="text-xs text-ink-muted">{copy.idle}</p>
                </div>
                <span className="inline-flex items-center px-3 py-1.5 rounded-lg border border-border-warm bg-surface text-xs font-semibold text-ink shrink-0">
                    Browse
                </span>
            </>
        ) : (
            <>
                <div className="w-12 h-12 bg-surface-alt rounded-full flex items-center justify-center mb-4">
                    <IconUpload className="w-6 h-6 text-ink" />
                </div>
                <h4 className="text-lg font-semibold text-ink">{copy.title}</h4>
                <p className="text-sm text-ink-muted mt-1">{copy.idle}</p>
                <div className="mt-4 flex items-center gap-2 text-xs text-ink-muted">
                    <IconPhoto size={14} />
                    <span>JPG, PNG, WebP, or PDF · Max 10 MB</span>
                </div>
            </>
        );
    }

    return (
        <div className="w-full">
            <div
                onClick={() => !uploading && fileInputRef.current?.click()}
                onDragEnter={(e) => { e.preventDefault(); e.stopPropagation(); setDragActive(true); }}
                onDragLeave={(e) => { e.preventDefault(); e.stopPropagation(); setDragActive(false); }}
                onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); setDragActive(true); }}
                onDrop={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setDragActive(false);
                    if (e.dataTransfer.files?.[0]) queueFile(e.dataTransfer.files[0]);
                }}
                className={`relative border-2 border-dashed rounded-xl cursor-pointer overflow-hidden transition-colors ${tone} ${compact ? 'px-4 py-3' : 'p-8'}`}
            >
                <input
                    type="file"
                    ref={fileInputRef}
                    onChange={(e) => queueFile(e.target.files[0])}
                    className="hidden"
                    accept="image/*,application/pdf"
                />
                <div className={compact ? 'flex items-center gap-3' : 'flex flex-col items-center justify-center text-center'}>
                    {body}
                </div>
            </div>

            <Modal show={showReasonModal} onClose={() => { setShowReasonModal(false); setPendingFile(null); }} maxWidth="md">
                <div className="p-6">
                    <h3 className="text-lg font-semibold text-ink">Replace {copy.title.toLowerCase()}?</h3>
                    <p className="text-sm text-ink-muted mt-1">This bill is already on the ledger. Enter a short reason for the audit trail.</p>
                    <textarea
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        rows={3}
                        className="mt-4 w-full border border-border-warm rounded-xl px-3 py-2 text-sm text-ink focus:ring-2 focus:ring-terracotta"
                        placeholder="e.g. Wrong scan, updated invoice from supplier"
                    />
                    <div className="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            className="px-4 py-2 text-sm font-semibold rounded-xl border border-border-warm"
                            onClick={() => { setShowReasonModal(false); setPendingFile(null); }}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="px-4 py-2 text-sm font-semibold rounded-xl bg-terracotta text-white disabled:opacity-50"
                            disabled={!reason.trim() || !pendingFile}
                            onClick={() => {
                                const file = pendingFile;
                                const why = reason.trim();
                                setShowReasonModal(false);
                                setPendingFile(null);
                                processFile(file, why);
                            }}
                        >
                            Replace
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
