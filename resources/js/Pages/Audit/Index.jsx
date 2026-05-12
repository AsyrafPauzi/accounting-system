import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { 
    IconCheck, 
    IconFlag, 
    IconEye, 
    IconSearch, 
    IconChevronRight, 
    IconCalendar, 
    IconChecklist,
    IconAlertCircle,
    IconFileText,
    IconExternalLink
} from '@tabler/icons-react';
import Modal from '@/Components/Modal';

export default function Index({ auth, bills, filters, stats }) {
    const [selectedBill, setSelectedBill] = useState(null);
    const [isProcessing, setIsProcessing] = useState(false);

    const handleVerify = (id) => {
        setIsProcessing(true);
        router.post(route('audit.verify', id), {}, {
            onFinish: () => setIsProcessing(false)
        });
    };

    const handleFlag = (id) => {
        setIsProcessing(true);
        router.post(route('audit.flag', id), {}, {
            onFinish: () => setIsProcessing(false)
        });
    };

    const handleYearChange = (year) => {
        router.get(route('audit.index'), { ...filters, year }, { preserveState: true });
    };

    const handleStatusChange = (status) => {
        router.get(route('audit.index'), { ...filters, status }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">Company Audit</h2>
                        <p className="text-slate-500 text-sm">Review and verify transactions for compliance</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <select 
                            value={filters.year} 
                            onChange={(e) => handleYearChange(e.target.value)}
                            className="border-slate-200 rounded-xl text-sm font-semibold text-slate-700 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            {[2024, 2025, 2026].map(y => (
                                <option key={y} value={y}>Year {y}</option>
                            ))}
                        </select>
                        <a 
                            href={route('audit.report', { year: filters.year })}
                            target="_blank"
                            className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors shadow-sm"
                        >
                            <IconFileText size={18} />
                            Generate Summary Report
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="Company Audit" />

            <div className="space-y-8">
                {/* Stats Overview */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-indigo-50 text-indigo-600 rounded-xl">
                                <IconChecklist size={24} />
                            </div>
                            <div>
                                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Transactions</p>
                                <h3 className="text-2xl font-bold text-slate-900">{stats.total}</h3>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-amber-50 text-amber-600 rounded-xl">
                                <IconAlertCircle size={24} />
                            </div>
                            <div>
                                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Audit</p>
                                <h3 className="text-2xl font-bold text-slate-900">{stats.unaudited}</h3>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                                <IconCheck size={24} />
                            </div>
                            <div>
                                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Verified</p>
                                <h3 className="text-2xl font-bold text-slate-900">{stats.verified}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filters & Table */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                            {['all', 'unaudited', 'verified', 'flagged'].map(s => (
                                <button
                                    key={s}
                                    onClick={() => handleStatusChange(s)}
                                    className={`px-4 py-1.5 rounded-lg text-xs font-bold capitalize transition-all
                                        ${filters.status === s ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-slate-200'}`}
                                >
                                    {s}
                                </button>
                            ))}
                        </div>
                        <div className="relative">
                            <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
                            <input 
                                type="text" 
                                placeholder="Search by number or vendor..." 
                                className="pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 w-full sm:w-64"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 bg-slate-50/80">
                                    <th className="px-6 py-4">Transaction</th>
                                    <th className="px-6 py-4">Vendor / Description</th>
                                    <th className="px-6 py-4">Amount</th>
                                    <th className="px-6 py-4">Receipt</th>
                                    <th className="px-6 py-4">Audit Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {bills.data.map((bill) => (
                                    <tr key={bill.id} className="hover:bg-slate-50/80 transition-colors group">
                                        <td className="px-6 py-4">
                                            <div className="font-bold text-slate-900">{bill.bill_number}</div>
                                            <div className="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                <IconCalendar size={12} />
                                                {new Date(bill.bill_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-semibold text-slate-700">{bill.supplier?.name || 'General Expense'}</div>
                                            <div className="text-xs text-slate-400 truncate max-w-[200px]">{bill.private_notes || bill.reference || 'No notes'}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-mono font-bold text-slate-800">RM {parseFloat(bill.total_amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            {bill.receipt_path ? (
                                                <button 
                                                    onClick={() => setSelectedBill(bill)}
                                                    className="flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-medium group/receipt"
                                                >
                                                    <div className="p-1.5 bg-indigo-50 rounded-lg group-hover/receipt:bg-indigo-100 transition-colors">
                                                        <IconFileText size={16} />
                                                    </div>
                                                    <span className="text-xs underline decoration-indigo-200 underline-offset-4">View Receipt</span>
                                                </button>
                                            ) : (
                                                <span className="text-slate-300 text-xs italic">No receipt</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                                                ${bill.audit_status === 'verified' ? 'bg-emerald-100 text-emerald-700' : 
                                                  bill.audit_status === 'flagged' ? 'bg-rose-100 text-rose-700' : 
                                                  'bg-slate-100 text-slate-600'}`}>
                                                {bill.audit_status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link 
                                                    href={route('bills.edit', bill.id)}
                                                    className="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                                                    title="View Bill Details"
                                                >
                                                    <IconEye size={18} />
                                                </Link>
                                                {bill.audit_status !== 'verified' && (
                                                    <button 
                                                        onClick={() => handleVerify(bill.id)}
                                                        disabled={isProcessing}
                                                        className="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                                                        title="Verify Transaction"
                                                    >
                                                        <IconCheck size={18} />
                                                    </button>
                                                )}
                                                {bill.audit_status === 'unaudited' && (
                                                    <button 
                                                        onClick={() => handleFlag(bill.id)}
                                                        disabled={isProcessing}
                                                        className="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all"
                                                        title="Flag for Review"
                                                    >
                                                        <IconFlag size={18} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {bills.data.length === 0 && (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-20 text-center">
                                            <div className="flex flex-col items-center">
                                                <IconChecklist size={48} className="text-slate-200 mb-4" />
                                                <p className="text-slate-500 font-medium">No transactions found for the selected filter.</p>
                                                <button onClick={() => handleStatusChange('all')} className="mt-2 text-indigo-600 text-sm font-semibold hover:underline">Clear filters</button>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Receipt Modal */}
            <Modal show={selectedBill !== null} onClose={() => setSelectedBill(null)} maxWidth="2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h3 className="text-xl font-bold text-slate-900">Receipt Details</h3>
                            <p className="text-sm text-slate-500">{selectedBill?.bill_number} · {selectedBill?.supplier?.name || 'General Expense'}</p>
                        </div>
                        <button 
                            onClick={() => setSelectedBill(null)}
                            className="p-2 text-slate-400 hover:text-slate-600 rounded-xl"
                        >
                            <IconX size={24} />
                        </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div className="rounded-xl overflow-hidden border border-slate-200 bg-slate-50 aspect-[3/4] flex items-center justify-center">
                            {selectedBill?.receipt_url?.match(/\.(jpeg|jpg|gif|png)$/) || selectedBill?.receipt_path?.match(/\.(jpeg|jpg|gif|png)$/) ? (
                                <div className="relative group w-full h-full">
                                    <img src={selectedBill?.receipt_url || selectedBill?.receipt_path} alt="Receipt" className="w-full h-full object-contain" />
                                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/5">
                                        <a 
                                            href={selectedBill?.receipt_url || selectedBill?.receipt_path} 
                                            target="_blank" 
                                            className="bg-white/90 backdrop-blur p-2 rounded-lg shadow-xl text-xs font-bold text-slate-800 flex items-center gap-2"
                                        >
                                            <IconExternalLink size={14} /> View Full Size
                                        </a>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center text-slate-400">
                                    <IconFileText size={64} />
                                    <span className="text-sm font-medium mt-4">Document Attachment</span>
                                    <a 
                                        href={selectedBill?.receipt_url || selectedBill?.receipt_path} 
                                        target="_blank" 
                                        className="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold flex items-center gap-2 hover:bg-indigo-700 transition-colors"
                                    >
                                        <IconExternalLink size={14} />
                                        Open in New Tab
                                    </a>
                                </div>
                            )}
                        </div>

                        <div className="space-y-6">
                            <div className="p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div className="space-y-3">
                                    <div className="flex justify-between">
                                        <span className="text-xs text-slate-500">Total Amount</span>
                                        <span className="text-sm font-bold text-slate-800">RM {parseFloat(selectedBill?.total_amount).toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-xs text-slate-500">Date</span>
                                        <span className="text-sm font-bold text-slate-800">
                                            {selectedBill?.bill_date ? new Date(selectedBill.bill_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-xs text-slate-500">Reference</span>
                                        <span className="text-sm font-bold text-slate-800">{selectedBill?.reference || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <button 
                                    onClick={() => { handleVerify(selectedBill.id); setSelectedBill(null); }}
                                    className="w-full py-3 bg-emerald-600 text-white rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-100"
                                >
                                    <IconCheck size={20} />
                                    Verify Transaction
                                </button>
                                <button 
                                    onClick={() => { handleFlag(selectedBill.id); setSelectedBill(null); }}
                                    className="w-full py-3 bg-white text-rose-600 border border-rose-200 rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-rose-50 transition-colors"
                                >
                                    <IconFlag size={20} />
                                    Flag for Review
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

const IconX = ({ size = 24, ...props }) => (
    <svg 
        width={size} 
        height={size} 
        viewBox="0 0 24 24" 
        fill="none" 
        stroke="currentColor" 
        strokeWidth="2" 
        strokeLinecap="round" 
        strokeLinejoin="round" 
        {...props}
    >
        <path d="M18 6L6 18M6 6l12 12" />
    </svg>
);
