<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlockchainStorageService;
use App\Services\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recoverable Data Controller
 *
 * Admin interface for viewing and restoring deleted blockchain files.
 * Demonstrates the blockchain recoverability architecture:
 * - Data is never permanently erased from the chain
 * - "Deletion" is an on-chain marker (action: 'deleted')
 * - Restoration publishes a counter-marker (action: 'restored')
 * - All actions are audit-logged per RA 12009 (NGPA)
 */
class RecoverableDataController extends Controller
{
    public function __construct(
        private BlockchainStorageService $storage,
    ) {}

    /**
     * Display the Recoverable Data admin page.
     * Shows all currently-deleted files that can be restored.
     */
    public function index(Request $request): Response
    {
        $deletedFiles = $this->storage->getDeletedFiles();

        $files = collect($deletedFiles)->values()->map(fn (array $file) => [
            'file_key' => $file['file_key'],
            'reason' => $file['reason'],
            'deleted_at' => $file['deleted_at'],
        ]);

        return Inertia::render('admin/recoverable-data', [
            'deletedFiles' => $files,
        ]);
    }

    /**
     * Restore a previously deleted file on blockchain.
     * Publishes a 'restored' action marker — the on-chain data was never removed.
     */
    public function restore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_key' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $fileKey = $validated['file_key'];
        $reason = $validated['reason'] ?? 'Restored by admin';

        $success = $this->storage->restoreFile($fileKey, $reason);

        if ($success) {
            return response()->json([
                'message' => 'File restored on blockchain. The restoration event is now on-chain and audit-logged.',
                'file_key' => $fileKey,
            ]);
        }

        return response()->json([
            'message' => 'Failed to restore file on blockchain.',
            'file_key' => $fileKey,
        ], 500);
    }
}
