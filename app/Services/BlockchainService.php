<?php

namespace App\Services;

use App\Enums\StreamEnums;

class BlockchainService
{
    private $multiChain;

    protected $streamKeyService;

    public function __construct(MultichainService $multiChain, StreamKeyService $streamKeyService)
    {
        $this->multiChain = $multiChain;
        $this->streamKeyService = $streamKeyService;
    }

    public function getClient(): MultichainService
    {
        return $this->multiChain;
    }

    public function publishDocuments(
        string $procurementId,
        string $procurementTitle,
        string $state,
        string $status,
        array $metadataArray,
        string $userAddress
    ): void {
        $timestamp = now()->toIso8601String();
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

        $documentItems = [];
        foreach ($metadataArray as $index => $metadata) {
            $docData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'stage' => $state,
                'timestamp' => $timestamp,
                'document_index' => $index + 1,
                'document_type' => $metadata['document_type'],
                'hash' => $metadata['hash'],
                'file_key' => $metadata['file_key'],
                'user_address' => $userAddress,
                'file_size' => $metadata['file_size'],
                'stage_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
            ];
            $documentItems[] = [
                'key' => $streamKey,
                'data' => $docData,
            ];
        }
        $this->multiChain->publishMultiFrom($userAddress, StreamEnums::DOCUMENTS->value, $documentItems);

        $this->updateStatus($procurementId, $procurementTitle, $status, $state, $userAddress, $timestamp);

        $this->logEvent(
            $procurementId,
            $procurementTitle,
            $state,
            'Uploaded '.count($metadataArray)." finalized $state documents",
            count($metadataArray),
            $userAddress,
            'document_upload',
            'workflow',
            'info',
            $timestamp
        );
    }

    public function updateStatus(
        string $procurementId,
        string $procurementTitle,
        string $status,
        string $stage,
        string $userAddress,
        string $timestamp
    ): void {
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
        $statusData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'current_status' => $status,
            'stage' => $stage,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];
        $this->multiChain->publishFrom($userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);
    }

    public function logEvent(
        string $procurementId,
        string $procurementTitle,
        string $stage,
        string $details,
        int $documentCount,
        string $userAddress,
        string $eventType,
        string $category,
        string $severity,
        string $timestamp
    ): void {
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
        $eventData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'event_type' => $eventType,
            'stage' => $stage,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
            'details' => $details,
            'category' => $category,
            'severity' => $severity,
            'document_count' => $documentCount,
        ];
        $this->multiChain->publishFrom($userAddress, StreamEnums::EVENTS->value, $streamKey, $eventData);
    }

    public function handleStageTransition(
        string $procurementId,
        string $procurementTitle,
        string $fromStatus,
        string $toStatus,
        string $fromStage,
        string $toStage,
        string $userAddress,
        string $details
    ): void {
        $timestamp = now()->toIso8601String();
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

        $statusData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'previous_status' => $fromStatus,
            'current_status' => $toStatus,
            'previous_stage' => $fromStage,
            'stage' => $toStage,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];
        $this->multiChain->publishFrom($userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

        $this->logEvent(
            $procurementId,
            $procurementTitle,
            $toStage,
            "$details (from $fromStage:$fromStatus to $toStage:$toStatus)",
            0,
            $userAddress,
            'stage_transition',
            'workflow',
            'info',
            $timestamp
        );
    }
}
