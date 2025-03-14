<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class BacSecretariatController extends BaseController
{
    public function __construct()
    {
        $this->middleware('role:bac_secretariat');
    }

    public function index()
    {
        return Inertia::render('bac-secretariat/dashboard');
    }

    public function prInitiation()
    {
        return Inertia::render('bac-secretariat/procurement-phase/pr-initiation');
    }
    
    /**
     * Debug endpoint to help troubleshoot document categorization
     *
     * @param string $procurementId
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugDocuments($procurementId)
    {
        try {
            $procurementController = app()->make(ProcurementController::class);
            $procurement = $procurementController->getProcurementData($procurementId);
            
            $documentsByPhase = $procurement['documents_by_phase'] ?? [];
            
            // Check for documents that might be missing or miscategorized
            $allDocs = $procurement['documents'] ?? [];
            $prDocs = collect($allDocs)->filter(function($doc) {
                $docType = strtolower($doc['document_type'] ?? '');
                $fileKey = strtolower($doc['file_key'] ?? '');
                
                return (
                    strpos($docType, 'purchase') !== false || 
                    strpos($docType, 'pr') !== false ||
                    strpos($docType, 'aip') !== false ||
                    strpos($docType, 'certificate') !== false ||
                    strpos($fileKey, 'prinitiation') !== false ||
                    strpos($fileKey, 'purchase') !== false
                );
            })->values()->toArray();
            
            return response()->json([
                'procurement_id' => $procurementId,
                'title' => $procurement['title'] ?? 'Unknown',
                'all_phases' => array_keys($documentsByPhase),
                'expected_phases' => $procurement['phases'] ?? [],
                'pr_docs_found' => count($prDocs),
                'pr_docs' => $prDocs,
                'document_count_by_phase' => collect($documentsByPhase)->map(function($docs) {
                    return count($docs);
                })->toArray()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in debugDocuments', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error debugging documents: ' . $e->getMessage()
            ], 500);
        }
    }
}
