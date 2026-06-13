<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('proFile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/settings/profile');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.role', null)
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.role', null)
            ->where('auth.can.manageProcurement', false)
            ->where('mustVerifyEmail', false)
        );
});

test('proFile page shares primary role and capabilities for role-based users', function () {
    $user = createUserWithRole('bac_secretariat', [
        'name' => 'BAC Secretariat User',
        'email' => 'secretariat@example.com',
        'blockchain_address' => 'secretariat-wallet',
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/settings/profile');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.role', 'bac_secretariat')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'BAC Secretariat User')
            ->where('auth.user.email', 'secretariat@example.com')
            ->where('auth.user.role', 'bac_secretariat')
            ->where('auth.user.blockchain_address', 'secretariat-wallet')
            ->where('auth.can.manageProcurement', true)
            ->where('auth.can.manageUsers', false)
        );
});

test('proFile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession([
            '_token' => 'test-token',
            'auth.password_confirmed_at' => time(),
        ])
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            '_token' => 'test-token',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession([
            '_token' => 'test-token',
            'auth.password_confirmed_at' => time(),
        ])
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
            '_token' => 'test-token',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession([
            '_token' => 'test-token',
            'auth.password_confirmed_at' => time(),
        ])
        ->delete('/settings/profile', [
            'password' => 'password',
            '_token' => 'test-token',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->withSession([
            '_token' => 'test-token',
            'auth.password_confirmed_at' => time(),
        ])
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
            '_token' => 'test-token',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});
