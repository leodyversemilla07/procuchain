<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Services\DocumentValidationService;
use App\Services\StageDocumentRequirements;

beforeEach(function () {
    $this->requirements = new StageDocumentRequirements;
    $this->service = new DocumentValidationService($this->requirements);
});

describe('DocumentValidationService', function () {
    describe('validateUpload', function () {
        it('validates required document for procurement initiation stage', function () {
            $validation = $this->service->validateUpload(
                StageEnums::PROCUREMENT_INITIATION,
                DocumentTypeEnums::PURCHASE_REQUEST,
                []
            );

            expect($validation['valid'])->toBeTrue();
            expect($validation['errors'])->toBeEmpty();
            expect($validation['warnings'])->toBeEmpty();
        });

        it('validates optional document for procurement initiation stage', function () {
            $validation = $this->service->validateUpload(
                StageEnums::PROCUREMENT_INITIATION,
                DocumentTypeEnums::MARKET_RESEARCH,
                []
            );

            expect($validation['valid'])->toBeTrue();
            expect($validation['errors'])->toBeEmpty();
        });

        it('warns when uploading document from different stage in same phase', function () {
            $validation = $this->service->validateUpload(
                StageEnums::PROCUREMENT_INITIATION,
                DocumentTypeEnums::PRE_PROCUREMENT_AGENDA, // Stage 2 document
                []
            );

            expect($validation['valid'])->toBeTrue();
            expect($validation['warnings'])->not->toBeEmpty();
            expect($validation['warnings'][0])->toContain('typically required for');
        });

        it('errors when uploading document from different phase', function () {
            $validation = $this->service->validateUpload(
                StageEnums::PROCUREMENT_INITIATION,
                DocumentTypeEnums::NOTICE_OF_AWARD, // Stage 10 document (different phase)
                []
            );

            expect($validation['valid'])->toBeFalse();
            expect($validation['errors'])->not->toBeEmpty();
            expect($validation['errors'][0])->toContain('not valid for');
        });

        it('prevents duplicate document upload', function () {
            $uploadedTypes = [DocumentTypeEnums::PURCHASE_REQUEST];

            $validation = $this->service->validateUpload(
                StageEnums::PROCUREMENT_INITIATION,
                DocumentTypeEnums::PURCHASE_REQUEST,
                $uploadedTypes
            );

            expect($validation['valid'])->toBeTrue();
            expect($validation['warnings'])->not->toBeEmpty();
            expect($validation['warnings'][0])->toContain('already been uploaded');
        });
    });

    describe('validateStageCompletion', function () {
        it('returns false when no documents uploaded', function () {
            $validation = $this->service->validateStageCompletion(
                StageEnums::PROCUREMENT_INITIATION,
                []
            );

            expect($validation['can_complete'])->toBeFalse();
            expect($validation['completion_percentage'])->toBe(0.0);
            expect($validation['missing_documents'])->not->toBeEmpty();
        });

        it('returns false when only some required documents uploaded', function () {
            $uploadedDocs = [
                DocumentTypeEnums::PURCHASE_REQUEST,
                DocumentTypeEnums::PPMP,
                DocumentTypeEnums::APP,
            ];

            $validation = $this->service->validateStageCompletion(
                StageEnums::PROCUREMENT_INITIATION,
                $uploadedDocs
            );

            expect($validation['can_complete'])->toBeFalse();
            expect($validation['completion_percentage'])->toBeGreaterThan(0.0);
            expect($validation['completion_percentage'])->toBeLessThan(100.0);
            expect($validation['missing_documents'])->toContain(DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value);
        });

        it('returns true when all required documents uploaded', function () {
            $requiredDocs = $this->requirements->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            $validation = $this->service->validateStageCompletion(
                StageEnums::PROCUREMENT_INITIATION,
                $requiredDocs
            );

            expect($validation['can_complete'])->toBeTrue();
            expect($validation['completion_percentage'])->toBe(100.0);
            expect($validation['missing_documents'])->toBeEmpty();
        });

        it('includes uploaded and required documents in response', function () {
            $uploadedDocs = [
                DocumentTypeEnums::PURCHASE_REQUEST,
                DocumentTypeEnums::PPMP,
            ];

            $validation = $this->service->validateStageCompletion(
                StageEnums::PROCUREMENT_INITIATION,
                $uploadedDocs
            );

            expect($validation)->toHaveKeys([
                'can_complete',
                'required_documents',
                'uploaded_documents',
                'missing_documents',
                'completion_percentage',
            ]);
            expect($validation['uploaded_documents'])->toHaveCount(2);
        });
    });

    describe('calculateCompletionPercentage', function () {
        it('returns 0% when no documents uploaded', function () {
            $percentage = $this->service->calculateCompletionPercentage(
                StageEnums::PROCUREMENT_INITIATION,
                []
            );

            expect($percentage)->toBe(0.0);
        });

        it('returns 100% when all required documents uploaded', function () {
            $requiredDocs = $this->requirements->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            $percentage = $this->service->calculateCompletionPercentage(
                StageEnums::PROCUREMENT_INITIATION,
                $requiredDocs
            );

            expect($percentage)->toBe(100.0);
        });

        it('calculates correct percentage for partial upload', function () {
            $requiredDocs = $this->requirements->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);
            $requiredCount = count($requiredDocs);

            // Upload half of required documents
            $halfUploaded = array_slice($requiredDocs, 0, (int) ($requiredCount / 2));

            $percentage = $this->service->calculateCompletionPercentage(
                StageEnums::PROCUREMENT_INITIATION,
                $halfUploaded
            );

            expect($percentage)->toBeGreaterThan(40.0);
            expect($percentage)->toBeLessThan(60.0);
        });

        it('caps percentage at 100% even with extra documents', function () {
            $requiredDocs = $this->requirements->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);
            $optionalDocs = $this->requirements->getOptionalDocuments(StageEnums::PROCUREMENT_INITIATION);
            $allDocs = array_merge($requiredDocs, $optionalDocs);

            $percentage = $this->service->calculateCompletionPercentage(
                StageEnums::PROCUREMENT_INITIATION,
                $allDocs
            );

            expect($percentage)->toBe(100.0);
        });
    });

    describe('getStageDocumentGuide', function () {
        it('returns complete guide structure for stage', function () {
            $guide = $this->service->getStageDocumentGuide(StageEnums::PROCUREMENT_INITIATION);

            expect($guide)->toHaveKeys([
                'stage',
                'stage_display_name',
                'phase',
                'description',
                'required_documents',
                'optional_documents',
                'counts',
            ]);
        });

        it('includes document counts in guide', function () {
            $guide = $this->service->getStageDocumentGuide(StageEnums::PROCUREMENT_INITIATION);

            expect($guide['counts'])->toHaveKeys([
                'required_count',
                'optional_count',
                'total_count',
            ]);
            expect($guide['counts']['required_count'])->toBeGreaterThan(0);
            expect($guide['counts']['total_count'])->toBeGreaterThanOrEqual($guide['counts']['required_count']);
        });

        it('formats required documents with display names', function () {
            $guide = $this->service->getStageDocumentGuide(StageEnums::PROCUREMENT_INITIATION);

            expect($guide['required_documents'])->not->toBeEmpty();
            $firstDoc = $guide['required_documents'][0];

            expect($firstDoc)->toHaveKeys(['value', 'display_name', 'description']);
            expect($firstDoc['value'])->toBeString();
            expect($firstDoc['display_name'])->toBeString();
        });

        it('includes stage metadata', function () {
            $guide = $this->service->getStageDocumentGuide(StageEnums::PROCUREMENT_INITIATION);

            expect($guide['stage'])->toBe(StageEnums::PROCUREMENT_INITIATION->value);
            expect($guide['stage_display_name'])->toBe(StageEnums::PROCUREMENT_INITIATION->getDisplayName());
            expect($guide['phase'])->toBe('pre_procurement');
            expect($guide['description'])->toBeString();
        });
    });

    describe('Cross-Stage Validation', function () {
        it('validates documents across all pre-procurement stages', function () {
            $stages = [
                StageEnums::PROCUREMENT_INITIATION,
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StageEnums::BIDDING_DOCUMENTS,
            ];

            foreach ($stages as $stage) {
                $requiredDocs = $this->requirements->getRequiredDocuments($stage);
                expect($requiredDocs)->not->toBeEmpty();

                $validation = $this->service->validateStageCompletion($stage, $requiredDocs);
                expect($validation['can_complete'])->toBeTrue();
            }
        });

        it('validates documents across all procurement stages', function () {
            $stages = [
                StageEnums::PRE_BID_CONFERENCE,
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                StageEnums::BID_OPENING,
                StageEnums::BID_EVALUATION,
                StageEnums::POST_QUALIFICATION,
                StageEnums::BAC_RESOLUTION,
            ];

            foreach ($stages as $stage) {
                $requiredDocs = $this->requirements->getRequiredDocuments($stage);

                if (! empty($requiredDocs)) {
                    $validation = $this->service->validateStageCompletion($stage, $requiredDocs);
                    expect($validation['can_complete'])->toBeTrue();
                }
            }
        });

        it('validates documents across all post-procurement stages', function () {
            $stages = [
                StageEnums::NOTICE_OF_AWARD,
                StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                StageEnums::NOTICE_TO_PROCEED,
                StageEnums::MONITORING,
                StageEnums::COMPLETION,
            ];

            foreach ($stages as $stage) {
                $requiredDocs = $this->requirements->getRequiredDocuments($stage);

                if (! empty($requiredDocs)) {
                    $validation = $this->service->validateStageCompletion($stage, $requiredDocs);
                    expect($validation['can_complete'])->toBeTrue();
                }
            }
        });
    });

    describe('Edge Cases', function () {
        it('handles empty uploaded documents array', function () {
            $validation = $this->service->validateStageCompletion(
                StageEnums::PROCUREMENT_INITIATION,
                []
            );

            expect($validation['can_complete'])->toBeFalse();
            expect($validation['uploaded_documents'])->toBeEmpty();
        });

        it('handles stage with no optional documents', function () {
            $guide = $this->service->getStageDocumentGuide(StageEnums::BID_OPENING);

            expect($guide['optional_documents'])->toBeArray();
            expect($guide['counts']['optional_count'])->toBeGreaterThanOrEqual(0);
        });

        it('handles completed stage (no requirements)', function () {
            $requiredDocs = $this->requirements->getRequiredDocuments(StageEnums::COMPLETED);

            expect($requiredDocs)->toBeEmpty();

            $validation = $this->service->validateStageCompletion(StageEnums::COMPLETED, []);
            expect($validation['can_complete'])->toBeTrue();
            expect($validation['completion_percentage'])->toBe(100.0);
        });
    });
});
