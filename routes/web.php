<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Bare prefix redirects → role dashboard
Route::redirect('/admin', '/admin/dashboard', 301);
Route::redirect('/bac-secretariat', '/bac-secretariat/dashboard', 301);
Route::redirect('/bac-chairman', '/bac-chairman/dashboard', 301);
Route::redirect('/hope', '/hope/dashboard', 301);

require __DIR__.'/web/public.php';
require __DIR__.'/web/authenticated.php';
require __DIR__.'/web/bac-secretariat.php';
require __DIR__.'/web/role-dashboards.php';
require __DIR__.'/web/admin.php';
require __DIR__.'/web/reports.php';
require __DIR__.'/auth.php';
require __DIR__.'/settings.php';

if (app()->environment('local')) {
    Route::get('/errors/{status}', function ($status) {
        abort_if(! in_array($status, [401, 403, 404, 419, 429, 500, 503]), 404);

        return Inertia::render('error', ['status' => (int) $status]);
    })->name('errors.preview');
}
