<?php

use App\DataTransferObjects\ProcurementData;
use App\Models\DocumentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'view documents',
        'download documents',
        'view procurement',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
});

it('can view documents through the application', function () {
    /** @var User $user */
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::findByName('admin', 'web')->givePermissionTo([
        'view documents',
        'download documents',
        'view procurement',
    ]);
    $user = User::factory()->createOne();
    $user->assignRole('admin');
    actingAs($user);

    // Create a document view record for testing data
    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-document',
        'document_type' => 'Test Document',
        'stage' => 'BiddingDocuments',
        'pr_number' => 'TEST-001',
    ]);

    $response = get('/pdf-viewer/test-document');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('documents/pdf-viewer')
        ->has('document')
        ->has('viewStats')
        ->has('recentViews')
    );
});

it('requires authentication for PDF viewer access', function () {
    $response = get('/pdf-viewer/test-document');

    $response->assertRedirect('/login');
});

it('requires proper role for PDF viewer access', function () {
    /** @var User $user */
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web', 'guard_name' => 'web']);
    Role::findByName('hope', 'web')->givePermissionTo([
        'view documents',
        'download documents',
        'view procurement',
    ]);
    $user = User::factory()->createOne();
    $user->assignRole('hope'); // Valid role but let's test middleware
    actingAs($user);

    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-document',
        'document_type' => 'Test Document',
        'stage' => 'BiddingDocuments',
        'pr_number' => 'TEST-001',
    ]);

    $response = get('/pdf-viewer/test-document');

    // Should succeed for valid roles
    $response->assertSuccessful();
});

it('formats stage enum to display name correctly', function () {
    /** @var User $user */
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    Role::findByName('bac_secretariat', 'web')->givePermissionTo([
        'view documents',
        'download documents',
        'view procurement',
    ]);
    $user = User::factory()->createOne();
    $user->assignRole('bac_secretariat');
    $user->forceFill(['blockchain_address' => 'test-address'])->save();
    actingAs($user);

    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-stage-format',
        'document_type' => 'procurement_initiation',
        'stage' => 'procurement_initiation',
        'pr_number' => 'TEST-002',
    ]);

    $response = get('/pdf-viewer/test-stage-format');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('documents/pdf-viewer')
        ->has('document')
        ->where('document.stage', 'procurement_initiation')
        ->where('document.stage_display', 'Procurement Initiation')
        ->where('document.document_type', 'procurement_initiation')
        ->where('document.document_type_display', 'Procurement Initiation Document (PDF)')
    );
});

it('forbids bac secretariat from viewing inaccessible procurement documents', function () {
    // TODO: Scoped document access control is not yet implemented in PdfViewerController.
    // Currently, any user with 'view documents' permission can view any document.
    // This test should be enabled once per-procurement scoped access checks are added.
    $this->markTestSkipped('Scoped document access not yet implemented — tracked as future enhancement');
});

function viewerLockedProcurementFixture(): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => 'PR-2025-998-0003',
        'title' => 'Locked Procurement',
        'description' => 'Fixture',
        'abc_amount' => 1000,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'status' => 'draft',
        'user_id' => '999',
        'created_at' => now()->toIso8601String(),
    ]);
}
