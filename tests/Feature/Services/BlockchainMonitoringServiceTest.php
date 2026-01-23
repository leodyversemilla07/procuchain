<?php

use App\Services\BlockchainMonitoringService;
use App\Services\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Force array cache driver
    config(['cache.default' => 'array']);
    // Forget any resolved cache driver instance to ensure new config is used
    Cache::forgetDriver(config('cache.default'));
    
    // We can also mock the cache if needed, but array driver is better for state
    
    // Now flush should use array driver
    Cache::flush();
    Log::spy();

    $this->multichainManager = mock(Manager::class);
    $this->service = new BlockchainMonitoringService($this->multichainManager);
});

describe('BlockchainMonitoringService', function () {
    describe('isHealthy', function () {
        test('it returns true when blockchain is responsive', function () {
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn([
                    'nodeaddress' => '1ABC123XYZ',
                    'chainname' => 'procuchain',
                    'blocks' => 12345,
                ]);

            $result = $this->service->isHealthy();

            expect($result)->toBeTrue();
        });

        test('it returns false when blockchain is unresponsive', function () {
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andThrow(new Exception('Connection refused'));

            $result = $this->service->isHealthy();

            expect($result)->toBeFalse();
        });

        test('it returns false when circuit breaker is open', function () {
            // Open circuit breaker by recording failures
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            $result = $this->service->isHealthy();

            expect($result)->toBeFalse();

            Log::shouldHaveReceived('warning')
                ->with('Blockchain circuit breaker is OPEN - blocking requests');
        });

        test('it caches health check results', function () {
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once() // Should only be called once due to caching
                ->andReturn(['nodeaddress' => '1ABC123XYZ']);

            // First call - should hit blockchain
            $result1 = $this->service->isHealthy();
            expect($result1)->toBeTrue();

            // Second call - should use cache
            $result2 = $this->service->isHealthy();
            expect($result2)->toBeTrue();
        });

        test('it returns false when getInfo response is malformed', function () {
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn(['chainname' => 'procuchain']); // Missing nodeaddress

            $result = $this->service->isHealthy();

            expect($result)->toBeFalse();
        });
    });

    describe('isCircuitOpen', function () {
        test('it returns false when circuit is closed', function () {
            $result = $this->service->isCircuitOpen();

            expect($result)->toBeFalse();
        });

        test('it returns true when circuit is open', function () {
            // Trigger circuit breaker
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            $result = $this->service->isCircuitOpen();

            expect($result)->toBeTrue();
        });

        test('it closes circuit after recovery time passes', function () {
            // Open circuit with 5 failures
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            expect($this->service->isCircuitOpen())->toBeTrue();

            // Simulate recovery time passing by manipulating cache
            Cache::put('blockchain:circuit_breaker', [
                'failures' => 5,
                'opened_at' => time() - 400, // 400 seconds ago
                'recovery_time' => time() - 100, // Recovery time already passed
            ], 360);

            // Mock successful recovery test
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn(['nodeaddress' => '1ABC123XYZ']);

            $result = $this->service->isCircuitOpen();

            expect($result)->toBeFalse();

            Log::shouldHaveReceived('info')
                ->with('Circuit breaker attempting recovery - entering half-open state');
        });

        test('it stays open when half-open recovery test fails', function () {
            // Open circuit with 5 failures
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            expect($this->service->isCircuitOpen())->toBeTrue();

            // Simulate recovery time passing
            Cache::put('blockchain:circuit_breaker', [
                'failures' => 5,
                'opened_at' => time() - 400,
                'recovery_time' => time() - 100,
            ], 360);

            // Mock failed recovery test (no nodeaddress in response)
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn(['chainname' => 'procuchain']); // Missing nodeaddress

            $result = $this->service->isCircuitOpen();

            expect($result)->toBeTrue();

            Log::shouldHaveReceived('warning')
                ->with('Circuit breaker recovery test failed - staying open');
        });

        test('it stays open when half-open recovery throws exception', function () {
            // Open circuit with 5 failures
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            // Simulate recovery time passing
            Cache::put('blockchain:circuit_breaker', [
                'failures' => 5,
                'opened_at' => time() - 400,
                'recovery_time' => time() - 100,
            ], 360);

            // Mock exception during recovery test
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andThrow(new Exception('Connection refused'));

            $result = $this->service->isCircuitOpen();

            expect($result)->toBeTrue();

            Log::shouldHaveReceived('warning')
                ->with('Circuit breaker recovery failed - staying open', \Mockery::type('array'));
        });

        test('it extends recovery time on failed recovery attempt', function () {
            // Open circuit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            // Simulate recovery time passing
            $originalRecoveryTime = time() - 100;
            Cache::put('blockchain:circuit_breaker', [
                'failures' => 5,
                'opened_at' => time() - 400,
                'recovery_time' => $originalRecoveryTime,
            ], 360);

            // Mock failed recovery
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andThrow(new Exception('Connection refused'));

            $this->service->isCircuitOpen();

            // Check that recovery time was extended
            $circuitState = Cache::get('blockchain:circuit_breaker');
            expect($circuitState['recovery_time'])->toBeGreaterThan($originalRecoveryTime);

            Log::shouldHaveReceived('info')
                ->with('Circuit breaker recovery time extended', \Mockery::type('array'));
        });
    });

    describe('recordSuccess', function () {
        test('it closes circuit breaker on success', function () {
            // Open circuit first
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            expect($this->service->isCircuitOpen())->toBeTrue();

            // Record success should close circuit
            $this->service->recordSuccess();

            expect($this->service->isCircuitOpen())->toBeFalse();
        });

        test('it clears health check cache on success', function () {
            // Set a cached health check
            Cache::put('blockchain:health_check', false, 60);

            $this->service->recordSuccess();

            expect(Cache::has('blockchain:health_check'))->toBeFalse();
        });

        test('it logs circuit recovery when closing after failure', function () {
            // Open circuit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            $this->service->recordSuccess();

            Log::shouldHaveReceived('info')
                ->with('CIRCUIT BREAKER CLOSED - Blockchain recovered');
        });
    });

    describe('recordFailure', function () {
        test('it increments failure count', function () {
            $this->service->recordFailure();

            $circuitState = Cache::get('blockchain:circuit_breaker');

            expect($circuitState['failures'])->toBe(1);
        });

        test('it opens circuit after threshold failures', function () {
            // Record 5 failures - circuit should open
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            // Circuit should now be open
            expect($this->service->isCircuitOpen())->toBeTrue();
        });

        test('it sets recovery time when opening circuit', function () {
            // Trigger circuit breaker
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            $circuitState = Cache::get('blockchain:circuit_breaker');

            expect($circuitState['recovery_time'])->toBeGreaterThan(time());
            expect($circuitState['recovery_time'])->toBeLessThanOrEqual(time() + 300);
        });

        test('it logs error when circuit opens', function () {
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            Log::shouldHaveReceived('error')
                ->with('CIRCUIT BREAKER OPENED - Blockchain appears down', \Mockery::type('array'));
        });

        test('it clears health check cache on failure', function () {
            Cache::put('blockchain:health_check', true, 60);

            $this->service->recordFailure();

            expect(Cache::has('blockchain:health_check'))->toBeFalse();
        });

        test('it does not reopen circuit if already open', function () {
            // Open circuit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            $circuitState1 = Cache::get('blockchain:circuit_breaker');
            $openedAt1 = $circuitState1['opened_at'];

            // Record another failure
            $this->service->recordFailure();

            $circuitState2 = Cache::get('blockchain:circuit_breaker');
            $openedAt2 = $circuitState2['opened_at'];

            // opened_at should remain the same (not reset)
            expect($openedAt2)->toBe($openedAt1);
        });
    });

    describe('getHealthStatus', function () {
        test('it returns comprehensive health data when healthy', function () {
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->andReturn(['nodeaddress' => '1ABC123XYZ']);

            // Create test data
            DB::table('jobs')->insert([
                [
                    'queue' => 'default',
                    'payload' => '{}',
                    'attempts' => 0,
                    'created_at' => time(),
                    'available_at' => time(),
                    'reserved_at' => null,
                ],
            ]);

            DB::table('failed_jobs')->insert([
                [
                    'uuid' => 'test-uuid-1',
                    'connection' => 'database',
                    'queue' => 'default',
                    'payload' => '{}',
                    'exception' => 'Test exception',
                    'failed_at' => now()->subHours(2),
                ],
            ]);

            $status = $this->service->getHealthStatus();

            expect($status)->toBeArray();
            expect($status['status'])->toBe('healthy');
            expect($status['circuit_breaker']['is_open'])->toBeFalse();
            expect($status['circuit_breaker']['failures'])->toBe(0);
            expect($status['queue']['pending_jobs'])->toBe(1);
            expect($status['queue']['failed_jobs_24h'])->toBe(1);
            expect($status)->toHaveKey('checked_at');
        });

        test('it returns unhealthy status when circuit is open', function () {
            // Open circuit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            $status = $this->service->getHealthStatus();

            expect($status['status'])->toBe('unhealthy');
            expect($status['circuit_breaker']['is_open'])->toBeTrue();
            expect($status['circuit_breaker']['failures'])->toBe(5);
            expect($status['circuit_breaker']['recovery_time'])->not->toBeNull();
        });
    });

    describe('resetCircuitBreaker', function () {
        test('it clears circuit breaker state', function () {
            // Open circuit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            expect($this->service->isCircuitOpen())->toBeTrue();

            // Reset
            $this->service->resetCircuitBreaker();

            expect($this->service->isCircuitOpen())->toBeFalse();
        });

        test('it clears health check cache', function () {
            Cache::put('blockchain:health_check', false, 60);

            $this->service->resetCircuitBreaker();

            expect(Cache::has('blockchain:health_check'))->toBeFalse();
        });

        test('it logs admin reset action', function () {
            $this->service->resetCircuitBreaker();

            Log::shouldHaveReceived('info')
                ->with('Circuit breaker manually reset by administrator');
        });
    });

    describe('integration scenarios', function () {
        test('it handles complete failure and recovery cycle with half-open state', function () {
            // 1. Start healthy
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn(['nodeaddress' => '1ABC123XYZ']);

            expect($this->service->isHealthy())->toBeTrue();

            // 2. Simulate blockchain going down (5 failures)
            Cache::flush(); // Clear health cache
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            expect($this->service->isCircuitOpen())->toBeTrue();
            expect($this->service->isHealthy())->toBeFalse();

            // 3. Verify circuit breaker blocks requests during recovery period
            // (isHealthy should not call getinfo when circuit is open)
            expect($this->service->isHealthy())->toBeFalse();

            // 4. Simulate recovery time passing
            Cache::put('blockchain:circuit_breaker', [
                'failures' => 5,
                'opened_at' => time() - 400,
                'recovery_time' => time() - 1,
            ], 360);

            // 5. Half-open state: test request during recovery attempt
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn(['nodeaddress' => '1ABC123XYZ']);

            // 6. Circuit should close after successful half-open test
            expect($this->service->isCircuitOpen())->toBeFalse();

            // 7. Next health check should succeed
            Cache::forget('blockchain:health_check');
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andReturn(['nodeaddress' => '1ABC123XYZ']);

            expect($this->service->isHealthy())->toBeTrue();
        });

        test('it handles recovery failure and extends recovery time', function () {
            // 1. Open circuit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordFailure();
            }

            expect($this->service->isCircuitOpen())->toBeTrue();

            // 2. Simulate recovery time passing
            Cache::put('blockchain:circuit_breaker', [
                'failures' => 5,
                'opened_at' => time() - 400,
                'recovery_time' => time() - 1,
            ], 360);

            // 3. Half-open test fails
            $this->multichainManager
                ->shouldReceive('getinfo')
                ->once()
                ->andThrow(new Exception('Still down'));

            // 4. Circuit should stay open
            expect($this->service->isCircuitOpen())->toBeTrue();

            // 5. Recovery time should be extended
            $circuitState = Cache::get('blockchain:circuit_breaker');
            expect($circuitState['recovery_time'])->toBeGreaterThan(time());
        });
    });
});
