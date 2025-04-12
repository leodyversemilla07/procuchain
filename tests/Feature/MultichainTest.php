<?php

namespace Tests\Feature;

use App\Services\MultichainService;

test('can connect to multichain', function () {
    $multichain = new MultichainService;
    $info = $multichain->getInfo();

    expect($info)->not->toBeNull()
        ->and($info)->toBeArray()
        ->and($info)->toHaveKey('chainname')
        ->and($info['chainname'])->toBe('procuchain');
});
