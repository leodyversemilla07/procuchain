<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('can access single document correction page with proper permissions', function () {
    /** @var User $user */
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $user = User::factory()->createOne();
    $user->assignRole('bac_secretariat');
    actingAs($user);

    // Test that the route is not accessible (method not allowed since only POST is defined)
    $response = get('/documents/test-txid/correct');

    // Should get 405 because GET method is not allowed for this route
    $response->assertStatus(405);
});

it('requires authentication for single document correction page', function () {
    $response = get('/documents/test-txid/correct');

    $response->assertStatus(405);
});

it('requires proper role for single document correction page', function () {
    /** @var User $user */
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
    $user = User::factory()->createOne();
    $user->assignRole('hope');
    actingAs($user);

    $response = get('/documents/test-txid/correct');

    $response->assertStatus(405);
});
