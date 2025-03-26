<?php

use App\Services\MultichainService;
use Illuminate\Support\Facades\Config;

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('multichain connection works correctly', function () {
    // If we're in a CI environment or explicitly want to skip blockchain tests
    if (env('SKIP_BLOCKCHAIN_TESTS', false)) {
        $this->markTestSkipped('Skipping blockchain tests in CI environment');
    }
    
    // Ensure configuration values are set
    if (empty(config('multichain.rpc.password'))) {
        Config::set('multichain.rpc.password', 'defaultpassword'); // Use a default for testing
    }

    // Create an instance of the MultichainService
    $multichainService = new MultichainService();

    // Test the connection
    $result = $multichainService->testConnection();

    // Output helpful debugging info if the test fails
    if (!isset($result['success']) || $result['success'] !== true) {
        dump('MultiChain connection failed:', $result);
    }

    // Assert the connection was successful
    expect($result['success'])->toBeTrue();

    // Assert that we received chain information
    expect($result)->toHaveKeys(['chain', 'version', 'protocol', 'node_address']);
});
