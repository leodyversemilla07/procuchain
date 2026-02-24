<?php

namespace Tests\Feature\Admin;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
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

describe('ProcurementWorkflowConfigController', function () {
    describe('index', function () {
        it('admin can access workflow configs page', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/workflow-config');

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($assert) => $assert
                    ->component('admin/workflow-configs')
                    ->has('competitiveModes')
                    ->has('alternativeModes')
            );
        });

        it('non-admin cannot access workflow configs page', function () {
            $response = $this->actingAs($this->nonAdmin)
                ->get('/admin/workflow-config');

            $response->assertStatus(403);
        });
    });

    describe('edit', function () {
        it('admin can access workflow config edit page', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/workflow-config/competitive_bidding/edit');

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($assert) => $assert
                    ->component('admin/workflow-config-edit')
                    ->has('mode')
                    ->has('currentStages')
                    ->has('allStages')
            );
        });

        it('returns 404 for invalid mode', function () {
            $response = $this->actingAs($this->admin)
                ->get('/admin/workflow-config/invalid_mode/edit');

            $response->assertStatus(404);
        });
    });

    describe('update', function () {
        it('admin can update workflow config', function () {
            $stages = [
                StageEnums::PROCUREMENT_INITIATION->value,
                StageEnums::BIDDING_DOCUMENTS->value,
                StageEnums::NOTICE_OF_AWARD->value,
                StageEnums::COMPLETED->value,
            ];

            $response = $this->actingAs($this->admin)
                ->put('/admin/workflow-config/small_value_procurement', [
                    'stages' => $stages,
                    'optional_stages' => [],
                ]);

            $response->assertRedirect('/admin/workflow-config');
            $response->assertSessionHas('success');

            // Verify database was updated
            $this->assertDatabaseHas('procurement_workflow_configs', [
                'procurement_mode' => 'small_value_procurement',
            ]);

            $config = ProcurementWorkflowConfig::forMode('small_value_procurement')->first();
            expect($config->stages)->toBe($stages);
        });

        it('rejects empty stages', function () {
            $response = $this->actingAs($this->admin)
                ->put('/admin/workflow-config/competitive_bidding', [
                    'stages' => [],
                    'optional_stages' => [],
                ]);

            $response->assertSessionHasErrors('stages');
        });

        it('rejects invalid stage values', function () {
            $response = $this->actingAs($this->admin)
                ->put('/admin/workflow-config/competitive_bidding', [
                    'stages' => ['invalid_stage'],
                    'optional_stages' => [],
                ]);

            $response->assertSessionHasErrors('stages');
        });
    });

    describe('resetToDefaults', function () {
        it('admin can reset workflow config to defaults', function () {
            // First, create a custom config
            ProcurementWorkflowConfig::create([
                'procurement_mode' => ProcurementModeEnums::SMALL_VALUE_PROCUREMENT->value,
                'display_name' => 'Test',
                'stages' => ['procurement_initiation'],
                'optional_stages' => [],
                'is_active' => true,
            ]);

            $response = $this->actingAs($this->admin)
                ->post('/admin/workflow-config/small_value_procurement/reset');

            $response->assertRedirect('/admin/workflow-config');
            $response->assertSessionHas('success');

            // Verify it was reset to defaults
            $config = ProcurementWorkflowConfig::forMode('small_value_procurement')->first();
            $defaultStages = StageEnums::getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);

            expect(count($config->stages))->toBe(count($defaultStages));
        });
    });
});
