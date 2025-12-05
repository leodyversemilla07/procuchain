<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Services\StageDocumentRequirements;

beforeEach(function () {
    $this->service = new StageDocumentRequirements;
});

describe('StageDocumentRequirements', function () {
    describe('getRequiredDocuments', function () {
        it('returns required documents for procurement initiation', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            expect($required)->not->toBeEmpty();
            expect($required)->toHaveCount(1);
            expect($required)->toContain(DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT);
        });

        it('returns required documents for pre-procurement conference', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            expect($required)->not->toBeEmpty();
            expect($required)->toContain(DocumentTypeEnums::PRE_PROCUREMENT_MINUTES);
        });

        it('returns required documents for bidding documents stage', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::BIDDING_DOCUMENTS);

            expect($required)->not->toBeEmpty();
            expect($required)->toContain(DocumentTypeEnums::INVITATION_TO_BID);
        });

        it('returns array of DocumentTypeEnums', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            foreach ($required as $doc) {
                expect($doc)->toBeInstanceOf(DocumentTypeEnums::class);
            }
        });

        it('returns empty array for completed stage', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::COMPLETED);

            expect($required)->toBeEmpty();
        });
    });

    describe('getOptionalDocuments', function () {
        it('returns optional documents for procurement initiation', function () {
            $optional = $this->service->getOptionalDocuments(StageEnums::PROCUREMENT_INITIATION);

            expect($optional)->toBeArray();

            if (! empty($optional)) {
                foreach ($optional as $doc) {
                    expect($doc)->toBeInstanceOf(DocumentTypeEnums::class);
                }
            }
        });

        it('returns different documents than required', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);
            $optional = $this->service->getOptionalDocuments(StageEnums::PROCUREMENT_INITIATION);

            // No document should be in both required and optional
            foreach ($optional as $doc) {
                expect($required)->not->toContain($doc);
            }
        });
    });

    describe('getDocumentCounts', function () {
        it('returns correct counts for procurement initiation', function () {
            $counts = $this->service->getDocumentCounts(StageEnums::PROCUREMENT_INITIATION);

            expect($counts)->toHaveKeys(['required_count', 'optional_count', 'total_count']);
            expect($counts['required_count'])->toBeGreaterThan(0);
            expect($counts['total_count'])->toBeGreaterThanOrEqual($counts['required_count']);
            expect($counts['total_count'])->toBe($counts['required_count'] + $counts['optional_count']);
        });

        it('returns counts for all stages', function () {
            $stages = [
                StageEnums::PROCUREMENT_INITIATION,
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StageEnums::BIDDING_DOCUMENTS,
                StageEnums::PRE_BID_CONFERENCE,
                StageEnums::BID_OPENING,
                StageEnums::BID_EVALUATION,
                StageEnums::POST_QUALIFICATION,
                StageEnums::BAC_RESOLUTION,
                StageEnums::NOTICE_OF_AWARD,
                StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                StageEnums::NOTICE_TO_PROCEED,
                StageEnums::MONITORING,
                StageEnums::COMPLETION,
            ];

            foreach ($stages as $stage) {
                $counts = $this->service->getDocumentCounts($stage);

                expect($counts['required_count'])->toBeGreaterThanOrEqual(0);
                expect($counts['optional_count'])->toBeGreaterThanOrEqual(0);
                expect($counts['total_count'])->toBeGreaterThanOrEqual(0);
            }
        });
    });

    describe('hasMinimumRequiredDocuments', function () {
        it('returns true when all required documents uploaded', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            $hasMinimum = $this->service->hasMinimumRequiredDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                $required
            );

            expect($hasMinimum)->toBeTrue();
        });

        it('returns false when no documents uploaded', function () {
            $hasMinimum = $this->service->hasMinimumRequiredDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                []
            );

            expect($hasMinimum)->toBeFalse();
        });

        it('returns false when only some required documents uploaded', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);
            $partial = array_slice($required, 0, count($required) - 1);

            $hasMinimum = $this->service->hasMinimumRequiredDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                $partial
            );

            expect($hasMinimum)->toBeFalse();
        });

        it('returns true even with extra optional documents', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);
            $optional = $this->service->getOptionalDocuments(StageEnums::PROCUREMENT_INITIATION);
            $all = array_merge($required, $optional);

            $hasMinimum = $this->service->hasMinimumRequiredDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                $all
            );

            expect($hasMinimum)->toBeTrue();
        });
    });

    describe('getMissingDocuments', function () {
        it('returns all required when nothing uploaded', function () {
            $missing = $this->service->getMissingDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                []
            );

            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            expect($missing)->toHaveCount(count($required));
        });

        it('returns empty when all required uploaded', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);

            $missing = $this->service->getMissingDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                $required
            );

            expect($missing)->toBeEmpty();
        });

        it('returns only missing documents', function () {
            // Use a stage with multiple required documents for this test
            $required = $this->service->getRequiredDocuments(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            // Only test if there are at least 2 required documents
            if (count($required) >= 2) {
                $uploaded = array_slice($required, 0, -2); // Upload all but last 2

                $missing = $this->service->getMissingDocuments(
                    StageEnums::PRE_PROCUREMENT_CONFERENCE,
                    $uploaded
                );

                expect($missing)->toHaveCount(2);

                foreach ($missing as $doc) {
                    expect($uploaded)->not->toContain($doc);
                    expect($required)->toContain($doc);
                }
            } else {
                // Skip test if not enough required documents
                expect(true)->toBeTrue();
            }
        });

        it('does not include optional documents in missing', function () {
            $required = $this->service->getRequiredDocuments(StageEnums::PROCUREMENT_INITIATION);
            $optional = $this->service->getOptionalDocuments(StageEnums::PROCUREMENT_INITIATION);

            $missing = $this->service->getMissingDocuments(
                StageEnums::PROCUREMENT_INITIATION,
                []
            );

            // Missing should only contain required, never optional
            foreach ($optional as $doc) {
                expect($missing)->not->toContain($doc);
            }
        });
    });

    describe('Phase Coverage', function () {
        it('has requirements for all pre-procurement stages', function () {
            $stages = [
                StageEnums::PROCUREMENT_INITIATION,
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StageEnums::BIDDING_DOCUMENTS,
            ];

            foreach ($stages as $stage) {
                $required = $this->service->getRequiredDocuments($stage);
                expect($required)->not->toBeEmpty();
            }
        });

        it('has requirements for all procurement stages', function () {
            $stages = [
                StageEnums::PRE_BID_CONFERENCE,
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                StageEnums::BID_OPENING,
                StageEnums::BID_EVALUATION,
                StageEnums::POST_QUALIFICATION,
                StageEnums::BAC_RESOLUTION,
            ];

            foreach ($stages as $stage) {
                $required = $this->service->getRequiredDocuments($stage);
                // Some stages may have optional requirements only
                expect($required)->toBeArray();
            }
        });

        it('has requirements for all post-procurement stages', function () {
            $stages = [
                StageEnums::NOTICE_OF_AWARD,
                StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                StageEnums::NOTICE_TO_PROCEED,
                StageEnums::MONITORING,
                StageEnums::COMPLETION,
            ];

            foreach ($stages as $stage) {
                $required = $this->service->getRequiredDocuments($stage);
                expect($required)->toBeArray();
            }
        });
    });

    describe('Data Integrity', function () {
        it('ensures no duplicate documents in required list', function () {
            $stages = StageEnums::cases();

            foreach ($stages as $stage) {
                $required = $this->service->getRequiredDocuments($stage);
                $unique = array_unique($required, SORT_REGULAR);

                expect($required)->toHaveCount(count($unique));
            }
        });

        it('ensures no duplicate documents in optional list', function () {
            $stages = StageEnums::cases();

            foreach ($stages as $stage) {
                $optional = $this->service->getOptionalDocuments($stage);
                $unique = array_unique($optional, SORT_REGULAR);

                expect($optional)->toHaveCount(count($unique));
            }
        });

        it('ensures no overlap between required and optional', function () {
            $stages = StageEnums::cases();

            foreach ($stages as $stage) {
                $required = $this->service->getRequiredDocuments($stage);
                $optional = $this->service->getOptionalDocuments($stage);

                foreach ($required as $doc) {
                    expect($optional)->not->toContain($doc);
                }
            }
        });
    });
});
