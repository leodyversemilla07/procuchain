<?php

namespace App\Policies;

use App\Models\DocumentView;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Services\ProcurementDataService;

/**
 * Document policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 *   Gate::define('view-document',     [DocumentPolicy::class, 'view'])
 *   Gate::define('download-document', [DocumentPolicy::class, 'download'])
 *   Gate::define('upload-document',   [DocumentPolicy::class, 'upload'])
 *   Gate::define('correct-document',  [DocumentPolicy::class, 'correct'])
 *
 * Usage in controllers:
 *   $this->authorize('download-document');
 */
class DocumentPolicy
{
    public function __construct(
        private readonly ProcurementDataService $procurementDataService,
        private readonly DocumentRepository $documentRepository,
        private readonly ProcurementPolicy $procurementPolicy,
    ) {}

    /**
     * Determine whether the user can view documents (PDFs, file previews).
     */
    public function view(User $user, ?string $fileKey = null): bool
    {
        if (! $user->hasPermissionTo('view documents')) {
            return false;
        }

        return $this->canAccessScopedDocument($user, $fileKey);
    }

    /**
     * Determine whether the user can download procurement documents.
     */
    public function download(User $user, ?string $fileKey = null): bool
    {
        if (! $user->hasPermissionTo('download documents')) {
            return false;
        }

        return $this->canAccessScopedDocument($user, $fileKey);
    }

    /**
     * Determine whether the user can upload documents during a procurement stage.
     */
    public function upload(User $user): bool
    {
        return $user->hasPermissionTo('upload documents');
    }

    /**
     * Determine whether the user can submit a document correction.
     * Allowed for Admin, BAC Chairman, and BAC Secretariat.
     */
    public function correct(User $user, ?string $documentReference = null): bool
    {
        if (! $user->hasAnyPermission([
            'edit procurement',
            'approve procurement',
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
        $document = $this->documentRepository->findByFileKey($fileKey)
            ?? $this->documentRepository->findByTxid($fileKey);

        if ($document !== null && $document->prNumber !== '') {
            return $document->prNumber;
        }

        $documentData = $this->procurementDataService->getDocumentDataByFileKey($fileKey)
            ?? $this->procurementDataService->validateDocumentExistsInBlockchain($fileKey);

        $prNumber = data_get($documentData, 'pr_number');
        if (is_string($prNumber) && $prNumber !== '') {
            return $prNumber;
        }

        $documentView = DocumentView::query()
            ->where('file_key', $fileKey)
            ->first();

        if ($documentView !== null && ! empty($documentView->pr_number)) {
            return $documentView->pr_number;
        }

        $parts = explode('/', $fileKey);
        $derivedPrNumber = $parts[0] ?? null;

        return is_string($derivedPrNumber) && $derivedPrNumber !== ''
            ? $derivedPrNumber
            : null;
    }
}
