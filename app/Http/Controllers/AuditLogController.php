<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('AuditLogs/Index', [
            'filters' => $request->only(['action', 'search']),
            'logs' => AuditLog::query()
                ->with('user')
                ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'auditable_type' => class_basename((string) $log->auditable_type),
                    'auditable_id' => $log->auditable_id,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
