<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait AuditContext
{
    /**
     * Get the audit context array containing the admin_id.
     *
     * @return array{admin_id: int|string|null}
     */
    protected function auditContext(?Request $request = null): array
    {
        return ['admin_id' => $request?->user()?->id ?? auth()->id()];
    }
}
