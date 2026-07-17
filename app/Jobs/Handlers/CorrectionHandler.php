<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Enums\UserRole;
use App\Jobs\Handlers\Concerns\HandlesTempFiles;
use App\Models\Procurement;
use App\Models\User;
use App\Notifications\ProcurementCorrectionSubmitted;
use App\Services\Publishers\CorrectionPublisher;
use App\Services\Publishers\ProcurementCorrectionPublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CorrectionHandler
{
    use HandlesTempFiles;

    public function __construct(
        private readonly CorrectionPublisher $correctionPublisher,
        private readonly ProcurementCorrectionPublisher $procurementCorrectionPublisher,
    ) {}

    public function executeDocumentCorrection(array $data): array
    {
        $correctedFile = null;

        if (! empty($data['temp_file_path'])) {
            $correctedFile = $this->reconstituteTempFile(
                $data['temp_file_path'],
                $data['original_filename'],
                $data['mime_type'],
            );
        }

        try {
            $result = $this->correctionPublisher->publish(
                prNumber: $data['pr_number'],
                procurementTitle: $data['procurement_title'],
                originalTxid: $data['original_txid'],
                originalDocumentHash: $data['original_document_hash'],
                correctionType: $data['correction_type'],
                action: $data['action'],
                reason: $data['reason'],
                correctedBy: $data['corrected_by'],
                userAddress: $data['user_address'],
                correctedFile: $correctedFile,
                originalStage: $data['original_stage'] ?? null,
            );

            $this->sendCorrectionNotifications($data['pr_number'], $data['procurement_title'], $data['corrected_by'], $data['reason'], $result['correction_txid'] ?? null);

            return $result;
        } finally {
            if (! empty($data['temp_file_path'])) {
                $this->cleanupTempFile($data['temp_file_path']);
            }
        }
    }

    public function executeProcurementCorrection(array $data): array
    {
        $originalProcurement = Procurement::fromBlockchainArray($data['original_procurement']);

        $result = $this->procurementCorrectionPublisher->publishCorrection(
            originalProcurement: $originalProcurement,
            correctedData: $data['corrected_data'],
            reason: $data['reason'],
            correctedBy: $data['corrected_by'],
            userAddress: $data['user_address'],
        );

        $this->sendCorrectionNotifications($data['pr_number'] ?? $originalProcurement->pr_number, $originalProcurement->title, $data['corrected_by'], $data['reason'], $result['txid'] ?? null);

        return $result;
    }

    /**
     * Notify BAC Chairman, HOPE, and admin about corrections.
     */
    private function sendCorrectionNotifications(
        string $prNumber,
        string $procurementTitle,
        string $correctedBy,
        string $reason,
        ?string $correctionTxid = null,
    ): void {
        try {
            $changedFields = [];

            $usersToNotify = User::whereHas('roles', function ($query) {
                $query->whereIn('name', [UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value, UserRole::ADMIN->value]);
            })->get();

            if ($usersToNotify->isEmpty()) {
                Log::info('No users to notify for correction', [
                    'pr_number' => $prNumber,
                ]);

                return;
            }

            $notificationData = [
                'pr_number' => $prNumber,
                'procurement_title' => $procurementTitle,
                'corrected_by' => $correctedBy,
                'reason' => $reason,
                'changed_fields' => $changedFields,
                'timestamp' => now()->toIso8601String(),
                'correction_txid' => $correctionTxid ?? '',
            ];

            Notification::send($usersToNotify, new ProcurementCorrectionSubmitted($notificationData));

            Log::info('Correction notifications sent', [
                'pr_number' => $prNumber,
                'recipients_count' => $usersToNotify->count(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send correction notifications', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
