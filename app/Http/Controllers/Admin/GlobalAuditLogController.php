<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/AuditLogs/Index', [
            'filters' => $request->only(['action', 'tenant']),
            'logs' => AuditLog::query()
                ->acrossTenants()
                ->with(['user', 'tenant'])
                ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
                ->when($request->filled('tenant'), fn ($query) => $query->where('tenant_id', $request->string('tenant')))
                ->latest('id')
                ->paginate(40)
                ->withQueryString()
                ->through(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'tenant' => $log->tenant?->name,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
