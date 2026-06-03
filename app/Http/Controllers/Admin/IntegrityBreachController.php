<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Http\Controllers\Controller;
use App\Models\IntegrityAuditLog;
use App\Models\IntegrityBreach;
use App\Models\ProcurementMirror;
use App\Services\BlockchainMirrorSyncService;
use App\Services\IntegrityVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integrity Breach Controller
 *
 * Admin controller for viewing and managing data integrity breaches
 * and integrity audit logs in the procurement mirror system.
 *
 * Two data sources:
 * - IntegrityBreach (procurement_mirror with breach) — active breach status
 * - IntegrityAuditLog — permanent forensic record (append-only)
 */
class IntegrityBreachController extends Controller
{
    /**
     * Display all integrity breaches with filtering and pagination.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-audit-log');

        $query = IntegrityBreach::query();

        // Filter by breach type
        if ($type = $request->input('breach_type')) {
            $query->where('breach_type', $type);
        }

        // Filter by stream
        if ($stream = $request->input('stream')) {
            $query->where('stream', $stream);
        }

        // Filter by resolution status
        $status = $request->input('status');
        if ($status === 'unresolved') {
            $query->unresolved();
        } elseif ($status === 'resolved') {
            $query->whereNotNull('repaired_at');
        }

        // Filter by PR number
        if ($prNumber = $request->input('pr_number')) {
            $query->forKey($prNumber);
        }

        // Filter by authorization status
        if ($request->input('unauthorized') === 'true') {
            $query->where('is_authorized', false);
        }

        $breaches = $query->orderByDesc('breach_detected_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/integrity-breaches', [
            'breaches' => $breaches,
            'filters' => [
                'breach_type' => $type,
                'stream' => $stream,
                'status' => $status,
                'pr_number' => $prNumber,
                'unauthorized' => $request->input('unauthorized'),
            ],
            'breachTypes' => BreachTypeEnums::options(),
            'streams' => collect(StreamEnums::cases())
                ->filter(fn ($case) => $case->isProcurementStream() || $case->isUserStream())
                ->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()])
                ->toArray(),
            'stats' => [
                'total' => IntegrityBreach::count(),
                'unresolved' => IntegrityBreach::unresolved()->count(),
                'critical' => IntegrityBreach::where('breach_type', BreachTypeEnums::HASH_MISMATCH->value)->unresolved()->count()
                    + IntegrityBreach::where('breach_type', BreachTypeEnums::CONTENT_MISMATCH->value)->unresolved()->count(),
                'unauthorized' => IntegrityBreach::where('is_authorized', false)->unresolved()->count(),
            ],
        ]);
    }

    /**
     * Show details for a specific breach record.
     */
    public function show(int $id): JsonResponse
    {
        $this->authorize('view-audit-log');

        $breach = IntegrityBreach::findOrFail($id);

        // Also fetch related audit log entries
        $auditLogs = IntegrityAuditLog::where('stream', $breach->stream)
            ->where('stream_key', $breach->stream_key)
            ->where('txid', $breach->txid)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'id' => $breach->id,
            'stream' => $breach->stream,
            'stream_key' => $breach->stream_key,
            'txid' => $breach->txid,
            'publisher_address' => $breach->publisher_address,
            'data_json' => $breach->data_json,
            'data_hash' => $breach->data_hash,
            'is_authorized' => $breach->is_authorized,
            'breach_type' => $breach->breach_type,
            'breach_data' => $breach->breach_data,
            'breach_detected_at' => $breach->breach_detected_at?->toIso8601String(),
            'repaired_at' => $breach->repaired_at?->toIso8601String(),
            'synced_at' => $breach->synced_at?->toIso8601String(),
            'verified_at' => $breach->verified_at?->toIso8601String(),
            'blocktime' => $breach->blocktime?->toIso8601String(),
            'severity' => $breach->severity(),
            'revision_number' => $breach->revision_number,
            'parent_txid' => $breach->parent_txid,
            'is_latest_revision' => $breach->is_latest_revision,
            'audit_logs' => $auditLogs,
        ]);
    }

    /**
     * Repair a specific breach from the blockchain.
     */
    public function repair(Request $request, int $id): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $breach = IntegrityBreach::findOrFail($id);

        try {
            $service = app(IntegrityVerificationService::class);
            $syncService = app(BlockchainMirrorSyncService::class);
            $count = $syncService->repairFromChain($breach->stream_key, $breach->stream);

            $breach->markAsRepaired();

            // Also update any pending audit logs for this record
            IntegrityAuditLog::where('stream', $breach->stream)
                ->where('stream_key', $breach->stream_key)
                ->where('txid', $breach->txid)
                ->where('recovery_status', 'pending')
                ->each(fn ($log) => $log->markRestored([
                    'items_restored' => $count,
                    'restored_by' => auth()->user()->name ?? 'admin',
                    'restored_via' => 'admin_ui',
                ]));

            Log::info('IntegrityBreachController: breach repaired via admin UI', [
                'breach_id' => $id,
                'stream' => $breach->stream,
                'stream_key' => $breach->stream_key,
                'repaired_count' => $count,
            ]);

            return redirect()->back()->with('success', "Breach repaired. {$count} item(s) synced from blockchain.");
        } catch (\Exception $e) {
            Log::error('IntegrityBreachController: repair failed', [
                'breach_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to repair breach: '.$e->getMessage());
        }
    }

    /**
     * Repair all unresolved breaches for a PR number.
     */
    public function repairPr(Request $request): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $prNumber = $request->input('pr_number');

        if (! $prNumber) {
            return redirect()->back()->with('error', 'PR number is required.');
        }

        try {
            $syncService = app(BlockchainMirrorSyncService::class);
            $count = $syncService->repairFromChain($prNumber);

            Log::info('IntegrityBreachController: PR breaches repaired via admin UI', [
                'pr_number' => $prNumber,
                'repaired_count' => $count,
            ]);

            return redirect()->back()->with('success', "PR {$prNumber} repaired. {$count} item(s) synced from blockchain.");
        } catch (\Exception $e) {
            Log::error('IntegrityBreachController: PR repair failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to repair PR: '.$e->getMessage());
        }
    }

    /**
     * Run an integrity verification on all mirror data using the new service.
     */
    public function verify(): JsonResponse
    {
        $this->authorize('view-audit-log');

        try {
            $service = app(IntegrityVerificationService::class);
            $result = $service->verifyAndRepair(autoRepair: false, source: 'manual');

            $breachCount = is_array($result['violations'])
            ? array_sum($result['violations'])
            : 0;

            return response()->json([
                'success' => true,
                'run_id' => $result['run_id'],
                'verified' => $result['verified'],
                'breach_count' => $breachCount,
                'violations' => $result['violations'],
                'restored' => $result['restored'],
                'failed' => $result['failed'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run integrity verification with auto-repair.
     */
    public function verifyAndRepair(): JsonResponse
    {
        $this->authorize('update-audit-log');

        try {
            $service = app(IntegrityVerificationService::class);
            $result = $service->verifyAndRepair(autoRepair: true, source: 'manual');

            $breachCount = is_array($result['violations'])
            ? array_sum($result['violations'])
            : 0;

            return response()->json([
                'success' => true,
                'run_id' => $result['run_id'],
                'verified' => $result['verified'],
                'breach_count' => $breachCount,
                'violations' => $result['violations'],
                'restored' => $result['restored'],
                'failed' => $result['failed'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get mirror status overview for the dashboard widget.
     */
    public function mirrorStatus(): JsonResponse
    {
        $this->authorize('view-audit-log');

        $totalRecords = ProcurementMirror::count();
        $unresolvedBreaches = IntegrityBreach::unresolved()->count();
        $lastSync = ProcurementMirror::max('synced_at');
        $lastVerified = ProcurementMirror::max('verified_at');

        $streamCounts = ProcurementMirror::selectRaw('stream, count(*) as count')
            ->groupBy('stream')
            ->pluck('count', 'stream')
            ->toArray();

        $breachCounts = IntegrityBreach::selectRaw('breach_type, count(*) as count')
            ->whereNull('repaired_at')
            ->groupBy('breach_type')
            ->pluck('count', 'breach_type')
            ->toArray();

        // Recent audit log stats
        $lastAuditRun = IntegrityAuditLog::max('created_at');
        $pendingRepairs = IntegrityAuditLog::unresolved()->count();

        return response()->json([
            'total_records' => $totalRecords,
            'unresolved_breaches' => $unresolvedBreaches,
            'last_sync' => $lastSync,
            'last_verified' => $lastVerified,
            'last_audit_run' => $lastAuditRun,
            'pending_repairs' => $pendingRepairs,
            'stream_counts' => $streamCounts,
            'breach_counts' => $breachCounts,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // INTEGRITY AUDIT LOG ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Render the Integrity Audit Logs Inertia page.
     */
    public function auditLogsPage(): Response
    {
        $this->authorize('view-audit-log');

        $violationTypes = [
            'hash_mismatch' => 'Hash Mismatch',
            'content_mismatch' => 'Content Mismatch',
            'user_address_tampered' => 'Address Tampered',
            'unauthorized_publisher' => 'Unauthorized Publisher',
            'row_deleted' => 'Row Deleted',
        ];

        $recoveryStatuses = [
            'pending' => 'Pending',
            'restored' => 'Restored',
            'failed' => 'Failed',
            'skipped' => 'Skipped',
        ];

        $severityLevels = [
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ];

        $sources = [
            'scheduled' => 'Scheduled',
            'manual' => 'Manual',
            'api' => 'API',
        ];

        return Inertia::render('admin/integrity-audit-logs', [
            'violationTypes' => $violationTypes,
            'recoveryStatuses' => $recoveryStatuses,
            'severityLevels' => $severityLevels,
            'sources' => $sources,
        ]);
    }

    /**
     * List integrity audit logs with filtering and pagination (JSON API).
     */
    public function auditLogsIndex(Request $request): JsonResponse
    {
        $this->authorize('view-audit-log');

        $query = IntegrityAuditLog::query();

        if ($type = $request->input('violation_type')) {
            $query->forViolationType($type);
        }

        if ($key = $request->input('stream_key')) {
            $query->forStreamKey($key);
        }

        if ($runId = $request->input('verification_run_id')) {
            $query->forRun($runId);
        }

        if ($severity = $request->input('severity')) {
            $query->withSeverity($severity);
        }

        if ($status = $request->input('recovery_status')) {
            $query->withRecoveryStatus($status);
        }

        if ($source = $request->input('source')) {
            $query->fromSource($source);
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return response()->json($logs);
    }

    /**
     * Show a specific integrity audit log entry.
     */
    public function auditLogsShow(int $id): JsonResponse
    {
        $this->authorize('view-audit-log');

        $log = IntegrityAuditLog::findOrFail($id);

        return response()->json($log);
    }

    /**
     * Repair a specific violation from the blockchain (via audit log).
     */
    public function auditLogsRepair(int $id): JsonResponse
    {
        $this->authorize('update-audit-log');

        $auditLog = IntegrityAuditLog::findOrFail($id);

        if ($auditLog->recovery_status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => "Violation already processed with status: {$auditLog->recovery_status}",
            ], 422);
        }

        try {
            $service = app(IntegrityVerificationService::class);
            $result = $service->restoreViolation($auditLog);

            return response()->json([
                'success' => $result['success'],
                'items_restored' => $result['items_restored'],
                'error' => $result['error'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a violation report for a specific verification run.
     */
    public function auditLogsReport(string $runId): JsonResponse
    {
        $this->authorize('view-audit-log');

        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        return response()->json($report);
    }

    // ─── Detail Pages (Inertia) ──────────────────────────────────────

    /**
     * Render the Audit Log Detail page.
     */
    public function auditLogDetailPage(int $id): Response
    {
        $this->authorize('view-audit-log');

        $log = IntegrityAuditLog::find($id);

        if (! $log) {
            return Inertia::render('admin/audit-log-detail', [
                'logId' => $id,
                'log' => null,
                'error' => 'Audit log not found.',
            ]);
        }

        return Inertia::render('admin/audit-log-detail', [
            'logId' => $id,
            'log' => $log,
        ]);
    }

    /**
     * Render the Verification Run Report page.
     */
    public function verificationReportPage(string $runId): Response
    {
        $this->authorize('view-audit-log');

        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        return Inertia::render('admin/verification-report', [
            'runId' => $runId,
            'report' => $report,
        ]);
    }

    /**
     * Render the Breach Detail page.
     */
    public function breachDetailPage(int $id): Response
    {
        $this->authorize('view-audit-log');

        $breach = ProcurementMirror::find($id);

        if (! $breach) {
            return Inertia::render('admin/breach-detail', [
                'breachId' => $id,
                'breach' => null,
                'error' => 'Breach not found.',
            ]);
        }

        // Get revision history
        $revisionHistory = $breach->getRevisionHistory()->map(fn ($r) => [
            'txid' => $r->txid,
            'revision_number' => $r->revision_number,
            'parent_txid' => $r->parent_txid,
            'is_latest_revision' => $r->is_latest_revision,
            'blocktime' => $r->blocktime?->toIso8601String(),
            'publisher_address' => $r->publisher_address,
            'data_hash' => $r->data_hash,
            'breach_detected_at' => $r->breach_detected_at?->toIso8601String(),
            'breach_type' => $r->breach_type,
            'repaired_at' => $r->repaired_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/breach-detail', [
            'breachId' => $id,
            'breach' => array_merge($breach->toArray(), [
                'revision_history' => $revisionHistory,
            ]),
        ]);
    }
}
