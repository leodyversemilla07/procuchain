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

    $this->user->assignRole('bac_secretariat');
    $this->actingAs($this->user);
});

describe('Progressive Document Upload - Authorization', function () {
    it('requires authentication', function () {
        auth()->logout();

        $File = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001-0001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_File' => $File,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
        ]);

        $response->assertRedirect('/login');
    });

    it('requires bac_secretariat role', function () {
        $user = User::factory()->create();
        $user->assignRole('hope');
        $this->actingAs($user);

        $File = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2025-001-0001',
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]), [
            'document_File' => $File,
            'document_type' => DocumentTypeEnums::PURCHASE_REQUEST->value,
        ]);

        $response->assertStatus(403);
    });
});
