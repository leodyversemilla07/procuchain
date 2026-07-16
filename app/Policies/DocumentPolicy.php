<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DocumentViewLog;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Services\ProcurementDataService;

class DocumentPolicy
{
    public function __construct(
        private readonly ProcurementDataService $procurementDataService,
        private readonly DocumentRepository $documentRepository,
        private readonly ProcurementPolicy $procurementPolicy,
    ) {}

    public function view(User $user, ?string $fileKey = null): bool
    {
        if (! $user->hasPermissionTo(Permission::VIEW_DOCUMENTS->value)) {
            return false;
        }

        return $this->canAccessScopedDocument($user, $fileKey);
    }

    public function download(User $user, ?string $fileKey = null): bool
    {
        if (! $user->hasPermissionTo(Permission::DOWNLOAD_DOCUMENTS->value)) {
            return false;
        }

        return $this->canAccessScopedDocument($user, $fileKey);
    }

    public function upload(User $user): bool
    {
        return $user->hasPermissionTo(Permission::UPLOAD_DOCUMENTS->value);
    }

    public function correct(User $user, ?string $documentReference = null): bool
    {
        if (! $user->hasAnyPermission([
            Permission::EDIT_PROCUREMENT->value,
            Permission::APPROVE_PROCUREMENT->value,
        ])) {
            return false;
        }

        return $this->canAccessScopedDocument($user, $documentReference);
    }

    private function canAccessScopedDocument(User $user, ?string $fileKey = null): bool
    {
        if (! $user->isBacSecretariat() || $fileKey === null) {
            return true;
        }

        $prNumber = $this->resolveProcurementNumber($fileKey);

        if ($prNumber === null) {
            return true;
        }

        return $this->procurementPolicy->view($user, $prNumber);
    }

    private function resolveProcurementNumber(string $fileKey): ?string
    {
        $document = $this->documentRepository->findByfileKey($fileKey)
            ?? $this->documentRepository->findByTxid($fileKey);

        if ($document !== null && $document->prNumber !== '') {
            return $document->prNumber;
        }

        $documentViewLog = DocumentViewLog::query()
            ->where('file_key', $fileKey)
            ->first();

        if ($documentViewLog !== null && ! empty($documentViewLog->pr_number)) {
            return $documentViewLog->pr_number;
        }

        $documentData = $this->procurementDataService->getDocumentDataByfileKey($fileKey)
            ?? $this->procurementDataService->validateDocumentExistsInBlockchain($fileKey);

        $prNumber = data_get($documentData, 'pr_number');
        if (is_string($prNumber) && $prNumber !== '') {
            return $prNumber;
        }

        $parts = explode('/', $fileKey);
        $derivedPrNumber = $parts[0] ?? null;

        return is_string($derivedPrNumber) && $derivedPrNumber !== ''
            ? $derivedPrNumber
            : null;
    }
}
