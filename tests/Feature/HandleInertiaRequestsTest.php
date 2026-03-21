<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shares a narrowed authenticated user payload with roles permissions and can flags', function () {
    $role = Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create procurement', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view documents', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => 'Procurement User',
        'email' => 'procurement@example.com',
        'blockchain_address' => 'wallet-123',
    ]);
    $user->assignRole($role);
    $user->givePermissionTo(['create procurement', 'view documents']);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app(Store::class));

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user'])->toBe([
        'id' => $user->id,
        'name' => 'Procurement User',
        'email' => 'procurement@example.com',
        'role' => 'bac_secretariat',
        'avatar' => '',
        'blockchain_address' => 'wallet-123',
    ]);

    expect($shared['auth']['roles'])->toBe(['bac_secretariat'])
        ->and($shared['auth']['permissions'])->toContain('create procurement', 'view documents')
        ->and($shared['auth']['can']['manageProcurement'])->toBeTrue()
        ->and($shared['auth']['can']['viewDocuments'])->toBeTrue()
        ->and($shared['auth']['can']['manageUsers'])->toBeFalse()
        ->and(array_key_exists('created_at', $shared['auth']['user']))->toBeFalse()
        ->and(array_key_exists('email_verified_at', $shared['auth']['user']))->toBeFalse();
});

it('shares an empty auth payload for guests', function () {
    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => null);
    $request->setLaravelSession(app(Store::class));

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user'])->toBeNull()
        ->and($shared['auth']['roles'])->toBe([])
        ->and($shared['auth']['permissions'])->toBe([])
        ->and($shared['auth']['can']['manageProcurement'])->toBeFalse();
});
