<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $platformTypes = [
            Subscription::class,
            Plan::class,
            User::class,
            Tenant::class,
        ];

        $query = AuditLog::whereIn('auditable_type', $platformTypes)
            ->latest('created_at');

        if ($request->filled('model')) {
            $query->where('auditable_type', $request->input('model'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id'             => $log->id,
                'user_name'      => $log->user_name ?? 'System',
                'user_id'        => $log->user_id,
                'event'          => $log->event,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id'   => $log->auditable_id,
                'old_values'     => $log->old_values,
                'new_values'     => $log->new_values,
                'created_at'     => $log->created_at?->toIso8601String(),
                'created_at_human' => $log->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs'    => $logs,
            'filters' => $request->only(['model', 'event', 'from', 'to']),
            'modelOptions' => collect($platformTypes)->map(fn ($c) => [
                'value' => $c,
                'label' => class_basename($c),
            ])->values(),
        ]);
    }
}
