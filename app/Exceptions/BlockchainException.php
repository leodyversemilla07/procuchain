<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Base exception for all blockchain-related errors
 */
class BlockchainException extends Exception
{
    /**
     * The blockchain operation that failed
     */
    protected ?string $operation = null;

    /**
     * Additional context about the error
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'A blockchain error occurred',
        int $code = 0,
        ?Exception $previous = null,
        ?string $operation = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->operation = $operation;
        $this->context = $context;
    }

    /**
     * Get the blockchain operation that failed
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Get additional context about the error
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception for connection failure
     *
     * @param  array<string, mixed>  $context
     */
    public static function connectionFailed(string $message, array $context = []): self
    {
        return new self(
            message: "Blockchain connection failed: {$message}",
            operation: 'connection',
            context: $context
        );
    }

    /**
     * Create exception for publish failure
     *
     * @param  array<string, mixed>  $context
     */
    public static function publishFailed(string $stream, string $message, array $context = []): self
    {
        return new self(
            message: "Failed to publish to stream '{$stream}': {$message}",
            operation: 'publish',
            context: array_merge(['stream' => $stream], $context)
        );
    }

    /**
     * Create exception for stream read failure
     *
     * @param  array<string, mixed>  $context
     */
    public static function streamReadFailed(string $stream, string $message, array $context = []): self
    {
        return new self(
            message: "Failed to read from stream '{$stream}': {$message}",
            operation: 'stream_read',
            context: array_merge(['stream' => $stream], $context)
        );
    }
}
