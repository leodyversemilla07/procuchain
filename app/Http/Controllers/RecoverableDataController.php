<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\NodeOperationJob;
use App\Services\BlockchainStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recoverable Data Controller
 *
 * Admin interface for viewing and restoring deleted blockchain files.
 * Also provides single-node purge and resync for demo purposes.
 *
 * Blockchain recoverability architecture:
 * - Data is never permanently erased from the chain
 * - "Deletion" is an on-chain marker (action: 'deleted')
 * - Restoration publishes a counter-marker (action: 'restored')
 * - Single-node purge removes local copy only; peers retain data
 * - Resync triggers the node to re-download from peers
 * - All actions are audit-logged per RA 12009 (NGPA)
 *
 * Purge and resync are dispatched as async queue jobs because
 * the SSM commands take 60-180s, exceeding nginx's fastcgi_read_timeout.
 * The frontend polls the status endpoint for progress.
 */
class RecoverableDataController extends Controller
{
    public function __construct(
        private BlockchainStorageService $storage,
    ) {}

    /**
     * Display the Recoverable Data admin page.
     * Shows all currently-deleted files grouped by PR number,
     * plus available nodes for the purge/resync demo.
     */
    public function index(Request $request): Response
    {
        $this->authorize('manage-recoverable-data');

        $deletedFiles = $this->storage->getDeletedFiles();

        $files = collect($deletedFiles)->values()->map(fn (array $file) => [
            'file_key' => $file['file_key'],
            'pr_number' => $file['pr_number'] ?? explode('/', $file['file_key'])[0],
            'reason' => $file['reason'],
            'deleted_at' => $file['deleted_at'],
        ]);

        // Extract unique PR numbers for the dropdown
        $prNumbers = $files->pluck('pr_number')->unique()->sort()->values()->all();

        return Inertia::render('admin/recoverable-data', [
            'deletedFiles' => $files,
            'nodes' => $this->storage->getAvailableNodes(),
        ]);
    }

    /**
     * Restore a previously deleted file on blockchain.
     * Publishes a 'restored' action marker — the on-chain data was never removed.
     */
    public function restore(Request $request): RedirectResponse
    {
        $this->authorize('manage-recoverable-data');

        $validated = $request->validate([
            'file_key' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $fileKey = $validated['file_key'];
        $reason = $validated['reason'] ?? 'Restored by admin';

        $success = $this->storage->restoreFile($fileKey, $reason);

        if ($success) {
            return redirect()->back()->with('success', 'File restored on blockchain. The restoration event is now on-chain and audit-logged.');
        }

        return redirect()->back()->with('error', 'Failed to restore file on blockchain.');
    }

    /**
     * Purge a node's data to demonstrate blockchain data resilience.
     *
     * In MultiChain CE, per-key deletion is not available — this performs
     * a full node purge (same as purgeAllFromNode). Data survives on all
     * other nodes and can be restored via manual resync.
     *
     * Recorded on-chain as action: 'file_node_purge' for audit compliance.
     */
    public function deleteFromNode(Request $request): RedirectResponse
    {
        $this->authorize('manage-recoverable-data');

        $validated = $request->validate([
            'file_key' => 'required|string',
            'node_id' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->storage->deleteFromNode(
            $validated['file_key'],
            $validated['node_id'],
            $validated['reason'] ?? 'Demo: single-node purge'
        );

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Purge ALL data from a single node's local storage (async).
     *
     * Dispatches a NodeOperationJob to the queue because the SSM command
     * takes 60-120s, which exceeds nginx's fastcgi_read_timeout (90s).
     * Returns the job ID immediately; the frontend polls for status.
     */
    public function purgeAllFromNode(Request $request): JsonResponse
    {
        $this->authorize('manage-recoverable-data');

        $validated = $request->validate([
            'node_id' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $jobId = Str::uuid()->toString();

        // Store initial pending state
        Cache::put("node_operation:{$jobId}", [
            'status' => 'pending',
            'operation' => 'purge',
            'node_id' => $validated['node_id'],
            'message' => 'Purge request queued...',
            'user_id' => $request->user()->id,
        ], now()->addMinutes(10));

        NodeOperationJob::dispatch(
            operation: 'purge',
            nodeId: $validated['node_id'],
            reason: $validated['reason'] ?? 'Demo: full node purge',
            jobId: $jobId,
            userId: $request->user()->id,
        );

        return response()->json([
            'job_id' => $jobId,
            'status' => 'pending',
            'message' => 'Purge request queued. The SSM command will run in the background.',
        ], 202);
    }

    /**
     * Resync a node's data from peers (async).
     *
     * Dispatches a NodeOperationJob to the queue because the SSM command
     * takes 60-180s. Returns the job ID immediately; the frontend polls.
     */
    public function resyncNode(Request $request): JsonResponse
    {
        $this->authorize('manage-recoverable-data');

        $validated = $request->validate([
            'node_id' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $jobId = Str::uuid()->toString();

        // Store initial pending state
        Cache::put("node_operation:{$jobId}", [
            'status' => 'pending',
            'operation' => 'resync',
            'node_id' => $validated['node_id'],
            'message' => 'Resync request queued...',
            'user_id' => $request->user()->id,
        ], now()->addMinutes(10));

        NodeOperationJob::dispatch(
            operation: 'resync',
            nodeId: $validated['node_id'],
            reason: $validated['reason'] ?? 'Manual resync — data restored from peers',
            jobId: $jobId,
            userId: $request->user()->id,
        );

        return response()->json([
            'job_id' => $jobId,
            'status' => 'pending',
            'message' => 'Resync request queued. The SSM command will run in the background.',
        ], 202);
    }

    /**
     * Poll the status of an async node operation (purge or resync).
     *
     * Returns: { status: 'pending'|'running'|'done'|'failed', message, operation, node_id }
     */
    public function nodeOperationStatus(Request $request, string $jobId): JsonResponse
    {
        $this->authorize('manage-recoverable-data');

        $cached = Cache::get("node_operation:{$jobId}");

        if ($cached === null) {
            return response()->json([
                'status' => 'pending',
                'message' => 'Waiting for job to start...',
            ], 202);
        }

        $ownerId = data_get($cached, 'user_id');
        if ($ownerId !== null && $request->user()->id !== (int) $ownerId) {
            abort(403);
        }

        $httpStatus = $cached['status'] === 'done' ? 200 : ($cached['status'] === 'failed' ? 422 : 202);

        return response()->json($cached, $httpStatus);
    }
}
