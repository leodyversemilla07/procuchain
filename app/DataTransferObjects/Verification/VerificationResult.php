<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Verification;

use Carbon\Carbon;

/**
 * Verification Result DTO
 *
 * Represents the result of a single document verification check
 */
final class VerificationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $verificationType,
        public readonly string $fileKey,
        public readonly ?string $expectedHash,
        public readonly ?string $actualHash,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly Carbon $verifiedAt,
    ) {}

    /**
     * Check if hashes match
     */
    public function hashesMatch(): bool
    {
        if ($this->expectedHash === null || $this->actualHash === null) {
            return false;
        }

        return $this->expectedHash === $this->actualHash;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'verification_type' => $this->verificationType,
            'file_key' => $this->fileKey,
            'expected_hash' => $this->expectedHash,
            'actual_hash' => $this->actualHash,
            'hash_match' => $this->hashesMatch(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'verified_at' => $this->verifiedAt->toIso8601String(),
        ];
    }

    /**
     * Create a successful verification result
     */
    public static function success(
        string $fileKey,
        string $hash,
        string $verificationType = 'integrity'
    ): self {
        return new self(
            isValid: true,
            verificationType: $verificationType,
            fileKey: $fileKey,
            expectedHash: $hash,
            actualHash: $hash,
            errors: [],
            warnings: [],
            verifiedAt: now(),
        );
    }

    /**
     * Create a failed verification result
     */
    public static function failure(
        string $fileKey,
        ?string $expectedHash,
        ?string $actualHash,
        array $errors,
        string $verificationType = 'integrity',
        array $warnings = []
    ): self {
        return new self(
            isValid: false,
            verificationType: $verificationType,
            fileKey: $fileKey,
            expectedHash: $expectedHash,
            actualHash: $actualHash,
            errors: $errors,
            warnings: $warnings,
            verifiedAt: now(),
        );
    }
}
