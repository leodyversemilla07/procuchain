<?php

declare(strict_types=1);

namespace App\Services\Blockchain;

use App\Enums\StreamEnums;
use App\Services\AuditLogger;
use App\Services\Manager;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Manages file lifecycle operations on the blockchain.
 *
 * Handles soft-delete, restore, deletion-status checks, and
 * enumeration of deleted files. All state is stored as on-chain
 * markers in the file.metadata stream (immutable append-only).
 *
 * @see FileUploader for upload operations
 * @see FileRetriever for retrieval operations
 */
class FileLifecycleManager
{
    public function __construct(
        private Manager $multichain,
    ) {}

    /**
     * Mark a file as deleted on blockchain.
     * Note: File content remains on blockchain (immutable) but marked as deleted.
     */
    public function deleteFile(string $fileKey, string $reason = ''): bool
    {
        try {
            $dataKey = str_replace('/', '_', $fileKey);
            $deletionKey = $dataKey.'_deleted';

            $this->multichain->publish(StreamEnums::FILE_METADATA->value, $deletionKey, [
                'json' => [
                    'file_key' => $fileKey,
                    'data_key' => $dataKey,
                    'action' => 'deleted',
                    'reason' => $reason,
                    'deleted_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info('File marked as deleted on blockchain', [
                'file_key' => $fileKey,
                'reason' => $reason,
            ]);

            app(AuditLogger::class)->log(
                action: 'file.deleted',
                subjectType: 'file',
                subjectId: $fileKey,
                oldValues: ['file_key' => $fileKey, 'action' => 'deleted', 'reason' => $reason],
            );

            return true;
        } catch (Exception $e) {
            Log::error('File deletion marking failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Restore a previously deleted file on blockchain.
     * Publishes a 'restored' action marker — the on-chain data was never removed.
     */
    public function restoreFile(string $fileKey, string $reason = ''): bool
    {
        try {
            $dataKey = str_replace('/', '_', $fileKey);
            $deletionKey = $dataKey.'_deleted';

            $this->multichain->publish(StreamEnums::FILE_METADATA->value, $deletionKey, [
                'json' => [
                    'file_key' => $fileKey,
                    'data_key' => $dataKey,
                    'action' => 'restored',
                    'reason' => $reason,
                    'restored_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info('File restored on blockchain', [
                'file_key' => $fileKey,
                'reason' => $reason,
            ]);

            app(AuditLogger::class)->log(
                action: 'file.restored',
                subjectType: 'file',
                subjectId: $fileKey,
                newValues: ['file_key' => $fileKey, 'action' => 'restored', 'reason' => $reason],
            );

            return true;
        } catch (Exception $e) {
            Log::error('File restoration failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a file is currently marked as deleted on blockchain.
     *
     * @return bool True if the latest action is 'deleted'
     */
    public function isFileDeleted(string $fileKey): bool
    {
        try {
            $dataKey = str_replace('/', '_', $fileKey);
            $deletionKey = $dataKey.'_deleted';

            $items = $this->multichain->liststreamkeyitems(
                StreamEnums::FILE_METADATA->value,
                $deletionKey,
                false,
                100,
                0,
                false
            );

            if (empty($items)) {
                return false;
            }

            $latestItem = collect($items)->last();
            $action = $latestItem['data']['json']['action'] ?? 'restored';

            return $action === 'deleted';
        } catch (Exception $e) {
            Log::error('File deletion status check failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all currently deleted file keys from blockchain.
     *
     * @return array<string, array{file_key: string, reason: string, deleted_at: string}>
     */
    public function getDeletedFiles(): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::FILE_METADATA->value,
                true,
                10000,
                0,
                false
            );

            $deletedFiles = [];
            $statusMap = [];

            foreach ($items as $item) {
                $data = $item['data']['json'] ?? null;
                if (! $data) {
                    continue;
                }

                $action = $data['action'] ?? null;
                $fileKey = $data['file_key'] ?? null;

                if (! in_array($action, ['deleted', 'restored']) || ! $fileKey) {
                    continue;
                }

                $statusMap[$fileKey] = [
                    'file_key' => $fileKey,
                    'action' => $action,
                    'reason' => $data['reason'] ?? '',
                    'timestamp' => $data['deleted_at'] ?? $data['restored_at'] ?? now()->toIso8601String(),
                ];
            }

            foreach ($statusMap as $fileKey => $info) {
                if ($info['action'] === 'deleted') {
                    $prNumber = explode('/', $info['file_key'])[0];

                    $deletedFiles[$fileKey] = [
                        'file_key' => $info['file_key'],
                        'pr_number' => $prNumber,
                        'reason' => $info['reason'],
                        'deleted_at' => $info['timestamp'],
                    ];
                }
            }

            return $deletedFiles;
        } catch (Exception $e) {
            Log::error('Failed to get deleted files', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
