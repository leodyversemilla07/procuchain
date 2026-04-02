<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('home'))->name('home');

Route::inertia('/about', 'about')->name('about');
Route::get('/workflow', WorkflowController::class)->name('workflow');
Route::inertia('/team', 'team')->name('team');
Route::inertia('/contact', 'contact')->name('contact');
Route::inertia('/privacy', 'privacy')->name('privacy.policy');
Route::inertia('/terms', 'terms')->name('terms.service');

Route::get('/invitation/{token}', [AcceptInvitationController::class, 'show'])
    ->name('invitation.show');
Route::post('/invitation/{token}/accept', [AcceptInvitationController::class, 'accept'])
    ->name('invitation.accept');
