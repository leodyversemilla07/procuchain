<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Status Data Transfer Object
 *
 * Represents procurement status data from blockchain.
 */
final class StatusData
{
    public function __construct(
        public readonly string $prNumber,
        public readonly ?string $procurementTitle,
        public readonly string $stage,
        public readonly string $currentStatus,
        public readonly string $userAddress,
        public readonly Carbon $timestamp,
        public readonly ?string $previousStatus = null,
        public readonly ?array $metadata = null,
    ) {}

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'procurement_title' => $this->procurementTitle,
            'stage' => strtolower($this->stage),
            'current_status' => strtolower($this->currentStatus),
            'user_address' => $this->userAddress,
            'timestamp' => $this->timestamp->toIso8601String(),
            'previous_status' => $this->previousStatus ? strtolower($this->previousStatus) : null,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        return new self(
            prNumber: $data['pr_number'],
            procurementTitle: $data['procurement_title'] ?? null,
            stage: $data['stage'],
            currentStatus: $data['current_status'],
            userAddress: $data['user_address'] ?? '',
            timestamp: Carbon::parse($data['timestamp']),
            previousStatus: $data['previous_status'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Format timestamp to full date and time
     */
    public function getFormattedDateTime(): string
    {
        return $this->timestamp->format('M j, Y, g:i A');
    }

    /**
     * Format timestamp to date only
     */
    public function getFormattedDateOnly(): string
    {
        return $this->timestamp->format('M j, Y');
    }

    /**
     * Format timestamp to time only
     */
    public function getFormattedTimeOnly(): string
    {
        return $this->timestamp->format('g:i A');
    }
}
