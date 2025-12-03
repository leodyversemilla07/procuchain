<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\UploadedFile;

/**
 * Exception thrown when document upload fails
 */
class DocumentUploadException extends Exception
{
    /**
     * The file that failed to upload
     */
    protected ?string $filename = null;

    /**
     * The procurement ID associated with the upload
     */
    protected ?string $procurementId = null;

    /**
     * The stage during which the upload failed
     */
    protected ?string $stage = null;

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Document upload failed',
        ?string $filename = null,
        ?string $procurementId = null,
        ?string $stage = null,
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->filename = $filename;
        $this->procurementId = $procurementId;
        $this->stage = $stage;
        $this->context = $context;
    }

    /**
     * Get the filename that failed to upload
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get the procurement ID
     */
    public function getProcurementId(): ?string
    {
        return $this->procurementId;
    }

    /**
     * Get the stage during which upload failed
     */
    public function getStage(): ?string
    {
        return $this->stage;
    }

    /**
     * Get additional context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception for file validation failure
     */
    public static function validationFailed(
        UploadedFile $file,
        string $reason,
        ?string $procurementId = null
    ): self {
        return new self(
            message: "File validation failed: {$reason}",
            filename: $file->getClientOriginalName(),
            procurementId: $procurementId,
            context: [
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'reason' => $reason,
            ]
        );
    }

    /**
     * Create exception for storage failure
     */
    public static function storageFailed(
        string $filename,
        string $message,
        ?string $procurementId = null
    ): self {
        return new self(
            message: "Failed to store document: {$message}",
            filename: $filename,
            procurementId: $procurementId,
            context: ['storage_error' => $message]
        );
    }

    /**
     * Create exception for blockchain storage failure
     */
    public static function blockchainStorageFailed(
        string $filename,
        string $message,
        ?string $procurementId = null
    ): self {
        return new self(
            message: "Failed to store document hash on blockchain: {$message}",
            filename: $filename,
            procurementId: $procurementId,
            context: ['blockchain_error' => $message]
        );
    }

    /**
     * Create exception for invalid document type
     */
    public static function invalidDocumentType(
        string $filename,
        string $expectedType,
        string $actualType
    ): self {
        return new self(
            message: "Invalid document type: expected '{$expectedType}', got '{$actualType}'",
            filename: $filename,
            context: [
                'expected_type' => $expectedType,
                'actual_type' => $actualType,
            ]
        );
    }

    /**
     * Create exception for file size exceeded
     */
    public static function fileSizeExceeded(
        string $filename,
        int $maxSize,
        int $actualSize
    ): self {
        $maxSizeMB = round($maxSize / (1024 * 1024), 2);
        $actualSizeMB = round($actualSize / (1024 * 1024), 2);

        return new self(
            message: "File size ({$actualSizeMB}MB) exceeds maximum allowed ({$maxSizeMB}MB)",
            filename: $filename,
            context: [
                'max_size' => $maxSize,
                'actual_size' => $actualSize,
            ]
        );
    }
}
