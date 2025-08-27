<?php

use App\Models\User;
use App\Services\MultichainService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Backup .env to .env.testbackup (if present) and ensure a writable .env exists for tests
    if (file_exists(base_path('.env'))) {
        copy(base_path('.env'), base_path('.env.testbackup'));
        $env = file_get_contents(base_path('.env'));
    } else {
        // Create a minimal .env for tests so the command can persist values
        $env = "APP_ENV=testing".PHP_EOL."APP_KEY=base64:testing".PHP_EOL;
        file_put_contents(base_path('.env'), $env);
        // mark that we created it so afterEach can remove it if needed
        file_put_contents(base_path('.env.createdbytest'), '1');
    }

    // Ensure .env does not contain existing MULTICHAIN_*_ADDRESS entries for clean tests
    $env = preg_replace('/^MULTICHAIN_.*_ADDRESS=.*$/m', '', $env);
    file_put_contents(base_path('.env'), $env);
    // Save the sanitized version for comparison
    file_put_contents(base_path('.env.cleanbackup'), $env);

    // Use in-memory SQLite for tests to avoid missing database file issues
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    // Run migrations so the users table exists for in-memory DB
    $this->artisan('migrate');

    // RefreshDatabase trait will handle transactions for tests
});

afterEach(function () {
    // Restore .env from backup if present, otherwise remove any test-created .env
    if (file_exists(base_path('.env.testbackup'))) {
        copy(base_path('.env.testbackup'), base_path('.env'));
        unlink(base_path('.env.testbackup'));
    }

    if (file_exists(base_path('.env.createdbytest'))) {
        // remove the test-created marker and .env
        unlink(base_path('.env.createdbytest'));
        if (file_exists(base_path('.env.testbackup')) === false && file_exists(base_path('.env'))) {
            unlink(base_path('.env'));
        }
    }
});

it('does not persist when --persist is not provided', function () {
    // Mock MultichainService to return a new address for admin
    $mock = Mockery::mock(MultichainService::class);
    $mock->shouldReceive('listAddresses')->andReturn([]);
    $mock->shouldReceive('getNewAddress')->andReturn('1GeneratedAddressAdmin');
    $mock->shouldReceive('validateAddress')->andReturn(['isvalid' => true]);

    $this->app->instance(MultichainService::class, $mock);

    // Ensure config has no preset addresses so the command will generate them
    config()->set('multichain.addresses', []);

    // Run the command without --persist
    $this->artisan('multichain:setup')->assertExitCode(0);

    // Ensure .env was not modified compared to the sanitized clean backup
    $env = file_get_contents(base_path('.env'));
    $clean = file_get_contents(base_path('.env.cleanbackup'));
    expect($env)->toBe($clean);
});

it('persists generated addresses when confirmed', function () {
    // Mock MultichainService to return a new address for admin
    $mock = Mockery::mock(MultichainService::class);
    $mock->shouldReceive('listAddresses')->andReturn([]);
    $mock->shouldReceive('getNewAddress')->andReturn('1GeneratedAddressAdmin');
    $mock->shouldReceive('validateAddress')->andReturn(['isvalid' => true]);

    $this->app->instance(MultichainService::class, $mock);

    // Ensure config has no preset addresses so the command will generate them
    config()->set('multichain.addresses', []);

    // Run the command with --persist and --force to bypass confirmation
    $this->artisan('multichain:setup', ['--persist' => true, '--force' => true])->assertSuccessful();

    $env = file_get_contents(base_path('.env'));
    expect($env)->toContain('MULTICHAIN_ADMIN_ADDRESS=1GeneratedAddressAdmin');
});

it('creates streams, grants permissions, persists addresses and updates users', function () {
    // Prepare config: request admin role permissions and include admin in addresses resolution
    config()->set('multichain.addresses', []);
    config()->set('multichain.permissions.roles', [
        'admin' => [
            'global' => ['connect'],
            'stream' => ['write'],
        ],
    ]);

    // Create a user with role 'admin' and a different blockchain address to be updated
    $user = User::factory()->create([
        'email' => 'admin@example.test',
        'role' => 'admin',
        'blockchain_address' => 'old-address',
    ]);

    // Mock MultichainService to exercise stream creation and granting
    $mock = Mockery::mock(MultichainService::class);
    $mock->shouldReceive('listAddresses')->once()->andReturn([]);
    $mock->shouldReceive('getNewAddress')->once()->andReturn('1GeneratedAddressAdmin');
    $mock->shouldReceive('validateAddress')->andReturn(['isvalid' => true]);
    // Streams: pretend none exist so createStream is called
    $mock->shouldReceive('getStreamInfo')->andThrow(new Exception('not found'));
    $mock->shouldReceive('createStream')->andReturnUsing(function ($stream) {
        return ['stream' => $stream];
    });
    $mock->shouldReceive('subscribe')->andReturnTrue();
    $mock->shouldReceive('grant')->andReturnTrue();

    $this->app->instance(MultichainService::class, $mock);

    // Run the command with persist and force to bypass interactive confirmation
    Artisan::call('multichain:setup', ['--persist' => true, '--force' => true]);

    // Ensure .env has the persisted address
    $env = file_get_contents(base_path('.env'));
    expect(str_contains($env, 'MULTICHAIN_ADMIN_ADDRESS=1GeneratedAddressAdmin'))->toBeTrue();

    // Ensure the user blockchain_address was updated
    $user->refresh();
    expect($user->blockchain_address)->toBe('1GeneratedAddressAdmin');
});
