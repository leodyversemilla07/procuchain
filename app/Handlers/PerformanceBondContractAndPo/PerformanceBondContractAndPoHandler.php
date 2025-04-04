<?php

namespace App\Handlers\PerformanceBondContractAndPo;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class PerformanceBondContractAndPoHandler extends BaseStageHandler
{
    /**
     * Handle the upload of performance bond, contract and purchase order documents.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);
            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in PerformanceBondContractAndPoHandler', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to upload ' . StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName() . ' documents: ' . $e->getMessage()];
        }
    }
    
    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'performanceBondFile' => $request->file('performance_bond_file'),
            'contractFile' => $request->file('contract_file'),
            'poFile' => $request->file('po_file'),
            'submissionDate' => $request->input('submission_date'),
            'bondAmount' => $request->input('bond_amount'),
            'signingDate' => $request->input('signing_date'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            'nextStage' => StageEnums::NOTICE_TO_PROCEED,
            'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED
        ];
    }
    
    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];
        
        if ($data['performanceBondFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['performanceBondFile']],
                [['document_type' => 'Performance Bond', 'submission_date' => $data['submissionDate'], 'bond_amount' => $data['bondAmount']]],
                $data['procurementId'],
                $data['procurementTitle'],
                'PerformanceBond'
            ));
        }

        if ($data['contractFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['contractFile']],
                [['document_type' => 'Contract', 'signing_date' => $data['signingDate']]],
                $data['procurementId'],
                $data['procurementTitle'],
                'ContractPO'
            ));
        }

        if ($data['poFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['poFile']],
                [['document_type' => 'PO', 'signing_date' => $data['signingDate']]],
                $data['procurementId'],
                $data['procurementTitle'],
                'ContractPO'
            ));
        }
        
        return $metadataArray;
    }
    
    private function processDocuments(array $data, array $metadataArray): array
    {
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to ' . $data['nextStage']->getDisplayName() . ' stage after recording documents'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'recorded',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName() . ' documents uploaded successfully. Proceeding to ' . $data['nextStage']->getDisplayName() . ' stage.'
        ];
    }
}
