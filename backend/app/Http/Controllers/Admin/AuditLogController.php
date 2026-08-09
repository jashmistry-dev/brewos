<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
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

        return response()->json([
            'audit_logs' => $auditLogs->items(),
            'pagination' => [
                'current_page' => $auditLogs->currentPage(),
                'per_page'     => $auditLogs->perPage(),
                'total'        => $auditLogs->total(),
                'last_page'    => $auditLogs->lastPage(),
            ],
        ]);
    }

    public function show(int|string $audit_log_id): JsonResponse
    {
        $auditLog = AuditLog::with(['user:id,name,email', 'cafe:id,name,slug'])->findOrFail($audit_log_id);

        return response()->json([
            'audit_log' => $auditLog,
        ]);
    }
}
