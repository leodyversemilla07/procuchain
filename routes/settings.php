<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\EmailNotificationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PushNotificationController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('settings.password.update');

    // Push notification settings
    Route::get('settings/push-notification', [PushNotificationController::class, 'edit'])->name('push-notification.edit');

    // Push notification routes
    Route::get('/settings/push/subscriptions', [PushNotificationController::class, 'index']);
    Route::post('/settings/push/subscribe', [PushNotificationController::class, 'store']);
    Route::delete('/settings/push/unsubscribe', [PushNotificationController::class, 'destroy']);

    // Email notification settings
    Route::get('settings/email-notification', [EmailNotificationController::class, 'edit'])->name('email-notification.edit');
    Route::patch('settings/email-notification', [EmailNotificationController::class, 'update'])->name('email-notification.update');

    // Appearance settings
    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
