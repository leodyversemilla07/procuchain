<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\Verification\VerificationResult;
use App\Repositories\DocumentRepository;
use App\Services\BlockchainStorageService;
use Exception;
use Illuminate\Support\Facades\Log;

final class DocumentIntegrityVerifier
{
    public function __construct(
        private readonly BlockchainStorageService $blockchainStorage,
        private readonly DocumentRepository $documentRepository,
    ) {}

    /**
     * Verify document integrity using SHA-256 hash comparison.
     */
    public function verify(string $fileKey, string $dataTxid): VerificationResult
    {
        try {
            $BlockchainFileData = $this->blockchainStorage->retrieveFile($fileKey, $dataTxid);
            $storedHash = $BlockchainFileData['hash'] ?? null;
            $actualHash = hash('sha256', $BlockchainFileData['content']);
            $isValid = $storedHash === $actualHash;

            Log::info('Document integrity verification completed', [
                'file_key' => $fileKey,
                'is_valid' => $isValid,
                'hash_match' => $storedHash === $actualHash,
            ]);

            if ($isValid) {
                return VerificationResult::success($fileKey, $actualHash, 'integrity');
            }

            return VerificationResult::failure(
                fileKey: $fileKey,
                expectedHash: $storedHash,
                actualHash: $actualHash,
                errors: ['Document integrity compromised: hash mismatch detected'],
                verificationType: 'integrity',
            );
        } catch (Exception $e) {
            Log::error('Document integrity verification failed', [
                'file_key' => $fileKey,
                'data_txid' => $dataTxid,
                'error' => $e->getMessage(),
            ]);

            return VerificationResult::failure(
                fileKey: $fileKey,
                expectedHash: null,
                actualHash: null,
                errors: ['Verification failed: '.$e->getMessage()],
                verificationType: 'integrity',
            );
        }
    }

    /**
     * Verify a single document by File key.
     */
    public function verifySingle(string $fileKey): VerificationResult
    {
        try {
            $document = $this->documentRepository->findByfileKey($fileKey);

            if ($document === null) {
                return VerificationResult::failure(
                    fileKey: $fileKey,
                    expectedHash: null,
                    actualHash: null,
                    errors: ['Document not found with File key: '.$fileKey],
                );
            }

            return $this->verify($fileKey, $document->dataTxid);
        } catch (Exception $e) {
            Log::error('Single document verification failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return VerificationResult::failure(
                fileKey: $fileKey,
                expectedHash: null,
                actualHash: null,
                errors: ['Verification failed: '.$e->getMessage()],
            );
        }
    }

    /**
     * Batch verify all documents for a procurement.
     */
    public function batchVerify(string $prNumber): array
    {
        $documents = $this->documentRepository->findByProcurement($prNumber);
        $results = [];

        foreach ($documents as $doc) {
            $results[] = [
                'document' => [
                    'file_key' => $doc->fileKey,
                    'document_type' => $doc->documentType,
                    'stage' => $doc->stage,
                    'uploaded_at' => $doc->timestamp->toIso8601String(),
                ],
                'verification' => $this->verify($doc->fileKey, $doc->dataTxid)->toArray(),
            ];
        }

        Log::info('Batch document verification completed', [
            'pr_number' => $prNumber,
            'documents_verified' => count($results),
        ]);

        return $results;
    }
}
