# Testing Guide

This guide explains the testing infrastructure for ProcuChain, including how to run tests, the configuration setup, and troubleshooting common issues.

## Overview

ProcuChain uses [Pest](https://pestphp.com/) as its testing framework, built on top of PHPUnit. The testing environment is designed to be **fast, isolated, and dependency-free** for Unit and Feature tests.

Key features of the test setup:

- **Database**: Uses `sqlite` in-memory database (`:memory:`) for speed.
- **Cache**: Uses `array` driver to avoid external Redis dependencies.
- **Queue**: Uses `sync` driver to process jobs immediately without a worker.
- **Session**: Uses `array` driver.

## Running Tests

### Run All Tests

Execute the full test suite:

```bash
php artisan test
```

### Run Specific Tests

Filter by test file or description:

```bash
# Run tests for a specific class/feature
php artisan test --filter=BlockchainMonitoringService

# Run a specific test file
php artisan test tests/Feature/ProcurementListControllerTest.php
```

### Compact Output

For cleaner output, use the `--compact` flag:

```bash
php artisan test --compact
```

## Configuration (`phpunit.xml`)

The `phpunit.xml` file governs the test environment configuration. We have configured it to override production/local `.env` settings to ensure tests run reliably in any environment (including CI/CD) without requiring external services like Redis.

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>

    <!-- Infrastructure Isolation -->
    <env name="CACHE_STORE" value="array"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="MAIL_MAILER" value="array"/>

    <!-- Database Isolation -->
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>

    <!-- External Service Mocking -->
    <env name="TELESCOPE_ENABLED" value="false"/>
    <env name="PULSE_ENABLED" value="false"/>
</php>
```

### Why "Array" Drivers?

Using `array` drivers for Cache and Session means data is stored in PHP memory for the duration of the test. This prevents "state leak" between tests and eliminates the need for a running Redis server during development testing.

## Service Layer Testing

We extensively use **Mockery** to mock external dependencies, especially the Blockchain interface.

### Mocking the Blockchain Manager

Most services depend on `App\Services\Manager`. In tests, we mock this to simulate blockchain responses without making actual RPC calls.

```php
// Example Mock Setup
beforeEach(function () {
    $this->multichainManager = mock(Manager::class);
    $this->service = new MyService($this->multichainManager);
});

// Example Expectation
it('checks blockchain connection', function () {
    $this->multichainManager
        ->shouldReceive('getinfo')
        ->once()
        ->andReturn(['nodeaddress' => '1ABC...']);

    expect($this->service->isHealthy())->toBeTrue();
});
```

## Troubleshooting

### `StreamInitException` / Redis Connection Refused

**Symptom**: `Predis\Connection\ConnectionException: No connection could be made because the target machine actively refused it [tcp://127.0.0.1:6379]`.

**Cause**: The application is trying to connect to a real Redis server, but it's not running or reachable. This usually happens if `phpunit.xml` is configured to use `redis` driver.

**Solution**:
Ensure your `phpunit.xml` has the following overrides (as updated in the latest build):

```xml
<env name="CACHE_DRIVER" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

If you still encounter this, try clearing your config cache:

```bash
php artisan config:clear
```

### Database Mocking Issues

**Symptom**: "Table not found" or foreign key constraint errors in tests.

**Solution**:
Ensure your test file uses the `RefreshDatabase` trait. This runs migrations for the in-memory SQLite database before each test.

```php
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
```

### "Final Class" Mocking Errors

**Symptom**: `Class ... is marked final and its methods cannot be replaced.`

**Cause**: You are trying to mock a class defined as `final class ...`. Mockery cannot create a proxy for final classes by default.

**Solution**:

1. Remove the `final` keyword from the class definition (recommended for services that need testing).
2. Or use interfaces for dependency injection and mock the interface instead.
