<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(protected Request $request) {}

    /**
     * Write a synchronous, append-only audit log entry.
     *
     * Per ADR-004: passwords, tokens, and secrets must never appear
     * in old_values or new_values. Callers are responsible for
     * stripping sensitive fields before passing values here.
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $cafeId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $userId = auth()->id();

        return AuditLog::create([
            'user_id'     => $userId,
            'cafe_id'     => $cafeId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => $this->request->ip(),
            'user_agent'  => $this->request->userAgent(),
        ]);
    }
}
