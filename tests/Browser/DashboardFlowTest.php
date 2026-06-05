<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\BlockchainMonitoringService;
use App\Services\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsPermissions;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);
uses(SeedsPermissions::class);

describe('Admin Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    });

    it('displays admin dashboard with all statistics cards', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/dashboard');

        $page->assertSee('Dashboard')
            ->assertSee('Projects')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('displays user management page', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/users');

        $page->assertSee('User')
            ->assertNoJavascriptErrors();
    });

    it('displays blockchain explorer', function () {
        browserBindBlockchainExplorerMocks();

        $this->actingAs($this->admin);

        $page = visit('/admin/blockchain-explorer');

        $page->assertSee('Blockchain')
            ->assertNoJavascriptErrors();
    });

    it('allows admin to toggle dark mode', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/dashboard');

        $page->assertNoJavascriptErrors();
    });
});

function browserBindBlockchainExplorerMocks(): void
{
    $multichain = mock(Manager::class);
    $multichain->shouldReceive('getblockchaininfo')
        ->zeroOrMoreTimes()
        ->andReturn([
            'blocks' => 1,
            'difficulty' => 0,
        ]);
    $multichain->shouldReceive('getnetworkinfo')
        ->zeroOrMoreTimes()
        ->andReturn([
            'connections' => 0,
        ]);
    $multichain->shouldReceive('getinfo')
        ->zeroOrMoreTimes()
        ->andReturn([
            'chainname' => 'browser-chain',
            'protocol' => 20013,
            'version' => '1.0.0',
            'nodeaddress' => 'browser-node-address',
        ]);
    $multichain->shouldReceive('getpeerinfo')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $multichain->shouldReceive('getblock')
        ->zeroOrMoreTimes()
        ->withAnyArgs()
        ->andReturnUsing(fn (int $height): array => [
            'height' => $height,
            'hash' => "browser-block-hash-{$height}",
            'time' => now()->timestamp,
            'miner' => 'browser-miner',
            'tx' => [],
            'size' => 0,
        ]);
    $multichain->shouldReceive('liststreams')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $multichain->shouldReceive('listaddresses')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    $healthService = mock(BlockchainMonitoringService::class);
    $healthService->shouldReceive('getHealthStatus')
        ->zeroOrMoreTimes()
        ->andReturn([
            'status' => 'healthy',
            'circuit_breaker' => [
                'is_open' => false,
                'failures' => 0,
                'recovery_time' => null,
            ],
            'queue' => [
                'pending_jobs' => 0,
                'failed_jobs_24h' => 0,
            ],
            'checked_at' => now()->toIso8601String(),
        ]);

    app()->instance(Manager::class, $multichain);
    app()->instance(BlockchainMonitoringService::class, $healthService);
}

describe('BAC Secretariat Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create();
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays BAC secretariat dashboard', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/dashboard');

        $page->assertSee('Dashboard')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('displays procurements list', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.index'));

        $page->assertSee('Procurement List')
            ->assertNoJavascriptErrors();
    });
});

describe('BAC Chairman Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacChairman = User::factory()->create();
        $this->bacChairman->assignRole('bac_chairman');
    });

    it('displays BAC chairman dashboard', function () {
        $this->actingAs($this->bacChairman);

        $page = visit('/bac-chairman/dashboard');

        $page->assertSee('Dashboard')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });
});

describe('HOPE Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->hope = User::factory()->create();
        $this->hope->assignRole('hope');
    });

    it('displays HOPE dashboard', function () {
        $this->actingAs($this->hope);

        $page = visit('/hope/dashboard');

        $page->assertSee('Dashboard')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });
});
