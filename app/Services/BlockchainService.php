<?php

namespace App\Services;

use App\Enums\StreamEnums;
use Exception;
use Illuminate\Support\Facades\Log;

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
        try {
            if (empty($procurementId) || empty($procurementTitle)) {
                throw new Exception('Procurement ID and title are required');
            }

            if (empty($metadataArray)) {
                throw new Exception('Document metadata array cannot be empty');
            }

            if (! $this->multiChain->validateAddress($userAddress)) {
                throw new Exception("Invalid blockchain address: $userAddress");
            }

            $timestamp = now()->toIso8601String();
            $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

            Log::info('Generated stream key', [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'stream_key' => $streamKey,
            ]);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $requiredFields = ['document_type', 'hash', 'file_key', 'file_size'];
                foreach ($requiredFields as $field) {
                    if (! isset($metadata[$field])) {
                        throw new Exception("Missing required metadata field: $field");
                    }
                }

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
                    'data' => ['json' => $docData],
                ];
            }

            Log::info('Publishing documents to blockchain', [
                'procurement_id' => $procurementId,
                'document_count' => count($metadataArray),
                'user_address' => $userAddress,
                'stream_key' => $streamKey,
                'first_document_item' => $documentItems[0] ?? null,
            ]);

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

        } catch (Exception $e) {
            Log::error('Failed to publish documents to blockchain', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function updateStatus(
        string $procurementId,
        string $procurementTitle,
        string $status,
        string $stage,
        string $userAddress,
        string $timestamp
    ): void {
        try {
            if (empty($procurementId) || empty($procurementTitle)) {
                throw new Exception('Procurement ID and title are required');
            }

            if (empty($status) || empty($stage)) {
                throw new Exception('Status and stage are required');
            }

            if (! $this->multiChain->validateAddress($userAddress)) {
                throw new Exception("Invalid blockchain address: $userAddress");
            }

            $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
            $statusData = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_status' => $status,
                    'stage' => $stage,
                    'timestamp' => $timestamp,
                    'user_address' => $userAddress,
                ],
            ];

            Log::info('Updating procurement status on blockchain', [
                'procurement_id' => $procurementId,
                'status' => $status,
                'stage' => $stage,
            ]);

            $this->multiChain->publishFrom($userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

        } catch (Exception $e) {
            Log::error('Failed to update status on blockchain', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        try {
            if (empty($procurementId) || empty($procurementTitle)) {
                throw new Exception('Procurement ID and title are required');
            }

            if (empty($details) || empty($eventType)) {
                throw new Exception('Event details and type are required');
            }

            if (! $this->multiChain->validateAddress($userAddress)) {
                throw new Exception("Invalid blockchain address: $userAddress");
            }

            if (! in_array($severity, ['info', 'warning', 'error'])) {
                throw new Exception('Invalid severity level. Must be info, warning, or error');
            }

            $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
            $eventData = [
                'json' => [
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
                ],
            ];

            Log::info('Logging procurement event to blockchain', [
                'procurement_id' => $procurementId,
                'event_type' => $eventType,
                'severity' => $severity,
            ]);

            $this->multiChain->publishFrom($userAddress, StreamEnums::EVENTS->value, $streamKey, $eventData);

        } catch (Exception $e) {
            Log::error('Failed to log event to blockchain', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        try {
            if (empty($procurementId) || empty($procurementTitle)) {
                throw new Exception('Procurement ID and title are required');
            }

            if (empty($fromStage) || empty($toStage)) {
                throw new Exception('From and to stages are required');
            }

            if (! $this->multiChain->validateAddress($userAddress)) {
                throw new Exception("Invalid blockchain address: $userAddress");
            }

            $timestamp = now()->toIso8601String();
            $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

            Log::info('Processing stage transition', [
                'procurement_id' => $procurementId,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
            ]);

            $statusData = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'previous_status' => $fromStatus,
                    'current_status' => $toStatus,
                    'previous_stage' => $fromStage,
                    'stage' => $toStage,
                    'timestamp' => $timestamp,
                    'user_address' => $userAddress,
                ],
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

        } catch (Exception $e) {
            Log::error('Failed to handle stage transition', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
