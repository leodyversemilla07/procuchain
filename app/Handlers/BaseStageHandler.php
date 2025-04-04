<?php

namespace App\Handlers;

use App\Services\BlockchainService;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

abstract class BaseStageHandler implements StageHandlerInterface
{
    protected $blockchainService;
    protected $fileStorageService;
    protected $notificationService;

    public function __construct(
        BlockchainService $blockchainService,
        FileStorageService $fileStorageService,
        NotificationService $notificationService
    ) {
        $this->blockchainService = $blockchainService;
        $this->fileStorageService = $fileStorageService;
        $this->notificationService = $notificationService;
    }

    /**
     * This method must be implemented by all concrete stage handlers
     * 
     * @param Request $request
     * @return array
     */
    abstract public function handle(Request $request): array;

    protected function getUserBlockchainAddress(): string
    {
        $user = Auth::user();
        if (!$user || empty($user->blockchain_address)) {
            throw new Exception('Blockchain address not set for this user.');
        }
        return $user->blockchain_address;
    }

    protected function uploadAndPrepareMetadata(
        array $files,
        array $metadata,
        string $procurementId,
        string $procurementTitle,
        string $stageFolder
    ): array {
        $sanitizedTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $procurementTitle);

        $basePath = trim("$procurementId-$sanitizedTitle/$stageFolder", '/');

        $metadataArray = [];
        foreach ($files as $index => $file) {
            $documentType = $metadata[$index]['document_type'] ?? "doc-$index";
            $sanitizedDocumentType = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documentType);

            $fileKey = $this->fileStorageService->uploadFile(
                $file,
                $basePath . '/',
                $sanitizedDocumentType
            );
            $hash = hash('sha256', file_get_contents($file->getRealPath()));

            $metadataArray[] = array_merge(
                [
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $file->getSize(),
                ],
                $metadata[$index]
            );
        }
        return $metadataArray;
    }
}
