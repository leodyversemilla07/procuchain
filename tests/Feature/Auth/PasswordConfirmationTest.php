<?php

use App\Enums\UserRoleEnums;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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
    $user = User::factory()->create([
        'role' => UserRoleEnums::BAC_SECRETARIAT->value,
        'password' => bcrypt('password'),
    ]);

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
    $user = User::factory()->create([
        'role' => UserRoleEnums::BAC_CHAIRMAN->value,
        'password' => bcrypt('password'),
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('bac-chairman.dashboard'));
});

test('password confirmation redirects to hope dashboard', function () {
    $user = User::factory()->create([
        'role' => UserRoleEnums::HOPE->value,
        'password' => bcrypt('password'),
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('hope.dashboard'));
});

test('password confirmation redirects to admin dashboard', function () {
    $user = User::factory()->create([
        'role' => UserRoleEnums::ADMIN->value,
        'password' => bcrypt('password'),
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('admin.dashboard'));
});
