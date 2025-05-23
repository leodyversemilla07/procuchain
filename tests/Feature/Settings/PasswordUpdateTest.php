<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->withSession(['_token' => 'test-token'])
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            '_token' => 'test-token',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->withSession(['_token' => 'test-token'])
        ->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            '_token' => 'test-token',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});
