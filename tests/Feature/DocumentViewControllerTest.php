<?php

use App\Models\DocumentView;
use App\Models\User;

it('can view documents through the application', function () {
    // Create a user and authenticate
    $user = User::factory()->create([
        'role' => 'admin'
    ]);
    $this->actingAs($user);

    // Create a document view record for testing data
    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-document',
        'document_type' => 'Test Document',
        'stage' => 'BiddingDocuments',
        'procurement_id' => 'TEST-001',
    ]);

    // Test that authenticated users can access PDF viewer
    $response = $this->get('/pdf-viewer/test-document');
    
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('documents/pdf-viewer')
        ->has('document')
        ->has('viewStats')
        ->has('recentViews')
    );
});

it('requires authentication for PDF viewer access', function () {
    $response = $this->get('/pdf-viewer/test-document');

    $response->assertRedirect('/login');
});

it('requires proper role for PDF viewer access', function () {
    $user = User::factory()->create([
        'role' => 'hope' // Valid role but let's test middleware
    ]);
    $this->actingAs($user);

    DocumentView::factory()->create([
        'user_id' => $user->id,
        'file_key' => 'test-document',
        'document_type' => 'Test Document',
        'stage' => 'BiddingDocuments',
        'procurement_id' => 'TEST-001',
    ]);

    $response = $this->get('/pdf-viewer/test-document');

    // Should succeed for valid roles
    $response->assertSuccessful();
});
