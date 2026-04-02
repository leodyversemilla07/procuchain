<?php

use Illuminate\Support\Facades\Route;

it('keeps key named routes stable after route extraction', function () {
    $routes = Route::getRoutes();

    expect($routes->getByName('home')?->uri())->toBe('/')
        ->and($routes->getByName('reports.index')?->uri())->toBe('reports')
        ->and($routes->getByName('search')?->uri())->toBe('search')
        ->and($routes->getByName('bac-secretariat.dashboard')?->uri())->toBe('bac-secretariat/dashboard')
        ->and($routes->getByName('admin.dashboard')?->uri())->toBe('admin/dashboard')
        ->and($routes->getByName('procurement.verify')?->uri())->toBe('procurement/{pr_number}/verify');
});
