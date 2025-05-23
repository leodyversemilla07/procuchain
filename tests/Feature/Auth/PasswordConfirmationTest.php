<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    // Create a test route for the test to redirect to
    Route::get('/test-redirect', fn () => 'Redirected')->name('bac-secretariat.dashboard');

    // Create user with a specific role
    $user = User::factory()->create([
        'role' => 'bac_secretariat',
    ]);

    $response = $this->actingAs($user)
                     ->withSession(['_token' => 'test-token'])
                     ->post('/confirm-password', [
                         'password' => 'password',
                         '_token' => 'test-token',
                     ]);

    // Now we can test the redirect works
    $response->assertRedirect(route('bac-secretariat.dashboard'));
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
                     ->withSession(['_token' => 'test-token'])
                     ->post('/confirm-password', [
                         'password' => 'wrong-password',
                         '_token' => 'test-token',
                     ]);

    $response->assertSessionHasErrors('password');
});
