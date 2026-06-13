<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BreachType;
use App\Enums\Stream;
use App\Http\Controllers\Controller;
use App\Jobs\RunIntegrityVerificationJob;
use App\Models\IntegrityViolationLog;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementStage;
use App\Services\BlockchainRecordSyncService;
use App\Services\BlockchainRpcClient;
use App\Services\IntegrityVerificationService;
use App\Services\NormalizedTableSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

        $query = IntegrityViolationLog::query();

        if ($type = $request->input('violation_type')) {
            $query->where('violation_type', $type);
        }
        if ($stream = $request->input('stream')) {
            $query->where('stream', $stream);
        }
        if ($status = $request->input('status')) {
            $query->where('recovery_status', $status);
        } else {
            // Integrity Breaches is the active work queue — show only actionable
            // pending violations. Superseded entries are deduplication artifacts
            // from repeated audit runs; they are not genuine open issues.
            // Permanent historical records remain visible in Integrity Audit Logs.
            $query->where('recovery_status', 'pending');
        }
        if ($prNumber = $request->input('pr_number')) {
            $query->where('stream_key', $prNumber);
        }

        $breaches = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Stats only count genuinely active pending violations (not superseded).
        $activePending = IntegrityViolationLog::where('recovery_status', 'pending');

        return Inertia::render('admin/integrity-breaches', [
            'breaches' => $breaches,
            'filters' => $request->only(['violation_type', 'stream', 'status', 'pr_number']),
            'breachTypes' => BreachType::options(),
            'streams' => collect(Stream::cases())
                ->filter(fn ($case) => $case->isProcurementStream())
                ->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()])
                ->toArray(),
            'stats' => [
                'total' => (clone $activePending)->count(),
                'unresolved' => (clone $activePending)->count(),
                'critical' => (clone $activePending)->where('severity', 'critical')->count(),
                'high' => (clone $activePending)->where('severity', 'high')->count(),
            ],
            'verificationStatus' => $this->getVerificationStatus(),
        ]);
    }

    /**
     * Show details for a specific breach.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-audit-log');

        $log = IntegrityViolationLog::find($id);

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
            $BlockchainRpcClient = app(BlockchainRpcClient::class);
            $items = $BlockchainRpcClient->liststreamkeyitems($log->stream, $log->stream_key);
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
                'database_snapshot' => $log->mirror_snapshot,
                'blockchain_snapshot' => $log->chain_snapshot,
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

        $log = IntegrityViolationLog::findOrFail($id);

        if ($log->recovery_status !== 'pending') {
            return back()->with('error', 'Already processed');
        }

        try {
            $result = app(IntegrityVerificationService::class)->restoreViolation($log);

            if (! $result['success']) {
                return back()->with('error', 'Repair failed: '.($result['error'] ?? 'Post-repair verification failed.'));
            }

            return back()->with('success', 'Breach repaired from blockchain and verified clean.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Integrity breach repair failed', [
                'breach_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Repair failed. Please try again or contact support.');
        }
    }

    /**
     * Repair all breaches for a PR.
     */
    public function repairPr(Request $request): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $validated = $request->validate([
            'pr_number' => ['required', 'string', 'regex:/^PR-\d{4}-\d{3}(-\d{4})?$/'],
        ]);

        $prNumber = $validated['pr_number'];

        try {
            $syncService = app(BlockchainRecordSyncService::class);
            $syncService->repairFromChain($prNumber);

            $verification = app(IntegrityVerificationService::class)->verifyAndRepair(false, 'manual');
            $stillBreached = IntegrityViolationLog::where('verification_run_id', $verification['run_id'])
                ->where('stream_key', $prNumber)
                ->where('recovery_status', 'pending')
                ->exists();

            if ($stillBreached) {
                return back()->with('error', "Repair ran, but PR {$prNumber} still has reproducible integrity breaches.");
            }

            IntegrityViolationLog::where('stream_key', $prNumber)
                ->where('recovery_status', 'pending')
                ->each(fn ($log) => $log->markRestored([
                    'restored_by' => auth()->user()->name ?? 'admin',
                    'restored_at' => now()->toIso8601String(),
                    'verified_by_run_id' => $verification['run_id'],
                ]));

            return back()->with('success', "PR {$prNumber} repaired from blockchain and verified clean.");
        } catch (\Exception $e) {
            report($e);
            Log::error('Integrity breach PR repair failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Repair failed. Please try again or contact support.');
        }
    }

    /**
     * Run integrity verification.
     */
    public function verify(): SymfonyResponse
    {
        $this->authorize('view-audit-log');

        $cacheKey = 'verification_run_'.auth()->id();
        $status = Cache::get("{$cacheKey}_status");

        if ($status === 'running') {
            return back()->with('error', 'Verification is already in progress.');
        }

        dispatch(new RunIntegrityVerificationJob(
            cacheKey: $cacheKey,
            userId: (string) auth()->id(),
            userName: auth()->user()?->name ?? 'admin',
            autoRepair: false,
        ));

        return back()->with('verification_run_id', $cacheKey)
            ->with('info', 'Verification started in the background. Refresh to see new results.');
    }

    /**
     * Run verification with auto-repair.
     */
    public function verifyAndRepair(): SymfonyResponse
    {
        $this->authorize('update-audit-log');

        $cacheKey = 'verification_run_'.auth()->id();
        $status = Cache::get("{$cacheKey}_status");

        if ($status === 'running') {
            return back()->with('error', 'Verification is already in progress.');
        }

        dispatch(new RunIntegrityVerificationJob(
            cacheKey: $cacheKey,
            userId: (string) auth()->id(),
            userName: auth()->user()?->name ?? 'admin',
            autoRepair: true,
        ));

        return back()->with('verification_run_id', $cacheKey)
            ->with('info', 'Verify & Repair started in the background. Refresh to see results.');
    }

    /**
     * Poll verification run status.
     */
    public function verifyStatus(): JsonResponse
    {
        $this->authorize('view-audit-log');

        return response()->json($this->getVerificationStatus());
    }

    /**
     * Get the current verification run status from cache.
     * Also returns/clears result data on completion/failure.
     */
    private function getVerificationStatus(): array
    {
        $cacheKey = 'verification_run_'.auth()->id();
        $status = Cache::get("{$cacheKey}_status");

        if (! $status) {
            return ['status' => 'idle'];
        }

        $response = [
            'status' => $status,
            'started_at' => Cache::get("{$cacheKey}_started_at"),
        ];

        if ($status === 'completed') {
            $response['result'] = Cache::get("{$cacheKey}_result");
            Cache::forget("{$cacheKey}_status");
            Cache::forget("{$cacheKey}_result");
            Cache::forget("{$cacheKey}_started_at");
        } elseif ($status === 'failed') {
            $response['error'] = Cache::get("{$cacheKey}_error");
            Cache::forget("{$cacheKey}_status");
            Cache::forget("{$cacheKey}_error");
            Cache::forget("{$cacheKey}_started_at");
        }

        return $response;
    }

    /**
     * Sync all data from blockchain to normalized tables.
     */
    public function sync(): JsonResponse
    {
        $this->authorize('update-audit-log');

        try {
            $syncService = app(NormalizedTableSyncService::class);
            $counts = $syncService->syncAll();

            return response()->json(['success' => true, 'counts' => $counts]);
        } catch (\Exception $e) {
            report($e);
            Log::error('Manual normalized table sync failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'Sync failed. Please try again or contact support.'], 500);
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
            'unresolved_breaches' => IntegrityViolationLog::where('recovery_status', 'pending')->count(),
            'stream_counts' => [
                'procurements' => Procurement::count(),
                'stages' => ProcurementStage::count(),
                'documents' => ProcurementDocument::count(),
                'events' => ProcurementEvent::count(),
            ],
        ]);
    }

    // ─── Audit Logs Page ─────────────────────────────────────────────

    public function auditLogsPage(Request $request): Response
    {
        $this->authorize('view-audit-log');

        $query = IntegrityViolationLog::query();

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
            'violationTypes' => BreachType::options(),
            'recoveryStatuses' => ['pending' => 'Pending', 'restored' => 'Restored', 'failed' => 'Failed', 'skipped' => 'Skipped'],
            'severityLevels' => ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'],
            'sources' => ['scheduled' => 'Scheduled', 'manual' => 'Manual'],
        ]);
    }

    public function auditLogsRepair(int $id): RedirectResponse
    {
        $this->authorize('update-audit-log');

        $log = IntegrityViolationLog::findOrFail($id);
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
            Log::error('Integrity audit log repair failed', [
                'audit_log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Repair failed. Please try again or contact support.');
        }
    }

    public function verificationReportPage(string $runId): Response
    {
        $this->authorize('view-audit-log');

        $logs = IntegrityViolationLog::forRun($runId)->orderByDesc('severity')->get();

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

        $log = IntegrityViolationLog::find($id);

        return Inertia::render('admin/audit-log-detail', [
            'logId' => $id,
            'log' => $log,
        ]);
    }
}
