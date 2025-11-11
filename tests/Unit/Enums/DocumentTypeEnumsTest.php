<?php

use App\Enums\DocumentTypeEnums;

it('can get display names for all document types', function () {
    expect(DocumentTypeEnums::BAC_RESOLUTION->getDisplayName())->toBe('BAC Resolution');
    expect(DocumentTypeEnums::NOTICE_OF_AWARD->getDisplayName())->toBe('Notice of Award');
    expect(DocumentTypeEnums::PERFORMANCE_BOND->getDisplayName())->toBe('Performance Bond');
    expect(DocumentTypeEnums::CONTRACT->getDisplayName())->toBe('Contract');
    expect(DocumentTypeEnums::PURCHASE_ORDER->getDisplayName())->toBe('Purchase Order');
    expect(DocumentTypeEnums::NOTICE_TO_PROCEED->getDisplayName())->toBe('Notice to Proceed');
    expect(DocumentTypeEnums::COMPLIANCE_REPORT->getDisplayName())->toBe('Compliance Report');
    expect(DocumentTypeEnums::CERTIFICATE_OF_COMPLETION->getDisplayName())->toBe('Certificate of Completion');
});

it('can match document types from strings using fromString method', function () {
    expect(DocumentTypeEnums::fromString('bac_resolution'))->toBe(DocumentTypeEnums::BAC_RESOLUTION);
    expect(DocumentTypeEnums::fromString('BAC Resolution'))->toBe(DocumentTypeEnums::BAC_RESOLUTION);
    expect(DocumentTypeEnums::fromString('Notice of Award'))->toBe(DocumentTypeEnums::NOTICE_OF_AWARD);
    expect(DocumentTypeEnums::fromString('notice_of_award'))->toBe(DocumentTypeEnums::NOTICE_OF_AWARD);
});

it('can match common document type variations', function () {
    expect(DocumentTypeEnums::fromString('Pre-Procurement Minutes'))->toBe(DocumentTypeEnums::PRE_PROCUREMENT_MINUTES);
    expect(DocumentTypeEnums::fromString('Pre-Bid Minutes'))->toBe(DocumentTypeEnums::PRE_BID_MINUTES);
    expect(DocumentTypeEnums::fromString('Evaluation Summary'))->toBe(DocumentTypeEnums::EVALUATION_SUMMARY);
    expect(DocumentTypeEnums::fromString('Abstract'))->toBe(DocumentTypeEnums::ABSTRACT);
});

it('returns null for unknown document types', function () {
    expect(DocumentTypeEnums::fromString('Unknown Document Type'))->toBeNull();
    expect(DocumentTypeEnums::fromString('invalid_type'))->toBeNull();
});

it('handles case insensitive matching', function () {
    expect(DocumentTypeEnums::fromString('NOTICE OF AWARD'))->toBe(DocumentTypeEnums::NOTICE_OF_AWARD);
    expect(DocumentTypeEnums::fromString('notice of award'))->toBe(DocumentTypeEnums::NOTICE_OF_AWARD);
    expect(DocumentTypeEnums::fromString('Notice Of Award'))->toBe(DocumentTypeEnums::NOTICE_OF_AWARD);
});

it('handles different separator variations', function () {
    expect(DocumentTypeEnums::fromString('bac_resolution'))->toBe(DocumentTypeEnums::BAC_RESOLUTION);
    expect(DocumentTypeEnums::fromString('bac-resolution'))->toBe(DocumentTypeEnums::BAC_RESOLUTION);
    expect(DocumentTypeEnums::fromString('bac resolution'))->toBe(DocumentTypeEnums::BAC_RESOLUTION);
});

it('maps stage names to document types correctly', function () {
    // When document_type is the same as stage name (procurement_initiation),
    // it should map to the correct document type enum
    expect(DocumentTypeEnums::fromString('procurement_initiation'))->toBe(DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT);
    expect(DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT->getDisplayName())->toBe('Procurement Initiation Document');
});
