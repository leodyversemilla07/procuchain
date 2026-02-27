<?php

// Disable Guzzle's shutdown task queue to prevent stale HTTP calls during teardown
\GuzzleHttp\Promise\Utils::queue()->disableShutdown();

// Ensure unlimited execution time during tests (prevents shutdown timeouts)
set_time_limit(0);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Browser');

// Configure browser testing
pest()->browser()
    ->timeout(30000); // 30 seconds timeout for browser operations

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeValidUser', function () {
    return $this->toBeInstanceOf(App\Models\User::class)
        ->and($this->value->exists)
        ->toBeTrue();
});

expect()->extend('toHaveValidationError', function (string $field) {
    return $this->toHaveKey('errors')
        ->and($this->value['errors'])
        ->toHaveKey($field);
});

expect()->extend('toBeSuccessfulResponse', function () {
    return $this->toBeInstanceOf(Illuminate\Testing\TestResponse::class)
        ->and($this->value->status())
        ->toBeBetween(200, 299);
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createUserWithRole(string $role, array $attributes = []): App\Models\User
{
    // Create role if it doesn't exist
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'guard_name' => 'web']);

    $user = App\Models\User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function createLockedUser(array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->create(array_merge([
        'account_locked' => true,
        'locked_at' => now(),
        'lock_expires_at' => now()->addMinutes(30),
        'failed_login_attempts' => 3,
        'locked_reason' => 'Multiple failed login attempts',
        'email_notifications_enabled' => true,
    ], $attributes));
}

/**
 * Helper to set private/protected properties via reflection.
 */
function setPrivate(object $object, string $property, mixed $value): void
{
    $ref = new \ReflectionClass($object);
    $prop = $ref->getProperty($property);
    $prop->setAccessible(true);
    $prop->setValue($object, $value);
}
