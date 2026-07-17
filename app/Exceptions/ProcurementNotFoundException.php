<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProcurementNotFoundException extends Exception
{
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

    public function getProcurementId(): ?string
    {
        return $this->procurementId;
    }

    public static function forId(string $procurementId): self
    {
        return new self(
            message: "Procurement with ID '{$procurementId}' not found",
            procurementId: $procurementId
        );
    }

    public static function forStage(string $procurementId, string $stage): self
    {
        return new self(
            message: "Procurement '{$procurementId}' not found in stage '{$stage}'",
            procurementId: $procurementId
        );
    }

    public function report(): bool
    {
        return false;
    }

    public function render(Request $request): Response
    {
        if ($request->wantsJson()) {
            return response([
                'message' => $this->getMessage(),
                'procurement_id' => $this->procurementId,
            ], 404);
        }

        return Inertia::render('error', ['status' => 404])
            ->toResponse($request)
            ->setStatusCode(404);
    }
}
