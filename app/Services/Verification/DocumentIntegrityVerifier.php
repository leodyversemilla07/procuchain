<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\DataTransferObjects\Verification\VerificationResult;
use App\Models\ProcurementDocument;
use App\Services\BlockchainStorageService;
use Exception;
use Illuminate\Support\Facades\Log;

final class DocumentIntegrityVerifier
{
    public function __construct(
        private readonly BlockchainStorageService $blockchainStorage,
    ) {}

    /**
     * Verify document integrity using SHA-256 hash comparison.
     */
    public function verify(string $fileKey, string $dataTxid): VerificationResult
    {
        try {
            $blockchainFileData = $this->blockchainStorage->retrieveFile($fileKey, $dataTxid);
            $storedHash = $blockchainFileData['hash'] ?? null;
            $actualHash = hash('sha256', $blockchainFileData['content']);
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
            $document = ProcurementDocument::with('procurement')->where('file_key', $fileKey)->first();

            if ($document === null) {
                return VerificationResult::failure(
                    fileKey: $fileKey,
                    expectedHash: null,
                    actualHash: null,
                    errors: ['Document not found with File key: '.$fileKey],
                );
            }

            return $this->verify($fileKey, $document->txid);
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
        $documents = ProcurementDocument::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('uploaded_at')
            ->get();

        $results = [];

        foreach ($documents as $doc) {
            $results[] = [
                'document' => [
                    'file_key' => $doc->file_key,
                    'document_type' => $doc->document_type,
                    'stage' => $doc->stage,
                    'uploaded_at' => $doc->uploaded_at?->toIso8601String(),
                ],
                'verification' => $this->verify($doc->file_key, $doc->txid)->toArray(),
            ];
        }

        Log::info('Batch document verification completed', [
            'pr_number' => $prNumber,
            'documents_verified' => count($results),
        ]);

        return $results;
    }
}
