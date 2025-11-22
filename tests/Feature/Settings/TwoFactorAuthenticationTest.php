<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('two factor settings page can be accessed without password confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.two-factor.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/two-factor')
        ->has('twoFactorEnabled')
        ->has('requiresConfirmation')
    );
});

test('two factor settings page requires authentication', function () {
    $response = $this->get(route('settings.two-factor.show'));

    $response->assertRedirect(route('login'));
});

test('two factor settings page shows correct status when disabled', function () {
    $user = User::factory()->create([
        'two_factor_secret' => null,
    ]);

    $response = $this->actingAs($user)->get(route('settings.two-factor.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/two-factor')
        ->where('twoFactorEnabled', false)
    );
});

test('two factor settings page shows correct status when enabled', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('settings.two-factor.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/two-factor')
        ->where('twoFactorEnabled', true)
    );
});
