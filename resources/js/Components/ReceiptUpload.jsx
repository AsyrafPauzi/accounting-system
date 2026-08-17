import React, { useState, useRef } from 'react';
import axios from 'axios';
import { IconPhoto, IconUpload, IconLoader2, IconCheck, IconX } from '@tabler/icons-react';

export default function ReceiptUpload({ onOcrComplete, billId = null, compact = false }) {
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef();

    const processFile = async (file) => {
        if (!file) return;
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
        if (!isImage && !isPdf) {
            setError('Please upload an image (JPG, PNG, WebP) or PDF file');
            return;
        }

        setUploading(true);
        setError(null);
        setSuccess(false);
        setProgress(0);

        const formData = new FormData();
        formData.append('receipt', file);
        if (billId) formData.append('bill_id', billId);

        try {
            const response = await axios.post(route('bills.upload-receipt'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (progressEvent) => {
                    const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    setProgress(percentCompleted);
                },
            });

            if (response.data.success) {
                if (response.data.status === 'pending') {
                    const pollPath = response.data.path;
                    const pollUrl = response.data.url;
                    
                    const pollInterval = setInterval(async () => {
                        try {
                            const pollResponse = await axios.get(route('bills.ocr-status'), {
                                params: { path: pollPath }
                            });
                            
                            if (pollResponse.data.status === 'completed') {
                                clearInterval(pollInterval);
                                setSuccess(true);
                                setUploading(false);
                                if (onOcrComplete) {
                                    onOcrComplete(pollResponse.data.ocr_data, pollUrl, pollPath);
                                }
                            } else if (pollResponse.data.status === 'failed') {
                                clearInterval(pollInterval);
                                setError(pollResponse.data.error || 'OCR scanning failed. Please enter details manually.');
                                setUploading(false);
                            }
                        } catch (pollErr) {
                            clearInterval(pollInterval);
                            setError('Failed to retrieve OCR scan status');
                            setUploading(false);
                        }
                    }, 2000);
                } else {
                    setSuccess(true);
                    setUploading(false);
                    if (onOcrComplete) {
                        onOcrComplete(response.data.ocr_data, response.data.url, response.data.path);
                    }
                }
            } else {
                setUploading(false);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to upload receipt');
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

    const browse = (e) => {
        e?.stopPropagation();
        fileInputRef.current?.click();
    };

    let body;
    if (uploading) {
        body = compact ? (
            <>
                <IconLoader2 className="w-8 h-8 text-terracotta animate-spin shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-ink">Scanning receipt… {progress}%</p>
                    <p className="text-xs text-ink-muted">Filling supplier, date, and lines from the file</p>
                </div>
            </>
        ) : (
            <>
                <div className="relative mb-4">
                    <IconLoader2 className="w-12 h-12 text-terracotta animate-spin" />
                    <div className="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-terracotta">{progress}%</div>
                </div>
                <h4 className="text-lg font-semibold text-ink">Scanning receipt…</h4>
                <p className="text-sm text-ink-muted mt-1">Extracting supplier, date, and amounts</p>
            </>
        );
    } else if (success) {
        body = compact ? (
            <>
                <div className="w-10 h-10 bg-forest/10 rounded-full flex items-center justify-center shrink-0">
                    <IconCheck className="w-5 h-5 text-forest" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-ink">Receipt attached</p>
                    <p className="text-xs text-ink-muted">Bill fields were filled where we could read them</p>
                </div>
                <button type="button" onClick={browse} className="text-xs font-semibold text-terracotta hover:underline shrink-0">
                    Replace
                </button>
            </>
        ) : (
            <>
                <div className="w-12 h-12 bg-forest/10 rounded-full flex items-center justify-center mb-4">
                    <IconCheck className="w-6 h-6 text-forest" />
                </div>
                <h4 className="text-lg font-semibold text-ink">Scan complete</h4>
                <p className="text-sm text-ink-muted mt-1">Fields have been auto-populated</p>
                <button type="button" onClick={browse} className="mt-4 text-xs font-medium text-terracotta">Upload another</button>
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
                    <p className="text-sm font-semibold text-ink">Have a receipt?</p>
                    <p className="text-xs text-ink-muted">Drop it here to fill the bill · JPG, PNG, WebP, or PDF · 10 MB</p>
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
                <h4 className="text-lg font-semibold text-ink">Upload receipt</h4>
                <p className="text-sm text-ink-muted mt-1">Drop your receipt here or click to browse</p>
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
                onClick={() => fileInputRef.current?.click()}
                onDragEnter={(e) => { e.preventDefault(); e.stopPropagation(); setDragActive(true); }}
                onDragLeave={(e) => { e.preventDefault(); e.stopPropagation(); setDragActive(false); }}
                onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); setDragActive(true); }}
                onDrop={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setDragActive(false);
                    if (e.dataTransfer.files?.[0]) processFile(e.dataTransfer.files[0]);
                }}
                className={`relative border-2 border-dashed rounded-xl cursor-pointer overflow-hidden transition-colors ${tone} ${compact ? 'px-4 py-3' : 'p-8'}`}
            >
                <input
                    type="file"
                    ref={fileInputRef}
                    onChange={(e) => processFile(e.target.files[0])}
                    className="hidden"
                    accept="image/*,application/pdf"
                />
                <div className={compact ? 'flex items-center gap-3' : 'flex flex-col items-center justify-center text-center'}>
                    {body}
                </div>
            </div>
        </div>
    );
}
