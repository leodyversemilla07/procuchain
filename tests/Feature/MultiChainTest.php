<?php

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('multichain connection works correctly', function () {
    // Create an instance of the MultichainService
    $multichainService = new \App\Services\MultichainService;

    // Test the connection
    $result = $multichainService->testConnection();

    // Assert the connection was successful
    expect($result['success'])->toBeTrue();

    // Assert that we received chain information
    expect($result)->toHaveKeys(['chain', 'version', 'protocol', 'node_address']);
});
