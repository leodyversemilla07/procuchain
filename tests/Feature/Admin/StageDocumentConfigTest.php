<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\StageDocumentConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create an admin user for testing
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create([
        'email' => 'admin@test.com',
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->admin->assignRole('admin');

    // Create non-admin user
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $this->nonAdmin = User::factory()->create();
    $this->nonAdmin->assignRole('bac_secretariat');
});

describe('StageDocumentConfigController', function () {
    describe('index', function () {
        it('admin can access stage document configs page', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/stage-documents');

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($assert) => $assert
                    ->component('admin/stage-document-configs')
                    ->has('selectedMode')
                    ->has('modes')
            );
        });

        it('admin can filter by mode', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/stage-documents?mode=small_value_procurement');

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($assert) => $assert
                    ->where('selectedMode', 'small_value_procurement')
            );
        });

        it('non-admin cannot access stage document configs page', function () {
            $response = $this->actingAs($this->nonAdmin)
                ->get('/admin/stage-documents');

            $response->assertStatus(403);
        });
    });

    describe('edit', function () {
        it('admin can access stage document config edit page', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/stage-documents/competitive_bidding/procurement_initiation/edit');

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($assert) => $assert
                    ->component('admin/stage-document-config-edit')
                    ->has('mode')
                    ->has('stage')
                    ->has('currentRequiredDocuments')
                    ->has('currentOptionalDocuments')
                    ->has('allDocuments')
            );
        });

        it('returns 404 for invalid mode or stage', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/stage-documents/invalid_mode/procurement_initiation/edit');

            $response->assertStatus(404);
        });
    });

    describe('update', function () {
        it('admin can update stage document config', function () {
            $requiredDocs = [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::PPMP->value,
            ];

            $optionalDocs = [
                DocumentTypeEnums::TECHNICAL_SPECIFICATIONS->value,
            ];

            $response = $this->actingAs($this->admin)
                ->put('/admin/stage-documents/small_value_procurement/procurement_initiation', [
                    'required_documents' => $requiredDocs,
                    'optional_documents' => $optionalDocs,
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            // Verify database was updated
            $this->assertDatabaseHas('stage_document_configs', [
                'stage' => 'procurement_initiation',
                'procurement_mode' => 'small_value_procurement',
            ]);

            $config = StageDocumentConfig::forStage('procurement_initiation')
                ->forMode('small_value_procurement')
                ->first();

            expect($config->required_documents)->toBe($requiredDocs);
            expect($config->optional_documents)->toBe($optionalDocs);
        });

        it('rejects documents in both required and optional', function () {
            $duplicateDocs = [DocumentTypeEnums::PURCHASE_REQUEST->value];

            $response = $this->actingAs($this->admin)
                ->put('/admin/stage-documents/competitive_bidding/procurement_initiation', [
                    'required_documents' => $duplicateDocs,
                    'optional_documents' => $duplicateDocs,
                ]);

            $response->assertSessionHasErrors('optional_documents');
        });

        it('rejects invalid document type values', function () {
            $response = $this->actingAs($this->admin)
                ->put('/admin/stage-documents/competitive_bidding/procurement_initiation', [
                    'required_documents' => ['invalid_document_type'],
                    'optional_documents' => [],
                ]);

            $response->assertSessionHasErrors('required_documents');
        });
    });

    describe('resetToDefaults', function () {
        it('admin can reset stage document config to defaults', function () {
            // First, create a custom config
            StageDocumentConfig::create([
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'procurement_mode' => ProcurementModeEnums::SMALL_VALUE_PROCUREMENT->value,
                'stage_display_name' => 'Test',
                'required_documents' => ['purchase_request'],
                'optional_documents' => [],
                'is_active' => true,
            ]);

            $response = $this->actingAs($this->admin)
                ->post('/admin/stage-documents/small_value_procurement/procurement_initiation/reset');

            $response->assertRedirect();
            $response->assertSessionHas('success');
        });
    });
});
