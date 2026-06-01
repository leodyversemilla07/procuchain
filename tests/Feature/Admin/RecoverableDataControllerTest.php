<?php

use App\Contracts\BlockchainStorageInterface;
use App\Jobs\NodeOperationJob;
use App\Models\User;
use App\Services\BlockchainStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function mockBlockchainStorageForNodes(array $nodes): void
{
    $mock = Mockery::mock(BlockchainStorageService::class);
    $mock->shouldReceive('getDeletedFiles')->zeroOrMoreTimes()->andReturn([]);
    $mock->shouldReceive('getAvailableNodes')->zeroOrMoreTimes()->andReturn($nodes);
    app()->instance(BlockchainStorageService::class, $mock);
    app()->instance(BlockchainStorageInterface::class, $mock);
}

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
        ->postJson('/admin/recoverable-data/purge-all-from-node', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('node_id');
});

it('purge-all-from-node validates reason is max 500 chars', function () {
    $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
            'reason' => str_repeat('x', 501),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('purge-all-from-node rejects purge when node is already purged', function () {
    mockBlockchainStorageForNodes([
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE', 'is_purged' => true],
    ]);

    $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'rejected');
});

it('purge-all-from-node dispatches job and returns 202', function () {
    Queue::fake();

    mockBlockchainStorageForNodes([
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE'],
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure(['job_id', 'status', 'message']);

    Queue::assertPushed(NodeOperationJob::class, function ($job) {
        return $job->operation === 'purge' && $job->nodeId === 'hope';
    });
});

it('purge-all-from-node uses custom reason when provided', function () {
    Queue::fake();

    mockBlockchainStorageForNodes([
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE'],
    ]);

    $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
            'reason' => 'Hardware failure on hope node',
        ])
        ->assertStatus(202);

    Queue::assertPushed(NodeOperationJob::class, function ($job) {
        return $job->reason === 'Hardware failure on hope node';
    });
});

it('purge-all-from-node rejects when node operation is already in progress', function () {
    Cache::put('node_operation_lock:hope', true, now()->addMinutes(20));

    $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/purge-all-from-node', [
            'node_id' => 'hope',
        ])
        ->assertStatus(409)
        ->assertJsonPath('status', 'rejected');
});

// ─── Resync Node ──────────────────────────────────────────────────────────────

it('resync-node route requires authentication', function () {
    $this->post('/admin/recoverable-data/resync-node', [
        'node_id' => 'hope',
    ])->assertRedirect('/login');
});

it('resync-node validates node_id is required', function () {
    $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/resync-node', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('node_id');
});

it('resync-node rejects resync when node is not purged', function () {
    mockBlockchainStorageForNodes([
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE'],
    ]);

    $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/resync-node', [
            'node_id' => 'hope',
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'rejected');
});

it('resync-node dispatches job for purged node and returns 202', function () {
    Queue::fake();

    mockBlockchainStorageForNodes([
        ['id' => 'hope', 'name' => 'HOPE', 'role' => 'HOPE', 'is_purged' => true],
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/admin/recoverable-data/resync-node', [
            'node_id' => 'hope',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure(['job_id', 'status', 'message']);

    Queue::assertPushed(NodeOperationJob::class, function ($job) {
        return $job->operation === 'resync' && $job->nodeId === 'hope';
    });
});

// ─── Inertia Props ────────────────────────────────────────────────────────────

it('index returns nodes and deleted files but not prNumbers', function () {
    mockBlockchainStorageForNodes([
        ['id' => 'admin', 'name' => 'Admin', 'role' => 'admin'],
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/recoverable-data');

    $response->assertStatus(200);
    $props = $response->viewData('page')['props'];
    expect($props)->toHaveKey('nodes');
    expect($props)->toHaveKey('deletedFiles');
    expect($props)->not->toHaveKey('prNumbers');
});
