<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $query = AuditLog::with(['user:id,name,email', 'cafe:id,name,slug']);

        if ($request->filled('cafe_id')) {
            $query->where('cafe_id', $request->query('cafe_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $auditLogs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $responseData = [
            'audit_logs' => $auditLogs->items(),
            'pagination' => [
                'current_page' => $auditLogs->currentPage(),
                'per_page'     => $auditLogs->perPage(),
                'total'        => $auditLogs->total(),
                'last_page'    => $auditLogs->lastPage(),
            ],
            'filters' => $request->only(['cafe_id', 'user_id', 'action', 'entity_type']),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'audit_logs' => $responseData['audit_logs'],
                'pagination' => $responseData['pagination'],
            ]);
        }

        return Inertia::render('Admin/AuditLogs', $responseData);
    }

    public function show(int|string $audit_log_id, Request $request): JsonResponse|RedirectResponse
    {
        $auditLog = AuditLog::with(['user:id,name,email', 'cafe:id,name,slug'])->findOrFail($audit_log_id);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'audit_log' => $auditLog,
            ]);
        }

        return redirect()->route('admin.audit_logs.index');
    }
}
