import React, { useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { IconPhoto, IconUpload, IconLoader2, IconCheck, IconX } from '@tabler/icons-react';

export default function ReceiptUpload({ onOcrComplete, billId = null }) {
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef();

    const processFile = async (file) => {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            setError('Please upload an image file (JPG, PNG)');
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
                }
            });

            if (response.data.success) {
                setSuccess(true);
                if (onOcrComplete) {
                    onOcrComplete(response.data.ocr_data, response.data.url, response.data.path);
                }
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to upload receipt');
        } finally {
            setUploading(false);
        }
    };

    const handleFileChange = (e) => {
        processFile(e.target.files[0]);
    };

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            processFile(e.dataTransfer.files[0]);
        }
    };

    return (
        <div className="w-full">
            <div 
                onClick={() => fileInputRef.current?.click()}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
                className={`relative border-2 border-dashed rounded-xl p-8 transition-all duration-300 cursor-pointer overflow-hidden
                    ${dragActive ? 'border-indigo-600 bg-indigo-50/50 scale-[1.02]' : 
                      uploading ? 'border-indigo-400 bg-indigo-50/30' : 
                      success ? 'border-emerald-400 bg-emerald-50/30' : 
                      error ? 'border-rose-400 bg-rose-50/30' : 
                      'border-slate-300 hover:border-indigo-400 hover:bg-slate-50'}`}
            >
                <input 
                    type="file" 
                    ref={fileInputRef} 
                    onChange={handleFileChange} 
                    className="hidden" 
                    accept="image/*"
                />

                <div className="flex flex-col items-center justify-center text-center">
                    {uploading ? (
                        <>
                            <div className="relative mb-4">
                                <IconLoader2 className="w-12 h-12 text-indigo-600 animate-spin" />
                                <div className="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-indigo-600">
                                    {progress}%
                                </div>
                            </div>
                            <h4 className="text-lg font-semibold text-slate-800">Scanning Receipt...</h4>
                            <p className="text-sm text-slate-500 mt-1">Our AI is extracting data from your receipt</p>
                            
                            {/* Scanning Animation Line */}
                            <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-indigo-500 to-transparent animate-scan" />
                        </>
                    ) : success ? (
                        <>
                            <div className="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mb-4">
                                <IconCheck className="w-6 h-6 text-emerald-600" />
                            </div>
                            <h4 className="text-lg font-semibold text-slate-800">Scan Complete!</h4>
                            <p className="text-sm text-slate-500 mt-1">Fields have been auto-populated</p>
                            <button 
                                onClick={(e) => { e.stopPropagation(); fileInputRef.current?.click(); }}
                                className="mt-4 text-xs font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                Upload another
                            </button>
                        </>
                    ) : error ? (
                        <>
                            <div className="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center mb-4">
                                <IconX className="w-6 h-6 text-rose-600" />
                            </div>
                            <h4 className="text-lg font-semibold text-rose-800">Upload Failed</h4>
                            <p className="text-sm text-rose-500 mt-1">{error}</p>
                            <button className="mt-4 px-4 py-2 bg-slate-800 text-white text-xs font-medium rounded-lg">Try Again</button>
                        </>
                    ) : (
                        <>
                            <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mb-4 group-hover:bg-indigo-100 transition-colors">
                                <IconUpload className="w-6 h-6 text-slate-600 group-hover:text-indigo-600" />
                            </div>
                            <h4 className="text-lg font-semibold text-slate-800">Upload Physical Receipt</h4>
                            <p className="text-sm text-slate-500 mt-1">Drop your receipt here or click to browse</p>
                            <div className="mt-4 flex items-center gap-2 text-xs text-slate-400">
                                <IconPhoto size={14} />
                                <span>Supports JPG, PNG (Max 10MB)</span>
                            </div>
                        </>
                    )}
                </div>
            </div>

            <style jsx>{`
                @keyframes scan {
                    0% { transform: translateY(0); opacity: 0; }
                    50% { opacity: 1; }
                    100% { transform: translateY(200px); opacity: 0; }
                }
                .animate-scan {
                    animation: scan 2s linear infinite;
                }
            `}</style>
        </div>
    );
}
