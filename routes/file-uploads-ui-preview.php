<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/bac-secretariat/preprocurement', function () {
    return Inertia::render('bac-secretariat/procurement-stage/pre-procurement-conference-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.preprocurement');

Route::get('/bac-secretariat/pre-bid-conference-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/pre-bid-conference-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.pre-bid-conference-upload.simple');

Route::get('/bac-secretariat/supplemental-bid-bulletin-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.supplemental-bid-bulletin-upload.simple');

Route::get('/bac-secretariat/bidding-documents-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bidding-documents-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bidding-documents-upload.simple');

Route::get('/bac-secretariat/bid-opening-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bid-opening-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bid-opening-upload.simple');

Route::get('/bac-secretariat/bid-evaluation-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bid-evaluation-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bid-evaluation-upload.simple');

Route::get('/bac-secretariat/bac-resolution-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bac-resolution-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bac-resolution-upload.simple');

Route::get('/bac-secretariat/post-qualification-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/post-qualification-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.post-qualification-upload.simple');

Route::get('/bac-secretariat/noa-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/noa-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.noa-upload.simple');

Route::get('/bac-secretariat/performance-bond-contract-po-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/performance-bond-contract-po-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.performance-bond-contract-po-upload.simple');

Route::get('/bac-secretariat/ntp-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/ntp-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.ntp-upload.simple');

Route::get('/bac-secretariat/monitoring-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/monitoring-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.monitoring-upload.simple');

Route::get('/bac-secretariat/completion-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/completion-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.completion-upload.simple');
