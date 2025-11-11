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

    // Profile settings
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('settings.profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('settings.profile.destroy');

    // Password settings
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('settings.password.update');

    // Push notification settings
    Route::get('settings/push-notification', [PushNotificationController::class, 'edit'])->name('settings.push-notification.edit');

    // Push notification subscription management
    Route::get('settings/push-notification/subscriptions', [PushNotificationController::class, 'index'])->name('settings.push-notification.subscriptions.index');
    Route::post('settings/push-notification/subscribe', [PushNotificationController::class, 'store'])->name('settings.push-notification.subscribe');
    Route::delete('settings/push-notification/unsubscribe', [PushNotificationController::class, 'destroy'])->name('settings.push-notification.unsubscribe');

    // Email notification settings
    Route::get('settings/email-notification', [EmailNotificationController::class, 'edit'])->name('settings.email-notification.edit');
    Route::patch('settings/email-notification', [EmailNotificationController::class, 'update'])->name('settings.email-notification.update');

    // Appearance settings
    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('settings.appearance.edit');

    // Two-factor authentication settings
    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('settings.two-factor.show');
});
