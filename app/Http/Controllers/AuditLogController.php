<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $logs = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user_name ?? $log->user?->name ?? 'System',
                    'event' => $log->event,
                    'auditable_type' => class_basename($log->auditable_type),
                    'auditable_id' => $log->auditable_id,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'created_at' => $log->created_at->toIso8601String(),
                    'created_at_human' => $log->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
        ]);
    }
}
