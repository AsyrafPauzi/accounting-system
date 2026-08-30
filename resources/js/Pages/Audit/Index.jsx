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

function IconX({ size = 24, ...props }) {
    return (
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
}

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
                        <h2 className="text-2xl font-display font-medium text-ink">Company Audit</h2>
                        <p className="text-ink-muted text-sm">Review and verify transactions for compliance</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <select 
                            value={filters.year} 
                            onChange={(e) => handleYearChange(e.target.value)}
                            className="border-border-warm rounded-xl text-sm font-semibold text-ink focus:ring-terracotta focus:border-terracotta"
                        >
                            {[2024, 2025, 2026].map(y => (
                                <option key={y} value={y}>Year {y}</option>
                            ))}
                        </select>
                        <a 
                            href={route('audit.report', { year: filters.year })}
                            target="_blank"
                            className="inline-flex items-center gap-2 px-4 py-2 bg-ink text-white rounded-xl text-sm font-bold hover:bg-ink transition-colors shadow-sm"
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
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-surface-alt text-terracotta rounded-xl">
                                <IconChecklist size={24} />
                            </div>
                            <div>
                                <p className="text-xs font-display font-medium text-ink-muted uppercase tracking-wider">Total Transactions</p>
                                <h3 className="text-2xl font-display font-medium text-ink">{stats.total}</h3>
                            </div>
                        </div>
                    </div>
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-mustard/15 text-mustard rounded-xl">
                                <IconAlertCircle size={24} />
                            </div>
                            <div>
                                <p className="text-xs font-display font-medium text-ink-muted uppercase tracking-wider">Pending Audit</p>
                                <h3 className="text-2xl font-display font-medium text-ink">{stats.unaudited}</h3>
                            </div>
                        </div>
                    </div>
                    <div className="bg-surface p-6 rounded-2xl border border-border-warm shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-forest/10 text-forest rounded-xl">
                                <IconCheck size={24} />
                            </div>
                            <div>
                                <p className="text-xs font-display font-medium text-ink-muted uppercase tracking-wider">Verified</p>
                                <h3 className="text-2xl font-display font-medium text-ink">{stats.verified}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filters & Table */}
                <div className="bg-surface rounded-2xl border border-border-warm shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-border-warm bg-cream/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                            {['all', 'unaudited', 'verified', 'flagged'].map(s => (
                                <button
                                    key={s}
                                    onClick={() => handleStatusChange(s)}
                                    className={`px-4 py-1.5 rounded-lg text-xs font-bold capitalize transition-all
                                        ${filters.status === s ? 'bg-terracotta text-white shadow-md ' : 'text-ink hover:bg-surface-alt'}`}
                                >
                                    {s}
                                </button>
                            ))}
                        </div>
                        <div className="relative">
                            <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted w-4 h-4" />
                            <input 
                                type="text" 
                                placeholder="Search by number or vendor..." 
                                className="pl-10 pr-4 py-2 border border-border-warm rounded-xl text-sm focus:ring-terracotta focus:border-terracotta w-full sm:w-64"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] font-display font-medium text-ink-muted uppercase tracking-widest border-b border-border-warm bg-cream/80">
                                    <th className="px-6 py-4">Transaction</th>
                                    <th className="px-6 py-4">Vendor / Description</th>
                                    <th className="px-6 py-4">Amount</th>
                                    <th className="px-6 py-4">Receipt</th>
                                    <th className="px-6 py-4">Audit Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-warm">
                                {(bills?.data ?? []).map((bill) => {
                                    const auditLabel = bill.audit_status ?? 'unaudited';
                                    return (
                                    <tr key={bill.id} className="hover:bg-cream/80 transition-colors group">
                                        <td className="px-6 py-4">
                                            <div className="font-display font-medium text-ink">{bill.bill_number}</div>
                                            <div className="text-[10px] text-ink-muted flex items-center gap-1 mt-0.5">
                                                <IconCalendar size={12} />
                                                {new Date(bill.bill_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' })}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-semibold text-ink">{bill.supplier?.name || 'General Expense'}</div>
                                            <div className="text-xs text-ink-muted truncate max-w-[200px]">{bill.private_notes || bill.reference || 'No notes'}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-mono font-display font-medium text-ink">RM {(Number(bill.total_amount) || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            {bill.supplier_invoice_path || bill.supplier_invoice_url ? (
                                                <button 
                                                    onClick={() => setSelectedBill(bill)}
                                                    className="flex items-center gap-2 text-terracotta hover:text-ink font-medium group/receipt"
                                                >
                                                    <div className="p-1.5 bg-surface-alt rounded-lg group-hover/receipt:bg-surface-alt transition-colors">
                                                        <IconFileText size={16} />
                                                    </div>
                                                    <span className="text-xs underline decoration-indigo-200 underline-offset-4">View invoice</span>
                                                </button>
                                            ) : (
                                                <span className="text-ink-muted text-xs italic">No invoice</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                                                ${auditLabel === 'verified' ? 'bg-forest/10 text-forest' :
                                                  auditLabel === 'flagged' ? 'bg-terracotta/10 text-terracotta' :
                                                  'bg-surface-alt text-ink'}`}>
                                                {auditLabel}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link 
                                                    href={route('bills.edit', bill.id)}
                                                    className="p-2 text-ink-muted hover:text-terracotta hover:bg-surface-alt rounded-lg transition-all"
                                                    title="View Bill Details"
                                                >
                                                    <IconEye size={18} />
                                                </Link>
                                                {auditLabel !== 'verified' && (
                                                    <button 
                                                        onClick={() => handleVerify(bill.id)}
                                                        disabled={isProcessing}
                                                        className="p-2 text-ink-muted hover:text-forest hover:bg-forest/10 rounded-lg transition-all"
                                                        title="Verify Transaction"
                                                    >
                                                        <IconCheck size={18} />
                                                    </button>
                                                )}
                                                {auditLabel === 'unaudited' && (
                                                    <button 
                                                        onClick={() => handleFlag(bill.id)}
                                                        disabled={isProcessing}
                                                        className="p-2 text-ink-muted hover:text-terracotta hover:bg-terracotta/10 rounded-lg transition-all"
                                                        title="Flag for Review"
                                                    >
                                                        <IconFlag size={18} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                    );
                                })}
                                {(bills?.data ?? []).length === 0 && (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-20 text-center">
                                            <div className="flex flex-col items-center">
                                                <IconChecklist size={48} className="text-ink-muted mb-4" />
                                                <p className="text-ink-muted font-medium">No transactions found for the selected filter.</p>
                                                <button onClick={() => handleStatusChange('all')} className="mt-2 text-terracotta text-sm font-semibold hover:underline">Clear filters</button>
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
                            <h3 className="text-xl font-display font-medium text-ink">Receipt Details</h3>
                            <p className="text-sm text-ink-muted">{selectedBill?.bill_number} · {selectedBill?.supplier?.name || 'General Expense'}</p>
                        </div>
                        <button 
                            onClick={() => setSelectedBill(null)}
                            className="p-2 text-ink-muted hover:text-ink rounded-xl"
                        >
                            <IconX size={24} />
                        </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div className="rounded-xl overflow-hidden border border-border-warm bg-cream aspect-[3/4] flex items-center justify-center">
                            {selectedBill?.supplier_invoice_url?.match(/\.(jpeg|jpg|gif|png)$/) || selectedBill?.supplier_invoice_path?.match(/\.(jpeg|jpg|gif|png)$/) ? (
                                <div className="relative group w-full h-full">
                                    <img src={selectedBill?.supplier_invoice_url || selectedBill?.supplier_invoice_path} alt="Supplier invoice" className="w-full h-full object-contain" />
                                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-ink/5">
                                        <a 
                                            href={selectedBill?.supplier_invoice_url || selectedBill?.supplier_invoice_path} 
                                            target="_blank" 
                                            className="bg-surface/90 backdrop-blur p-2 rounded-lg shadow-xl text-xs font-display font-medium text-ink flex items-center gap-2"
                                        >
                                            <IconExternalLink size={14} /> View Full Size
                                        </a>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center text-ink-muted">
                                    <IconFileText size={64} />
                                    <span className="text-sm font-medium mt-4">Document Attachment</span>
                                    <a 
                                        href={selectedBill?.supplier_invoice_url || selectedBill?.supplier_invoice_path} 
                                        target="_blank" 
                                        className="mt-4 px-4 py-2 bg-terracotta text-white rounded-lg text-xs font-bold flex items-center gap-2 hover:bg-terracotta transition-colors"
                                    >
                                        <IconExternalLink size={14} />
                                        Open in New Tab
                                    </a>
                                </div>
                            )}
                        </div>

                        <div className="space-y-6">
                            <div className="p-4 bg-cream rounded-xl border border-border-warm">
                                <div className="space-y-3">
                                    <div className="flex justify-between">
                                        <span className="text-xs text-ink-muted">Total Amount</span>
                                        <span className="text-sm font-display font-medium text-ink">RM {(Number(selectedBill?.total_amount) || 0).toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-xs text-ink-muted">Date</span>
                                        <span className="text-sm font-display font-medium text-ink">
                                            {selectedBill?.bill_date ? new Date(selectedBill.bill_date).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-xs text-ink-muted">Reference</span>
                                        <span className="text-sm font-display font-medium text-ink">{selectedBill?.reference || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <button 
                                    onClick={() => { handleVerify(selectedBill.id); setSelectedBill(null); }}
                                    className="w-full py-3 bg-forest text-white rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-forest transition-colors shadow-lg "
                                >
                                    <IconCheck size={20} />
                                    Verify Transaction
                                </button>
                                <button 
                                    onClick={() => { handleFlag(selectedBill.id); setSelectedBill(null); }}
                                    className="w-full py-3 bg-surface text-terracotta border border-terracotta/30 rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-terracotta/10 transition-colors"
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
