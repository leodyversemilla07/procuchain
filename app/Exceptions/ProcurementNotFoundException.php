<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Exception thrown when a procurement record is not found
 */
class ProcurementNotFoundException extends Exception
{
    /**
     * The procurement ID that was not found
     */
    protected ?string $procurementId = null;

    public function __construct(
        string $message = 'Procurement record not found',
        ?string $procurementId = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->procurementId = $procurementId;
    }

    /**
     * Get the procurement ID that was not found
     */
    public function getProcurementId(): ?string
    {
        return $this->procurementId;
    }

    /**
     * Create exception for a specific procurement ID
     */
    public static function forId(string $procurementId): self
    {
        return new self(
            message: "Procurement with ID '{$procurementId}' not found",
            procurementId: $procurementId
        );
    }

    /**
     * Create exception for a specific stage
     */
    public static function forStage(string $procurementId, string $stage): self
    {
        return new self(
            message: "Procurement '{$procurementId}' not found in stage '{$stage}'",
            procurementId: $procurementId
        );
    }

    /**
     * Report the exception
     */
    public function report(): bool
    {
        // Don't report 404s to error tracking services
        return false;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(Request $request): Response
    {
        if ($request->wantsJson()) {
            return response([
                'message' => $this->getMessage(),
                'procurement_id' => $this->procurementId,
            ], 404);
        }

        return response(view('errors.404', [
            'message' => $this->getMessage(),
        ]), 404);
    }
}
