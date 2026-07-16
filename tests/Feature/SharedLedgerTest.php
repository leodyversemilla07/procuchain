<?php

use App\Models\User;
use App\Services\BlockchainRpcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
});

// ─── Route exists and returns the page ───────────────────────────────────────

it('returns the shared ledger page for authenticated users', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Mock the BlockchainRpcClient to avoid actual blockchain calls
    $blockchainRpcClientMock = Mockery::mock(BlockchainRpcClient::class);
    $blockchainRpcClientMock->shouldReceive('liststreamitems')
        ->andReturn([]);
    $this->app->instance(BlockchainRpcClient::class, $blockchainRpcClientMock);

    // Use node=default to avoid new Client() calls that bypass the container
    $this->actingAs($user)
        ->get('/admin/shared-ledger?node=default')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('shared-ledger')
            ->has('entries')
            ->has('pagination')
            ->has('available_streams')
            ->has('filters')
        );
});

it('is not accessible without admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');

    $this->actingAs($user)
        ->get('/admin/shared-ledger')
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function () {
    $this->get('/admin/shared-ledger')
        ->assertRedirect('/login');
});

// ─── Filters ─────────────────────────────────────────────────────────────────

it('filters ledger entries by pr_number', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $mockData = [
        [
            'txid' => 'abc1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'keys' => ['PR-2025-001-0001'],
            'data' => [
                'json' => [
                    'pr_number' => 'PR-2025-001-0001',
                    'procurement_title' => 'Test Procurement',
                    'event_type' => 'procurement_created',
                    'details' => 'Procurement created',
                    'user_address' => '1abc',
                    'timestamp' => now()->subHour()->toIso8601String(),
                ],
            ],
        ],
        [
            'txid' => 'def4567890abcdef1234567890abcdef1234567890abcdef1234567890abc',
            'keys' => ['PR-2025-002-0001'],
            'data' => [
                'json' => [
                    'pr_number' => 'PR-2025-002-0001',
                    'procurement_title' => 'Another Procurement',
                    'event_type' => 'procurement_created',
                    'details' => 'Procurement created',
                    'user_address' => '1def',
                    'timestamp' => now()->subDay()->toIso8601String(),
                ],
            ],
        ],
    ];

    $blockchainRpcClientMock = Mockery::mock(BlockchainRpcClient::class);
    $blockchainRpcClientMock->shouldReceive('liststreamitems')
        ->with('procurement.metadata', true, 5000, 0, false)
        ->andReturn($mockData);
    $blockchainRpcClientMock->shouldReceive('liststreamitems')
        ->andReturn([]);
    $this->app->instance(BlockchainRpcClient::class, $blockchainRpcClientMock);

    $this->actingAs($user)
        ->get('/admin/shared-ledger?pr_number=PR-2025-001-0001&node=default')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('shared-ledger')
            ->has('entries', 1)
            ->where('entries.0.pr_number', 'PR-2025-001-0001')
        );
});

it('handles blockchain unavailability gracefully', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $blockchainRpcClientMock = Mockery::mock(BlockchainRpcClient::class);
    // Throw on first stream call to trigger exception handling
    $blockchainRpcClientMock->shouldReceive('liststreamitems')
        ->andThrow(new Exception('Connection refused'));
    $this->app->instance(BlockchainRpcClient::class, $blockchainRpcClientMock);

    // Use node=default to avoid new Client() calls that bypass the container
    $this->actingAs($user)
        ->get('/admin/shared-ledger?node=default')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('shared-ledger')
            ->where('pagination.total', 0)
        );
});
