<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AuditController extends Controller
{
    /**
     * Display a listing of transactions to be audited.
     */
    public function index(Request $request): Response
    {
        $year = $request->input('year', now()->year);
        $status = $request->input('status', 'all');

        $query = Bill::with(['supplier', 'items'])
            ->whereYear('bill_date', $year)
            ->orderByDesc('bill_date');

        if ($status !== 'all') {
            $query->where('audit_status', $status);
        }

        $bills = $query->paginate(20)->withQueryString();

        return Inertia::render('Audit/Index', [
            'bills' => $bills,
            'filters' => [
                'year' => (int) $year,
                'status' => $status,
            ],
            'stats' => [
                'total' => Bill::whereYear('bill_date', $year)->count(),
                'unaudited' => Bill::whereYear('bill_date', $year)->where(function ($q) {
                    $q->where('audit_status', 'unaudited')->orWhereNull('audit_status');
                })->count(),
                'verified' => Bill::whereYear('bill_date', $year)->where('audit_status', 'verified')->count(),
                'flagged' => Bill::whereYear('bill_date', $year)->where('audit_status', 'flagged')->count(),
            ],
        ]);
    }

    /**
     * Generate a PDF summary of the audit.
     */
    public function report(Request $request)
    {
        $year = $request->input('year', now()->year);
        
        $bills = Bill::with(['supplier'])
            ->whereYear('bill_date', $year)
            ->orderBy('bill_date')
            ->get();

        $stats = [
            'total' => $bills->count(),
            'verified' => $bills->where('audit_status', 'verified')->count(),
            'unaudited' => $bills->where('audit_status', 'unaudited')->count() + $bills->whereNull('audit_status')->count(),
            'flagged' => $bills->where('audit_status', 'flagged')->count(),
            'total_amount' => $bills->sum('total_amount'),
        ];

        $companyData = tenant()->getCompanyDetails();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.audit_summary', [
            'bills' => $bills,
            'stats' => $stats,
            'year' => $year,
            'company' => $companyData,
        ]);

        return $pdf->stream("Audit_Summary_{$year}.pdf");
    }

    /**
     * Verify a transaction.
     */
    public function verify(Request $request, string $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        
        $bill->update([
            'audit_status' => 'verified',
            'audited_at' => now(),
            'audited_by' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', "Transaction {$bill->bill_number} has been verified.");
    }

    /**
     * Flag a transaction for review.
     */
    public function flag(Request $request, string $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        
        $bill->update([
            'audit_status' => 'flagged',
            'audited_at' => now(),
            'audited_by' => $request->user()?->id,
        ]);

        return redirect()->back()->with('warning', "Transaction {$bill->bill_number} has been flagged for review.");
    }
}
