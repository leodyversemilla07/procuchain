<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Correction Data Transfer Object
 *
 * Represents immutable correction metadata stored on blockchain (procurement.corrections stream)
 */
final class CorrectionData
{
    public function __construct(
        public readonly ?string $txid,
        public readonly string $prNumber,
        public readonly string $procurementTitle,
        public readonly string $originalTxid,
        public readonly string $originalDocumentHash,
        public readonly string $correctionType,
        public readonly string $action,
        public readonly string $reason,
        public readonly string $correctedBy,
        public readonly string $userAddress,
        public readonly Carbon $timestamp,
        public readonly ?array $correctedMetadata = null,
    ) {}

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'procurement_title' => $this->procurementTitle,
            'original_txid' => $this->originalTxid,
            'original_document_hash' => $this->originalDocumentHash,
            'correction_type' => $this->correctionType,
            'action' => $this->action,
            'reason' => $this->reason,
            'corrected_by' => $this->correctedBy,
            'user_address' => $this->userAddress,
            'timestamp' => $this->timestamp->toIso8601String(),
            'corrected_metadata' => $this->correctedMetadata,
        ];
    }

    public static function fromBlockchainArray(array $data, string $txid): self
    {
        // Backward compatibility: try pr_number first, fall back to pr_number
        $prNumber = $data['pr_number'] ?? $data['pr_number'] ?? '';

        // Handle corrected_by as either string or array
        $correctedBy = $data['corrected_by'] ?? '';
        if (is_array($correctedBy)) {
            $correctedBy = $correctedBy['name'] ?? ($correctedBy['id'] ?? '');
        }

        return new self(
            txid: $txid,
            prNumber: $prNumber,
            procurementTitle: $data['procurement_title'] ?? '',
            originalTxid: $data['original_txid'] ?? '',
            originalDocumentHash: $data['original_document_hash'] ?? '',
            correctionType: $data['correction_type'] ?? '',
            action: $data['action'] ?? '',
            reason: $data['reason'] ?? '',
            correctedBy: (string) $correctedBy,
            userAddress: $data['user_address'] ?? '',
            timestamp: Carbon::parse($data['timestamp'] ?? now())->setTimezone('Asia/Manila'),
            correctedMetadata: $data['corrected_metadata'] ?? null,
        );
    }
}
