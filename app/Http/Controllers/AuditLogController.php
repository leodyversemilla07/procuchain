<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $query = AuditLog::with('actor:id,name,email')
                ->latest('created_at');

            if ($request->filled('action')) {
                $query->where('action', $request->string('action'));
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->string('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->string('date_to'));
            }

            $logs = $query->paginate(50)->withQueryString();

            $distinctActions = AuditLog::distinct()->orderBy('action')->pluck('action');

            return Inertia::render('admin/audit-log', [
                'logs' => $logs,
                'distinctActions' => $distinctActions,
                'filters' => $request->only(['action', 'user_id', 'date_from', 'date_to']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch audit logs', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('admin/audit-log', [
                'logs' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 50,
                    'total' => 0,
                    'links' => [],
                ],
                'distinctActions' => [],
                'filters' => [],
                'error' => 'Failed to load audit logs. Please try again.',
            ]);
        }
    }
}
