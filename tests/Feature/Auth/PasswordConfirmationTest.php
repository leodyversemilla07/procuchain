<?php

use App\Enums\UserRoleEnums;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/confirm-password')
    );
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

test('password can be confirmed', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::BAC_SECRETARIAT->value, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);
    $user->assignRole(UserRoleEnums::BAC_SECRETARIAT->value);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->withSession(['url.intended' => route('bac-secretariat.dashboard')])
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('bac-secretariat.dashboard'));
    $response->assertSessionHas('auth.password_confirmed_at');
});

test('password confirmation fails with wrong password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ]);

    $response->assertSessionHasErrors(['password']);
    $response->assertSessionMissing('auth.password_confirmed_at');
});

test('password confirmation redirects to bac chairman dashboard', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::BAC_CHAIRMAN->value, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);
    $user->assignRole(UserRoleEnums::BAC_CHAIRMAN->value);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('bac-chairman.dashboard'));
});

test('password confirmation redirects to hope dashboard', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::HOPE->value, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);
    $user->assignRole(UserRoleEnums::HOPE->value);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('hope.dashboard'));
});

test('password confirmation redirects to admin dashboard', function () {
    Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);
    $user->assignRole(UserRoleEnums::ADMIN->value);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('admin.dashboard'));
});

