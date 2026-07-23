<?php

declare(strict_types=1);

use App\Services\BlockchainRpcClient;
use App\Services\NodePurgeDetector;

beforeEach(function () {
    $this->blockchainRpcClientMock = Mockery::mock(BlockchainRpcClient::class);
    $this->blockchainRpcClientMock->shouldReceive('success')->andReturn(true);
    $this->detector = new NodePurgeDetector($this->blockchainRpcClientMock);
});

afterEach(function () {
    Mockery::close();
});

it('does not mark node as purged when purge and resync share the same blocktime', function () {
    $sameBlocktime = 1779753638;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_full_purge')
        ->andReturn([['blocktime' => $sameBlocktime, 'data' => ['json' => ['reason' => 'test-purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_resync')
        ->andReturn([['blocktime' => $sameBlocktime]]);

    $result = $this->detector->checkPurgeStateFromPrimary('hope');

    expect($result['is_purged'])->toBeFalse('Same-block purge+resync should mean node has recovered');
    expect($result['was_explicitly_purged'])->toBeFalse();
});

it('marks node as purged when purge blocktime is strictly greater than resync', function () {
    $purgeBlocktime = 1779754000;
    $resyncBlocktime = 1779753638;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'new-purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_resync')
        ->andReturn([['blocktime' => $resyncBlocktime]]);

    $result = $this->detector->checkPurgeStateFromPrimary('hope');

    expect($result['is_purged'])->toBeTrue('Purge after resync should mean node is purged');
    expect($result['was_explicitly_purged'])->toBeTrue();
    expect($result['purge_reason'])->toBe('new-purge');
});

it('does not mark node as purged when resync blocktime is strictly greater than purge', function () {
    $purgeBlocktime = 1779753638;
    $resyncBlocktime = 1779754000;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'old-purge']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_resync')
        ->andReturn([['blocktime' => $resyncBlocktime]]);

    $result = $this->detector->checkPurgeStateFromPrimary('hope');

    expect($result['is_purged'])->toBeFalse('Resync after purge should mean node has recovered');
});

it('marks node as purged when no resync event exists', function () {
    $purgeBlocktime = 1779753638;

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_full_purge')
        ->andReturn([['blocktime' => $purgeBlocktime, 'data' => ['json' => ['reason' => 'just purged']]]]);

    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_resync')
        ->andReturn([]);

    $result = $this->detector->checkPurgeStateFromPrimary('hope');

    expect($result['is_purged'])->toBeTrue('No resync event means node is still purged');
});

it('does not mark node as purged when no purge event exists', function () {
    $this->blockchainRpcClientMock->shouldReceive('liststreamkeyitems')
        ->withArgs(fn (string $stream, string $key) => $key === 'node_hope_full_purge')
        ->andReturn([]);

    $result = $this->detector->checkPurgeStateFromPrimary('hope');

    expect($result['is_purged'])->toBeFalse('No purge event means node is not purged');
});
