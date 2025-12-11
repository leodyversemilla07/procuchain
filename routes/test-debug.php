<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/debug/procurements', function () {
    try {
        Log::info('DEBUG: Starting procurement fetch test');
        
        $service = app(\App\Services\ProcurementDataService::class);
        $procurements = $service->fetchAndProcessProcurements();
        
        Log::info('DEBUG: Fetch completed', [
            'count' => count($procurements),
            'sample' => array_slice($procurements, 0, 2)
        ]);
        
        return response()->json([
            'success' => true,
            'count' => count($procurements),
            'procurements' => $procurements,
        ]);
    } catch (\Exception $e) {
        Log::error('DEBUG: Fetch failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
