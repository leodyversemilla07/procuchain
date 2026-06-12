<?php

use App\Services\NormalizedTableSyncService;

it('syncs normalized read models with the legacy blockchain sync command name', function () {
    $syncService = Mockery::mock(NormalizedTableSyncService::class);
    $syncService->shouldReceive('syncAll')
        ->once()
        ->andReturn([
            'procurements' => 1,
            'stages' => 2,
            'documents' => 3,
            'events' => 4,
            'corrections' => 0,
            'archives' => 0,
            'metadata_corrections' => 0,
            'files' => 0,
        ]);

    app()->instance(NormalizedTableSyncService::class, $syncService);

    $this->artisan('blockchain:sync')
        ->expectsOutput('Starting blockchain sync...')
        ->assertSuccessful();
});

it('fails fast for unsupported single-stream legacy sync', function () {
    $this->artisan('blockchain:sync --stream=procurement.metadata')
        ->expectsOutput('Starting blockchain sync...')
        ->expectsOutput('Single-stream sync is no longer supported by blockchain:sync (procurement.metadata). Use blockchain:sync-normalized for normalized read models.')
        ->assertFailed();
});
