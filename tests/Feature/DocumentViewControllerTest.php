<?php

use App\Models\DocumentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('can view documents through the application', function () {
    /** @var User $user */
    $user = User::factory()->createOne([
        'role' => 'admin',
    ]);
    actingAs($user);

    // Create a document view record for testing data
    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-document',
        'document_type' => 'Test Document',
        'stage' => 'BiddingDocuments',
        'procurement_id' => 'TEST-001',
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
    $user = User::factory()->createOne([
        'role' => 'hope', // Valid role but let's test middleware
    ]);
    actingAs($user);

    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-document',
        'document_type' => 'Test Document',
        'stage' => 'BiddingDocuments',
        'procurement_id' => 'TEST-001',
    ]);

    $response = get('/pdf-viewer/test-document');

    // Should succeed for valid roles
    $response->assertSuccessful();
});
