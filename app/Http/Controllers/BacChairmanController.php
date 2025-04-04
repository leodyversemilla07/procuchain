<?php

namespace App\Http\Controllers;

use App\Handlers\ProcurementViewHandler;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BacChairmanController extends BaseController
{
    private $procurementHandler;

    public function __construct(ProcurementViewHandler $procurementHandler)
    {
        $this->procurementHandler = $procurementHandler;
        $this->middleware('auth');
        $this->middleware('role:bac_chairman');
    }

    public function index()
    {
        return Inertia::render('bac-chairman/dashboard');
    }

    public function indexProcurementsList()
    {
        try {
            $procurements = $this->procurementHandler->getProcurementsList();

            return Inertia::render('procurements/procurements-list', [
                'procurements' => $procurements,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve procurements:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements: '.$e->getMessage(),
            ]);
        }
    }

    public function showProcurement($procurementId)
    {
        try {
            $procurement = $this->procurementHandler->getProcurementDetails($procurementId);

            if (! $procurement) {
                return Inertia::render('procurements/show', ['message' => 'Procurement not found']);
            }

            return Inertia::render('procurements/show', [
                'procurement' => $procurement,
                'now' => now()->toIso8601String(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurement:', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/show', [
                'error' => 'Failed to retrieve procurement: '.$e->getMessage(),
            ]);
        }
    }
}
