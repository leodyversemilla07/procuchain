<?php

use App\DataTransferObjects\Verification\CompletenessResult;
use App\DataTransferObjects\Verification\ComplianceResult;
use App\DataTransferObjects\Verification\CrossReferenceResult;
use App\DataTransferObjects\Verification\VerificationReportDTO;
use App\DataTransferObjects\Verification\VerificationResult;
use App\Enums\StageEnums;

beforeEach(function () {
    // Set up Carbon for testing
    Carbon\Carbon::setTestNow('2025-12-02 10:00:00');
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

describe('VerificationResult DTO', function () {
    it('creates a successful verification result', function () {
        $result = VerificationResult::success('test-file-key', 'abc123hash', 'integrity');

        expect($result->isValid)->toBeTrue();
        expect($result->verificationType)->toBe('integrity');
        expect($result->fileKey)->toBe('test-file-key');
        expect($result->expectedHash)->toBe('abc123hash');
        expect($result->actualHash)->toBe('abc123hash');
        expect($result->hashesMatch())->toBeTrue();
        expect($result->errors)->toBeEmpty();
        expect($result->warnings)->toBeEmpty();
    });

    it('creates a failed verification result', function () {
        $result = VerificationResult::failure(
            fileKey: 'test-file-key',
            expectedHash: 'expected123',
            actualHash: 'actual456',
            errors: ['Hash mismatch detected'],
            verificationType: 'integrity',
            warnings: ['File may be corrupted'],
        );

        expect($result->isValid)->toBeFalse();
        expect($result->hashesMatch())->toBeFalse();
        expect($result->errors)->toContain('Hash mismatch detected');
        expect($result->warnings)->toContain('File may be corrupted');
    });

    it('converts to array correctly', function () {
        $result = VerificationResult::success('test-file-key', 'abc123', 'integrity');
        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'is_valid',
            'verification_type',
            'file_key',
            'expected_hash',
            'actual_hash',
            'hash_match',
            'errors',
            'warnings',
            'verified_at',
        ]);
        expect($array['is_valid'])->toBeTrue();
        expect($array['hash_match'])->toBeTrue();
    });

    it('handles null hashes correctly', function () {
        $result = VerificationResult::failure(
            fileKey: 'test-file-key',
            expectedHash: null,
            actualHash: null,
            errors: ['Document not found'],
        );

        expect($result->hashesMatch())->toBeFalse();
    });
});

describe('CompletenessResult DTO', function () {
    it('creates from validation result', function () {
        $validationResult = [
            'can_complete' => true,
            'completion_percentage' => 100.0,
            'required_documents' => ['purchase_request', 'ppmp'],
            'uploaded_documents' => ['purchase_request', 'ppmp'],
            'missing_documents' => [],
        ];

        $result = CompletenessResult::fromValidation(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            validationResult: $validationResult,
        );

        expect($result->isComplete)->toBeTrue();
        expect($result->prNumber)->toBe('PR-2025-001-0001');
        expect($result->stage)->toBe(StageEnums::PROCUREMENT_INITIATION);
        expect($result->completionPercentage)->toBe(100.0);
        expect($result->canCompleteStage())->toBeTrue();
    });

    it('calculates document counts correctly', function () {
        $result = new CompletenessResult(
            isComplete: false,
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            completionPercentage: 50.0,
            requiredDocuments: ['doc1', 'doc2', 'doc3', 'doc4'],
            uploadedDocuments: ['doc1', 'doc2'],
            missingDocuments: ['doc3', 'doc4'],
            errors: [],
            warnings: [],
            verifiedAt: now(),
        );

        expect($result->getRequiredCount())->toBe(4);
        expect($result->getUploadedCount())->toBe(2);
        expect($result->getMissingCount())->toBe(2);
    });

    it('converts to array with all keys', function () {
        $result = CompletenessResult::fromValidation(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            validationResult: ['can_complete' => true, 'completion_percentage' => 100.0],
        );

        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'is_complete',
            'pr_number',
            'stage',
            'stage_display_name',
            'completion_percentage',
            'required_documents',
            'uploaded_documents',
            'missing_documents',
            'document_counts',
            'can_complete_stage',
            'errors',
            'warnings',
            'verified_at',
        ]);
    });
});

describe('CrossReferenceResult DTO', function () {
    it('creates consistent result', function () {
        $result = CrossReferenceResult::consistent(
            prNumber: 'PR-2025-001-0001',
            prNumberChecks: [
                ['document_type' => 'Purchase Request', 'matches' => true],
                ['document_type' => 'PPMP', 'matches' => true],
            ],
        );

        expect($result->isConsistent)->toBeTrue();
        expect($result->prNumber)->toBe('PR-2025-001-0001');
        expect($result->hasPrNumberMismatch())->toBeFalse();
        expect($result->getTotalIssues())->toBe(0);
    });

    it('creates inconsistent result with errors', function () {
        $result = CrossReferenceResult::inconsistent(
            prNumber: 'PR-2025-001-0001',
            errors: ['PR number mismatch in document'],
            prNumberChecks: [
                ['document_type' => 'Purchase Request', 'matches' => false],
            ],
        );

        expect($result->isConsistent)->toBeFalse();
        expect($result->hasPrNumberMismatch())->toBeTrue();
        expect($result->getTotalIssues())->toBe(1);
    });

    it('converts to array with summary', function () {
        $result = CrossReferenceResult::consistent('PR-2025-001-0001');
        $array = $result->toArray();

        expect($array)->toHaveKey('summary');
        expect($array['summary'])->toHaveKeys([
            'total_issues',
            'total_warnings',
            'has_pr_mismatch',
            'has_amount_inconsistency',
        ]);
    });
});

describe('ComplianceResult DTO', function () {
    it('creates compliant result', function () {
        $result = ComplianceResult::compliant(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            documentTypeChecks: [
                ['document_type' => 'Purchase Request', 'valid' => true],
            ],
        );

        expect($result->isCompliant)->toBeTrue();
        expect($result->stage)->toBe(StageEnums::PROCUREMENT_INITIATION);
        expect($result->getViolationsCount())->toBe(0);
    });

    it('creates non-compliant result with violations', function () {
        $result = ComplianceResult::nonCompliant(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            errors: ['Invalid document format'],
            documentTypeChecks: [
                ['document_type' => 'Invalid Doc', 'valid' => false],
            ],
        );

        expect($result->isCompliant)->toBeFalse();
        expect($result->getViolationsCount())->toBe(1);
        expect($result->hasDocumentTypeViolations())->toBeTrue();
    });

    it('converts to array correctly', function () {
        $result = ComplianceResult::compliant('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);
        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'is_compliant',
            'pr_number',
            'stage',
            'stage_display_name',
            'document_type_checks',
            'timeline_checks',
            'procurement_mode_checks',
            'summary',
            'errors',
            'warnings',
            'verified_at',
        ]);
    });
});

describe('VerificationReportDTO', function () {
    it('creates from individual results', function () {
        $completenessResult = CompletenessResult::fromValidation(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            validationResult: ['can_complete' => true, 'completion_percentage' => 100.0],
        );

        $crossRefResult = CrossReferenceResult::consistent('PR-2025-001-0001');
        $complianceResult = ComplianceResult::compliant('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);

        $report = VerificationReportDTO::fromResults(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            integrityResults: [
                ['is_valid' => true, 'file_key' => 'test-key'],
            ],
            completenessResult: $completenessResult,
            crossReferenceResult: $crossRefResult,
            complianceResult: $complianceResult,
            verifiedBy: 1,
        );

        expect($report->prNumber)->toBe('PR-2025-001-0001');
        expect($report->overallValid)->toBeTrue();
        expect($report->getOverallStatus())->toBe('verified');
    });

    it('determines failed status when integrity fails', function () {
        $completenessResult = CompletenessResult::fromValidation(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            validationResult: ['can_complete' => true, 'completion_percentage' => 100.0],
        );

        $crossRefResult = CrossReferenceResult::consistent('PR-2025-001-0001');
        $complianceResult = ComplianceResult::compliant('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);

        $report = VerificationReportDTO::fromResults(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            integrityResults: [
                ['is_valid' => false, 'file_key' => 'test-key', 'errors' => ['Hash mismatch']],
            ],
            completenessResult: $completenessResult,
            crossReferenceResult: $crossRefResult,
            complianceResult: $complianceResult,
        );

        expect($report->overallValid)->toBeFalse();
        expect($report->getOverallStatus())->toBe('failed');
        expect($report->getCriticalIssuesCount())->toBeGreaterThan(0);
    });

    it('counts warnings from all results', function () {
        $completenessResult = new CompletenessResult(
            isComplete: true,
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            completionPercentage: 100.0,
            requiredDocuments: [],
            uploadedDocuments: [],
            missingDocuments: [],
            errors: [],
            warnings: ['Warning 1', 'Warning 2'],
            verifiedAt: now(),
        );

        $crossRefResult = CrossReferenceResult::consistent('PR-2025-001-0001', warnings: ['Warning 3']);
        $complianceResult = ComplianceResult::compliant('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION, warnings: ['Warning 4']);

        $report = VerificationReportDTO::fromResults(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            integrityResults: [
                ['is_valid' => true, 'warnings' => ['Warning 5']],
            ],
            completenessResult: $completenessResult,
            crossReferenceResult: $crossRefResult,
            complianceResult: $complianceResult,
        );

        expect($report->getWarningsCount())->toBe(5);
    });

    it('converts to array with all fields', function () {
        $completenessResult = CompletenessResult::fromValidation(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            validationResult: ['can_complete' => true, 'completion_percentage' => 100.0],
        );

        $crossRefResult = CrossReferenceResult::consistent('PR-2025-001-0001');
        $complianceResult = ComplianceResult::compliant('PR-2025-001-0001', StageEnums::PROCUREMENT_INITIATION);

        $report = VerificationReportDTO::fromResults(
            prNumber: 'PR-2025-001-0001',
            stage: StageEnums::PROCUREMENT_INITIATION,
            integrityResults: [],
            completenessResult: $completenessResult,
            crossReferenceResult: $crossRefResult,
            complianceResult: $complianceResult,
        );

        $array = $report->toArray();

        expect($array)->toHaveKeys([
            'pr_number',
            'stage',
            'stage_display_name',
            'overall_valid',
            'overall_status',
            'integrity_results',
            'completeness_result',
            'cross_reference_result',
            'compliance_result',
            'summary',
            'generated_at',
            'verified_by',
        ]);
    });
});
