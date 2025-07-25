<?php

namespace App\Http\Controllers;

use App\Services\SmartContractService;
use App\Services\MultichainService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class SmartContractController extends Controller
{
    public function __construct(
        private SmartContractService $smartContractService,
        private MultichainService $multichainService
    ) {}

    /**
     * Initialize document management smart contract system
     */
    public function initialize(): JsonResponse
    {
        try {
            // Create document management library
            $libraryResult = $this->smartContractService->createDocumentManagementLibrary();
            
            // Create document validation filters
            $filterResults = $this->smartContractService->createDocumentValidationFilters();
            
            return response()->json([
                'success' => true,
                'message' => 'Document management smart contract system initialized successfully',
                'data' => [
                    'library_txid' => $libraryResult,
                    'filters' => $filterResults
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Document management smart contract initialization failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize document management smart contract system',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Validate document integrity
     */
    public function validateDocumentIntegrity(Request $request): JsonResponse
    {
        $request->validate([
            'procurement_id' => 'required|string',
            'document_hash' => 'required|string|size:64'
        ]);

        try {
            $result = $this->smartContractService->validateDocumentIntegrity(
                $request->procurement_id,
                $request->document_hash
            );

            return response()->json([
                'success' => true,
                'message' => 'Document integrity validation completed',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Document integrity validation failed', [
                'procurement_id' => $request->procurement_id,
                'document_hash' => $request->document_hash,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document integrity validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check metadata compliance
     */
    public function checkMetadataCompliance(Request $request): JsonResponse
    {
        $request->validate([
            'metadata' => 'required|array',
            'stage' => 'required|string'
        ]);

        try {
            $result = $this->smartContractService->checkDocumentMetadataCompliance(
                $request->metadata,
                $request->stage
            );

            return response()->json([
                'success' => true,
                'message' => 'Metadata compliance check completed',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Metadata compliance check failed', [
                'stage' => $request->stage,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Metadata compliance check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate document storage consistency
     */
    public function validateStorageConsistency(Request $request): JsonResponse
    {
        $request->validate([
            'procurement_id' => 'required|string'
        ]);

        try {
            $result = $this->smartContractService->validateDocumentStorageConsistency(
                $request->procurement_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Document storage consistency validation completed',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Document storage consistency validation failed', [
                'procurement_id' => $request->procurement_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document storage consistency validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document audit trail
     */
    public function getAuditTrail(string $procurementId): JsonResponse
    {
        try {
            $auditTrailResult = $this->smartContractService->getDocumentAuditTrail($procurementId);

            return response()->json([
                'success' => true,
                'message' => 'Audit trail retrieved successfully',
                'data' => [
                    'procurement_id' => $auditTrailResult['procurement_id'],
                    'total_entries' => $auditTrailResult['total_entries'],
                    'audit_trail' => $auditTrailResult['audit_trail']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Audit trail retrieval failed', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit trail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system status
     */
    public function getStatus(): JsonResponse
    {
        try {
            // Check if blockchain is accessible
            $blockchainStatus = $this->multichainService->getInfo();
            
            return response()->json([
                'success' => true,
                'message' => 'Document management smart contract system is operational',
                'data' => [
                    'blockchain_status' => 'connected',
                    'blockchain_info' => $blockchainStatus,
                    'features' => [
                        'document_integrity_validation',
                        'document_metadata_compliance_checking',
                        'document_storage_consistency_validation',
                        'document_audit_trail_generation',
                        'document_validation_filters'
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Smart contract status check failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Smart contract system status check failed',
                'error' => $e->getMessage(),
                'data' => [
                    'blockchain_status' => 'disconnected'
                ]
            ], 500);
        }
    }
}
