<?php

use App\Mail\NewLocationLoginAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('new location login alert email can be sent', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'name' => 'Test User',
    ]);

    $location = [
        'city' => 'Manila',
        'region' => 'NCR',
        'country' => 'Philippines',
    ];

    $ipAddress = '192.168.1.1';
    $userAgent = 'Mozilla/5.0';
    $loginTime = Carbon::parse('2026-07-21 14:30:00');

    Mail::to($user->email)->send(new NewLocationLoginAlert($user, $location, $ipAddress, $userAgent, $loginTime));

    Mail::assertSent(NewLocationLoginAlert::class, function ($mail) use ($user, $ipAddress, $userAgent, $loginTime) {
        return $mail->hasTo($user->email) &&
               $mail->user->id === $user->id &&
               $mail->ipAddress === $ipAddress &&
               $mail->userAgent === $userAgent &&
               $mail->loginTime->eq($loginTime) &&
               $mail->formattedLocation === 'Manila, NCR, Philippines';
    });
});

test('new location login alert email has correct subject and content', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'name' => 'Test User',
    ]);

    $location = [
        'city' => 'Cebu',
        'region' => 'Central Visayas',
        'country' => 'Philippines',
    ];

    $ipAddress = '10.0.0.1';
    $userAgent = 'Chrome/120';
    $loginTime = now();

    $mail = new NewLocationLoginAlert($user, $location, $ipAddress, $userAgent, $loginTime);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Security Alert: New Login Location Detected - ProcuChain');

    $content = $mail->content();
    expect($content->view)->toBe('emails.new-location-login-alert');
    expect($content->with)->toHaveKey('user', $user)
        ->and($content->with)->toHaveKey('location', $location)
        ->and($content->with)->toHaveKey('ipAddress', $ipAddress)
        ->and($content->with)->toHaveKey('userAgent', $userAgent)
        ->and($content->with)->toHaveKey('formattedLocation', 'Cebu, Central Visayas, Philippines');
});

test('new location login alert handles unknown location', function () {
    $user = User::factory()->create();
    $location = [];
    $loginTime = now();

    $mail = new NewLocationLoginAlert($user, $location, '0.0.0.0', null, $loginTime);

    expect($mail->formattedLocation)->toBe('Unknown Location');
});
