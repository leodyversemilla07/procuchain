<?php

namespace App\Services;

use App\Enums\StreamEnums;
use Illuminate\Support\Facades\Log;

class DocumentBlockchainService
{
    public function __construct(private MultichainService $multichainService) {}

    /**
     * Get document data from blockchain by file key
     */
    public function getDocumentData(string $fileKey): ?array
    {
        try {
            Log::info('Attempting to get blockchain data', ['file_key' => $fileKey]);

            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if ($allDocumentItems === null) {
                Log::warning('Failed to retrieve document stream items.', ['file_key' => $fileKey]);

                return null;
            }

            Log::info('Retrieved document stream items', [
                'file_key' => $fileKey,
                'total_items' => count($allDocumentItems),
            ]);

            $documentItem = collect($allDocumentItems)
                ->filter(function ($item) use ($fileKey) {
                    $itemFileKey = $item['data']['json']['file_key'] ?? null;

                    Log::debug('Checking document item', [
                        'search_file_key' => $fileKey,
                        'item_file_key' => $itemFileKey,
                        'match' => $itemFileKey === $fileKey,
                    ]);

                    return isset($item['data']['json']['file_key']) &&
                        $item['data']['json']['file_key'] === $fileKey;
                })
                ->first();

            if (! $documentItem) {
                Log::info('No blockchain document found for file key', ['file_key' => $fileKey]);

                return null;
            }

            $data = $documentItem['data']['json'] ?? [];

            Log::info('Found blockchain document data', [
                'file_key' => $fileKey,
                'hash' => $data['hash'] ?? 'NOT SET',
                'procurement_id' => $data['procurement_id'] ?? 'NOT SET',
                'full_data_keys' => array_keys($data),
            ]);

            return [
                'procurement_id' => $data['procurement_id'] ?? 'Unknown',
                'procurement_title' => $data['procurement_title'] ?? 'Unknown Document',
                'document_type' => $data['document_type'] ?? pathinfo($fileKey, PATHINFO_FILENAME),
                'stage' => $data['stage'] ?? 'Unknown',
                'file_size' => $data['file_size'] ?? null,
                'timestamp' => $data['timestamp'] ?? now()->toISOString(),
                'hash' => $data['hash'] ?? '',
                'user_address' => $data['user_address'] ?? 'unknown@example.com',
                'stage_metadata' => $data['stage_metadata'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get document data from blockchain', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get current procurement status from blockchain
     */
    public function getCurrentProcurementStatus(string $procurementId): ?array
    {
        try {
            $statusItems = $this->multichainService->listStreamItems(
                StreamEnums::STATUS->value,
                true,
                1000,
                0,
                false
            );

            if ($statusItems === null) {
                return null;
            }

            $procurementStatuses = collect($statusItems)
                ->filter(function ($item) use ($procurementId) {
                    $data = $item['data']['json'] ?? [];

                    return ($data['procurement_id'] ?? '') === $procurementId;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['json']['timestamp'] ?? '';
                });

            $latestStatus = $procurementStatuses->first();

            if ($latestStatus) {
                $data = $latestStatus['data']['json'] ?? [];

                return [
                    'current_status' => $data['current_status'] ?? '',
                    'stage' => $data['stage'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get procurement status', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Alternative method to get document hash by procurement ID and file pattern matching
     */
    public function getHashByProcurementId(string $procurementId, string $fileKey): ?string
    {
        try {
            Log::info('Attempting alternative hash lookup', [
                'procurement_id' => $procurementId,
                'file_key' => $fileKey,
            ]);

            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if ($allDocumentItems === null) {
                return null;
            }

            $documentItem = collect($allDocumentItems)
                ->filter(function ($item) use ($procurementId, $fileKey) {
                    $data = $item['data']['json'] ?? [];
                    $itemProcurementId = $data['procurement_id'] ?? '';
                    $itemFileKey = $data['file_key'] ?? '';

                    if ($itemProcurementId === $procurementId) {
                        return true;
                    }

                    $fileKeyParts = explode('/', $fileKey);
                    $itemFileKeyParts = explode('/', $itemFileKey);

                    if (count($fileKeyParts) >= 1 && count($itemFileKeyParts) >= 1) {
                        return $fileKeyParts[0] === $itemFileKeyParts[0];
                    }

                    return false;
                })
                ->first();

            if ($documentItem) {
                $data = $documentItem['data']['json'] ?? [];
                $hash = $data['hash'] ?? null;

                Log::info('Alternative hash lookup result', [
                    'found_hash' => ! empty($hash),
                    'hash_value' => $hash,
                    'matched_file_key' => $data['file_key'] ?? 'NOT SET',
                ]);

                return $hash;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Alternative hash lookup failed', [
                'procurement_id' => $procurementId,
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that the file exists in document stream
     */
    public function validateDocumentExistsInBlockchain(string $fileKey): ?array
    {
        try {
            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if ($allDocumentItems === null) {
                return null;
            }

            foreach ($allDocumentItems as $item) {
                $data = $item['data']['json'] ?? [];
                if (($data['file_key'] ?? '') === $fileKey) {
                    return $data;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Blockchain validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
