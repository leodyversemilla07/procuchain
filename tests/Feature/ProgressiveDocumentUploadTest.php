<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->group('feature', 'progressive-upload');

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create([
        'blockchain_address' => 'test_address_123',
    ]);

    // Assign bac_secretariat role using Spatie Permission
    $this->user->assignRole('bac_secretariat');

    $this->actingAs($this->user);
});
describe('Progressive Document Upload - Pre-Procurement Phase', function () {
    it('uploads single document for procurement initiation stage', function () {
        $file = UploadedFile::fake()->create('purchase_request.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Purchase Request for Office Supplies',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();
    })->skip('Requires blockchain setup');

    it('uploads single document for pre-procurement conference stage', function () {
        $file = UploadedFile::fake()->create('agenda.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PRE_PROCUREMENT_AGENDA->value,
            'description' => 'Pre-Procurement Conference Agenda',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    })->skip('Requires blockchain setup');

    it('uploads single document for bidding documents stage', function () {
        $file = UploadedFile::fake()->create('itb.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::BIDDING_DOCUMENTS->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::INVITATION_TO_BID->value,
            'description' => 'Invitation to Bid',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    })->skip('Requires blockchain setup');
});

describe('Progressive Document Upload - Procurement Phase', function () {
    it('uploads single document for pre-bid conference stage', function () {
        $file = UploadedFile::fake()->create('pre_bid_minutes.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PRE_BID_CONFERENCE->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PRE_BID_CONFERENCE_MINUTES->value,
            'description' => 'Pre-Bid Conference Minutes',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    })->skip('Requires blockchain setup');

    it('uploads single document for bid opening stage', function () {
        $file = UploadedFile::fake()->create('abstract.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::BID_OPENING->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::ABSTRACT_OF_BIDS->value,
            'description' => 'Abstract of Bids',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    })->skip('Requires blockchain setup');
});

describe('Progressive Document Upload - Post-Procurement Phase', function () {
    it('uploads single document for notice of award stage', function () {
        $file = UploadedFile::fake()->create('noa.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.post-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::NOTICE_OF_AWARD->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
            'description' => 'Notice of Award',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    })->skip('Requires blockchain setup');

    it('uploads single document for notice to proceed stage', function () {
        $file = UploadedFile::fake()->create('ntp.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.post-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::NOTICE_TO_PROCEED->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::NOTICE_TO_PROCEED->value,
            'description' => 'Notice to Proceed',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    })->skip('Requires blockchain setup');
});

describe('Progressive Document Upload - Validation', function () {
    it('rejects non-PDF files', function () {
        $file = UploadedFile::fake()->create('document.docx', 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Test',
        ]);

        $response->assertSessionHasErrors('document_file');
    });

    it('rejects files larger than 10MB', function () {
        $file = UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Test',
        ]);

        $response->assertSessionHasErrors('document_file');
    });

    it('requires document_type field', function () {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file,
            'description' => 'Test',
        ]);

        $response->assertSessionHasErrors('document_type');
    });

    it('requires document_file field', function () {
        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Test',
        ]);

        $response->assertSessionHasErrors('document_file');
    });

    it('rejects invalid stage for phase', function () {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        // Trying to upload a Procurement phase document to Pre-Procurement controller
        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::BID_OPENING->value, // Procurement phase stage
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::ABSTRACT_OF_BIDS->value,
            'description' => 'Test',
        ]);

        $response->assertForbidden();
    });
});

describe('Progressive Document Upload - Authorization', function () {
    it('requires authentication', function () {
        auth()->logout();

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Test',
        ]);

        $response->assertRedirect(route('login'));
    });

    it('requires bac_secretariat role', function () {
        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole('viewer');

        $this->actingAs($unauthorizedUser);
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Test',
        ]);

        $response->assertForbidden();
    });
});

describe('Progressive Document Upload - Multiple Uploads', function () {
    it('allows uploading different documents sequentially', function () {
        $file1 = UploadedFile::fake()->create('purchase_request.pdf', 1024, 'application/pdf');
        $file2 = UploadedFile::fake()->create('ppmp.pdf', 1024, 'application/pdf');

        // First upload
        $response1 = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file1,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
            'description' => 'Purchase Request',
        ]);

        $response1->assertRedirect();
        $response1->assertSessionHas('success');

        // Second upload
        $response2 = $this->post(route('procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_file' => $file2,
            'document_type' => DocumentTypeEnums::PPMP->value,
            'description' => 'PPMP',
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('success');
    })->skip('Requires blockchain setup');
});
