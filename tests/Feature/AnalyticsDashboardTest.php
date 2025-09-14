<?php

use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\get;
use App\Models\User;

it('renders analytics dashboard with procurement prop', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    get('/analytics')
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/dashboard')
            ->has('procurement')
            ->has('filters', fn(Assert $filters) => $filters->where('time_range', '30_days')->etc())
            ->has('timeRangeOptions')
        );
});
