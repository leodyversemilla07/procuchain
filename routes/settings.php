<?php

use App\Http\Controllers\Settings\MfaController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PushNotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit')->middleware('mfa');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update')->middleware('mfa');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy')->middleware('mfa');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit')->middleware('mfa');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update')->middleware('mfa');

    // MFA routes don't require MFA middleware to avoid circular dependency
    Route::get('settings/mfa', [MfaController::class, 'edit'])->name('mfa.edit');
    Route::post('settings/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('settings/mfa/enable', [MfaController::class, 'enable'])->name('mfa.enable');
    Route::post('settings/mfa/disable', [MfaController::class, 'disable'])->name('mfa.disable');
    Route::post('settings/mfa/backup-codes/regenerate', [MfaController::class, 'regenerateBackupCodes'])->name('mfa.backup-codes.regenerate');

    // Push notification settings
    Route::get('settings/push-notification', [PushNotificationController::class, 'edit'])->name('push-notification.edit')->middleware('mfa');
    // Push notification routes
    Route::get('/settings/push/subscriptions', [PushNotificationController::class, 'index']);
    Route::post('/settings/push/subscribe', [PushNotificationController::class, 'store']);
    Route::delete('/settings/push/unsubscribe', [PushNotificationController::class, 'destroy']);

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance')->middleware('mfa');
});

// MFA verification routes (outside auth middleware)
Route::get('mfa/verify', function () {
    if (! session('mfa_user_id')) {
        return redirect()->route('login');
    }

    return Inertia::render('auth/mfa-verify');
})->name('mfa.verify.form');

Route::post('mfa/verify', [MfaController::class, 'verify'])->name('mfa.verify');
