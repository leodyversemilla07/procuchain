<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

describe('Authentication', function () {
    test('login screen can be rendered', function () {
        $response = $this->get('/login');

        expect($response)->toBeSuccessfulResponse();
    });

    describe('Login Process', function () {
        it('redirects users to correct dashboard based on role', function (string $role, string $expectedRedirectRoute) {
            $user = createUserWithRole($role);

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $this->assertAuthenticated();
            $response->assertRedirectToRoute($expectedRedirectRoute);
        })->with([
            ['bac_secretariat', 'bac-secretariat.dashboard'],
            ['bac_chairman', 'bac-chairman.dashboard'],
            ['hope', 'hope.dashboard'],
            ['admin', 'admin.dashboard'],
        ]);

        it('fails authentication with invalid password', function () {
            $user = User::factory()->create();

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $this->assertGuest();
        });
    });

    describe('Logout Process', function () {
        it('allows authenticated users to logout', function () {
            $user = User::factory()->create();

            // Authenticate the user
            $this->actingAs($user);
            $this->assertAuthenticated();

            // Test logout functionality
            Auth::logout();

            $this->assertGuest();
        });
    });
});
