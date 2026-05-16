<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Event Data Transfer Object
 *
 * Represents audit event data from blockchain.
 */
final class EventData
{
    public function __construct(
        public readonly string $prNumber,
        public readonly string $procurementTitle,
        public readonly string $stage,
        public readonly string $eventType,
        public readonly string $category,
        public readonly string $severity,
        public readonly string $details,
        public readonly int $documentCount,
        public readonly string $userAddress,
        public readonly Carbon $timestamp,
        public readonly ?array $metadata = null,
    ) {}

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'procurement_title' => $this->procurementTitle,
            'stage' => $this->stage,
            'event_type' => $this->eventType,
            'category' => $this->category,
            'severity' => $this->severity,
            'details' => $this->details,
            'document_count' => $this->documentCount,
            'user_address' => $this->userAddress,
            'timestamp' => $this->timestamp->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        // Backward compatibility: try pr_number first, fall back to pr_number
        $prNumber = $data['pr_number'] ?? $data['pr_number'] ?? '';

        return new self(
            prNumber: $prNumber,
            procurementTitle: $data['procurement_title'],
            stage: $data['stage'],
            eventType: $data['event_type'],
            category: $data['category'],
            severity: $data['severity'],
            details: $data['details'],
            documentCount: (int) ($data['document_count'] ?? 0),
            userAddress: $data['user_address'],
            timestamp: Carbon::parse($data['timestamp'])->setTimezone('Asia/Manila'),
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
