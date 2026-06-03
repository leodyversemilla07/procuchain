<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Http\Controllers\Controller;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Services\BlockchainRecordSyncService;
use App\Services\IntegrityVerificationService;
use App\Services\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integrity Breach Controller
 *
 * Admin controller for viewing and managing integrity breaches.
 * Reads from normalized tables, repairs FROM blockchain.
 */
class IntegrityBreachController extends Controller
{
    /**
     * Display all integrity breaches with filtering.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-audit-log');

        $query = IntegrityAuditLog::query()->where('recovery_status', '!=', 'restored');

        if ($type = $request->input('violation_type')) {
            $query->where('violation_type', $type);
        }
        if ($stream = $request->input('stream')) {
            $query->where('stream', $stream);
        }
        if ($status = $request->input('status')) {
            $query->where('recovery_status', $status);
        }
        if ($prNumber = $request->input('pr_number')) {
            $query->where('stream_key', $prNumber);
        }

        $breaches = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/integrity-breaches', [
            'breaches' => $breaches,
            'filters' => $request->only(['violation_type', 'stream', 'status', 'pr_number']),
            'breachTypes' => BreachTypeEnums::options(),
            'streams' => collect(StreamEnums::cases())
                ->filter(fn ($case) => $case->isProcurementStream())
                ->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()])
                ->toArray(),
            'stats' => [
                'total' => IntegrityAuditLog::count(),
                'unresolved' => IntegrityAuditLog::where('recovery_status', 'pending')->count(),
                'critical' => IntegrityAuditLog::where('severity', 'critical')->where('recovery_status', 'pending')->count(),
                'high' => IntegrityAuditLog::where('severity', 'high')->where('recovery_status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * Show details for a specific breach.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-audit-log');

        $log = IntegrityAuditLog::find($id);

        if (! $log) {
            return Inertia::render('admin/breach-detail', [
                'breachId' => $id,
                'breach' => null,
                'error' => 'Breach not found.',
            ]);
        }

        // Get blockchain data for comparison
        $blockchainData = null;
        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamkeyitems($log->stream, $log->stream_key);
            if (is_array($items) && ! empty($items)) {
                foreach ($items as $item) {
                    if (($item['txid'] ?? null) === $log->txid) {
                        $blockchainData = $item['data']['json'] ?? null;
                        break;
                    }
                }
                if ($blockchainData === null) {
                    $blockchainData = end($items)['data']['json'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch blockchain data', ['error' => $e->getMessage()]);
        }

        return Inertia::render('admin/breach-detail', [
            'breachId' => $id,
            'breach' => [
                'id' => $log->id,
                'stream' => $log->stream,
                'stream_key' => $log->stream_key,
                'txid' => $log->txid,
                'violation_type' => $log->violation_type,
                'severity' => $log->severity,
                'database_snapshot' => $log->database_snapshot,
                'blockchain_snapshot' => $log->blockchain_snapshot,
                'field_differences' => $log->field_differences,
                'recovery_status' => $log->recovery_status,
                'created_at' => $log->created_at?->toIso8601String(),
                'blockchain_data' => $blockchainData,
            ],
        ]);
    }

    /**
     * Repair a specific breach from blockchain.
     */
    public function repair(int $id): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $log = IntegrityAuditLog::findOrFail($id);

        if ($log->recovery_status !== 'pending') {
            return back()->with('error', 'Already processed');
        }

        try {
            $syncService = app(BlockchainRecordSyncService::class);
            $syncService->repairFromChain($log->stream_key, $log->stream);

            $log->markRestored([
                'restored_by' => auth()->user()->name ?? 'admin',
                'restored_at' => now()->toIso8601String(),
            ]);

            return back()->with('success', 'Breach repaired from blockchain.');
        } catch (\Exception $e) {
            return back()->with('error', 'Repair failed: '.$e->getMessage());
        }
    }

    /**
     * Repair all breaches for a PR.
     */
    public function repairPr(Request $request): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $prNumber = $request->input('pr_number');
        if (! $prNumber) {
            return back()->with('error', 'PR number required');
        }

        try {
            $syncService = app(BlockchainRecordSyncService::class);
            $syncService->repairFromChain($prNumber);

            IntegrityAuditLog::where('stream_key', $prNumber)
                ->where('recovery_status', 'pending')
                ->each(fn ($log) => $log->markRestored(['restored_by' => 'admin']));

            return back()->with('success', "PR {$prNumber} repaired from blockchain.");
        } catch (\Exception $e) {
            return back()->with('error', 'Repair failed: '.$e->getMessage());
        }
    }

    /**
     * Run integrity verification.
     */
    public function verify(): JsonResponse
    {
        $this->authorize('view-audit-log');

        try {
            $service = app(IntegrityVerificationService::class);
            $result = $service->verifyAndRepair(false, 'manual');

            return response()->json([
                'success' => true,
                'run_id' => $result['run_id'],
                'verified' => $result['verified'],
                'breach_count' => array_sum($result['violations']),
                'violations' => $result['violations'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Run verification with auto-repair.
     */
    public function verifyAndRepair(): JsonResponse
    {
        $this->authorize('update-audit-log');

        try {
            $service = app(IntegrityVerificationService::class);
            $result = $service->verifyAndRepair(true, 'manual');

            return response()->json([
                'success' => true,
                'run_id' => $result['run_id'],
                'verified' => $result['verified'],
                'breach_count' => array_sum($result['violations']),
                'restored' => $result['restored'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync all data from blockchain to normalized tables.
     */
    public function sync(): JsonResponse
    {
        $this->authorize('update-audit-log');

        try {
            $syncService = app(\App\Services\NormalizedTableSyncService::class);
            $counts = $syncService->syncAll();

            return response()->json(['success' => true, 'counts' => $counts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Dashboard status.
     */
    public function mirrorStatus(): JsonResponse
    {
        $this->authorize('view-audit-log');

        return response()->json([
            'total_records' => Procurement::count(),
            'unresolved_breaches' => IntegrityAuditLog::where('recovery_status', 'pending')->count(),
            'stream_counts' => [
                'procurements' => Procurement::count(),
                'stages' => \App\Models\ProcurementStage::count(),
                'documents' => \App\Models\ProcurementDocument::count(),
                'events' => \App\Models\ProcurementEvent::count(),
            ],
        ]);
    }

    // ─── Audit Logs Page ─────────────────────────────────────────────

    public function auditLogsPage(Request $request): Response
    {
        $this->authorize('view-audit-log');

        $query = IntegrityAuditLog::query();

        if ($type = $request->input('violation_type')) {
            $query->where('violation_type', $type);
        }
        if ($key = $request->input('stream_key')) {
            $query->where('stream_key', $key);
        }
        if ($severity = $request->input('severity')) {
            $query->where('severity', $severity);
        }
        if ($status = $request->input('recovery_status')) {
            $query->where('recovery_status', $status);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return Inertia::render('admin/integrity-audit-logs', [
            'logs' => $logs,
            'filters' => $request->only(['violation_type', 'stream_key', 'severity', 'recovery_status']),
            'violationTypes' => BreachTypeEnums::options(),
            'recoveryStatuses' => ['pending' => 'Pending', 'restored' => 'Restored', 'failed' => 'Failed', 'skipped' => 'Skipped'],
            'severityLevels' => ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'],
            'sources' => ['scheduled' => 'Scheduled', 'manual' => 'Manual'],
        ]);
    }

    public function auditLogsRepair(int $id): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $log = IntegrityAuditLog::findOrFail($id);
        if ($log->recovery_status !== 'pending') {
            return back()->with('error', 'Already processed');
        }

        try {
            $service = app(IntegrityVerificationService::class);
            $result = $service->restoreViolation($log);
            return $result['success']
                ? back()->with('success', 'Restored from blockchain.')
                : back()->with('error', $result['error'] ?? 'Failed');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verificationReportPage(string $runId): Response
    {
        $this->authorize('view-audit-log');

        $logs = IntegrityAuditLog::forRun($runId)->orderByDesc('severity')->get();

        $summary = [
            'total_violations' => $logs->count(),
            'critical' => $logs->where('severity', 'critical')->count(),
            'high' => $logs->where('severity', 'high')->count(),
            'medium' => $logs->where('severity', 'medium')->count(),
            'low' => $logs->where('severity', 'low')->count(),
            'restored' => $logs->where('recovery_status', 'restored')->count(),
            'pending' => $logs->where('recovery_status', 'pending')->count(),
            'by_type' => $logs->groupBy('violation_type')->map->count()->toArray(),
        ];

        return Inertia::render('admin/verification-report', [
            'runId' => $runId,
            'report' => ['run_id' => $runId, 'summary' => $summary, 'violations' => $logs->toArray()],
        ]);
    }

    public function auditLogDetailPage(int $id): Response
    {
        $this->authorize('view-audit-log');

        $log = IntegrityAuditLog::find($id);

        return Inertia::render('admin/audit-log-detail', [
            'logId' => $id,
            'log' => $log,
        ]);
    }
}
