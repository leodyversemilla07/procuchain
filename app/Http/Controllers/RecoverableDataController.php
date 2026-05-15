<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlockchainStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * All mutations return RedirectResponse for Inertia compatibility
 * (router.post auto-handles XSRF tokens, no manual CSRF needed).
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
            'prNumbers' => $prNumbers,
            'nodes' => $this->storage->getAvailableNodes(),
        ]);
    }

    /**
     * Restore a previously deleted file on blockchain.
     * Publishes a 'restored' action marker — the on-chain data was never removed.
     */
    public function restore(Request $request): RedirectResponse
    {
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
     * Purge a file's data from a single node's local storage.
     * The data remains on all other nodes and will be re-synced automatically.
     * Recorded on-chain as action: 'node_purge' for audit compliance.
     */
    public function deleteFromNode(Request $request): RedirectResponse
    {
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
     * Resync a node's stream data from its peers.
     * After a single-node purge, this triggers the node to re-download
     * all missing stream items from connected nodes.
     */
    public function resyncNode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'node_id' => 'required|string',
        ]);

        $result = $this->storage->resyncNode($validated['node_id']);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
