<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function __construct(protected Request $request) {}

    /**
     * Record an audit log entry.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'old_values' => empty($oldValues) ? null : $oldValues,
                'new_values' => empty($newValues) ? null : $newValues,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Never let audit logging break the primary request flow
            Log::error('AuditLogger: failed to write audit entry', [
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
