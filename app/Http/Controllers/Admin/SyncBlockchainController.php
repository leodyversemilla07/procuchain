<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BlockchainRecordSyncService;

class SyncBlockchainController extends Controller
{
    public function __invoke(BlockchainRecordSyncService $syncService)
    {
        $counts = $syncService->syncAll();

        return response()->json(['success' => true, 'synced' => $counts]);
    }
}
