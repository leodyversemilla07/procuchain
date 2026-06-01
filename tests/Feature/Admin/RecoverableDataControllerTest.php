<?php

use App\Models\User;
use App\Services\BlockchainStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

// ─── Route Access ─────────────────────────────────────────────────────────────

it('admin can view the recoverable data page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/recoverable-data')
        ->assertStatus(200);
});

it('non-admin cannot access recoverable data page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']));

    $this->actingAs($user)
        ->get('/admin/recoverable-data')
        ->assertStatus(403);
});

// ─── Purge All From Node ──────────────────────────────────────────────────────

it('purge-all-from-node route requires authentication', function () {
    $this->post('/admin/recoverable-data/purge-all-from-node', [
        'node_id' => 'hope',
    ])->assertRedirect('/login');
});

it('purge-all-from-node route requires admin role', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']));

    $this->actingAs($user)
        ->post('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
        ])
        ->assertStatus(403);
});

it('purge-all-from-node validates node_id is required', function () {
    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/purge-all-from-node', [])
        ->assertSessionHasErrors('node_id');
});

it('purge-all-from-node validates reason is max 500 chars', function () {
    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
            'reason' => str_repeat('x', 501),
        ])
        ->assertSessionHasErrors('reason');
});

it('purge-all-from-node returns error for invalid node', function () {
    // Mock the service to return failure for invalid node
    $mock = Mockery::mock(BlockchainStorageService::class)->makePartial();
    $mock->shouldReceive('purgeAllFromNode')
        ->with('nonexistent-node', 'Demo: full node purge')
        ->once()
        ->andReturn([
            'success' => false,
            'message' => "Node 'nonexistent-node' not found in registry",
            'items_purged' => 0,
        ]);
    $mock->shouldReceive('getDeletedFiles')->andReturn([]);
    $mock->shouldReceive('getAvailableNodes')->andReturn([]);
    app()->instance(BlockchainStorageService::class, $mock);

    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'nonexistent-node',
        ])
        ->assertSessionHas('error');
});

it('purge-all-from-node succeeds and redirects back with success', function () {
    $mock = Mockery::mock(BlockchainStorageService::class)->makePartial();
    $mock->shouldReceive('purgeAllFromNode')
        ->with('hope', 'Demo: full node purge')
        ->once()
        ->andReturn([
            'success' => true,
            'message' => 'Purged all data (15 items across 4 streams) from Hope (hope). Data survives on remaining nodes — resync to restore.',
            'items_purged' => 15,
        ]);
    $mock->shouldReceive('getDeletedFiles')->andReturn([]);
    $mock->shouldReceive('getAvailableNodes')->andReturn([]);
    app()->instance(BlockchainStorageService::class, $mock);

    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
        ])
        ->assertSessionHas('success');
});

it('purge-all-from-node uses custom reason when provided', function () {
    $mock = Mockery::mock(BlockchainStorageService::class)->makePartial();
    $mock->shouldReceive('purgeAllFromNode')
        ->with('hope', 'Hardware failure on hope node')
        ->once()
        ->andReturn([
            'success' => true,
            'message' => 'Purged all data from Hope.',
            'items_purged' => 5,
        ]);
    $mock->shouldReceive('getDeletedFiles')->andReturn([]);
    $mock->shouldReceive('getAvailableNodes')->andReturn([]);
    app()->instance(BlockchainStorageService::class, $mock);

    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
            'reason' => 'Hardware failure on hope node',
        ])
        ->assertSessionHas('success');
});

// ─── Resync Node ──────────────────────────────────────────────────────────────

it('resync-node route requires authentication', function () {
    $this->post('/admin/recoverable-data/resync-node', [
        'node_id' => 'hope',
    ])->assertRedirect('/login');
});

it('resync-node validates node_id is required', function () {
    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/resync-node', [])
        ->assertSessionHasErrors('node_id');
});

it('resync-node succeeds and redirects back with success', function () {
    $mock = Mockery::mock(BlockchainStorageService::class)->makePartial();
    $mock->shouldReceive('resyncNode')
        ->with('hope')
        ->once()
        ->andReturn([
            'success' => true,
            'message' => 'Node hope resynced successfully. All stream data restored from peers.',
        ]);
    $mock->shouldReceive('getDeletedFiles')->andReturn([]);
    $mock->shouldReceive('getAvailableNodes')->andReturn([]);
    app()->instance(BlockchainStorageService::class, $mock);

    $this->actingAs($this->admin)
        ->post('/admin/recoverable-data/resync-node', [
            'node_id' => 'hope',
        ])
        ->assertSessionHas('success');
});

// ─── Inertia Props ────────────────────────────────────────────────────────────

it('index returns nodes and deleted files but not prNumbers', function () {
    $mock = Mockery::mock(BlockchainStorageService::class)->makePartial();
    $mock->shouldReceive('getDeletedFiles')->once()->andReturn([]);
    $mock->shouldReceive('getAvailableNodes')->once()->andReturn([
        ['id' => 'admin', 'name' => 'Admin', 'role' => 'admin'],
    ]);
    app()->instance(BlockchainStorageService::class, $mock);

    $response = $this->actingAs($this->admin)
        ->get('/admin/recoverable-data');

    $response->assertStatus(200);
    // The page should receive nodes and deletedFiles, but NOT prNumbers anymore
    $props = $response->viewData('page')['props'];
    expect($props)->toHaveKey('nodes');
    expect($props)->toHaveKey('deletedFiles');
    expect($props)->not->toHaveKey('prNumbers');
});
