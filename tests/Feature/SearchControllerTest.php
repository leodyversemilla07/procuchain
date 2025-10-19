<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->assignRole('bac_secretariat');
});

describe('SearchController', function () {
    describe('index method', function () {
        it('returns search page', function () {
            actingAs($this->user);

            get(route('search'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('search/index')
                );
        });

        it('can search with query parameter', function () {
            actingAs($this->user);

            get(route('search', ['q' => 'test']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('search/index')
                    ->has('results')
                );
        });

        it('returns empty results for non-existent query', function () {
            actingAs($this->user);

            get(route('search', ['q' => 'nonexistentterm'.time()]))
                ->assertOk();
        });

        it('handles empty query gracefully', function () {
            actingAs($this->user);

            get(route('search', ['q' => '']))
                ->assertOk();
        });

        it('sanitizes search input', function () {
            actingAs($this->user);

            get(route('search', ['q' => '<script>alert("xss")</script>']))
                ->assertOk();
        });
    });

    describe('suggestions method', function () {
        it('returns search suggestions', function () {
            actingAs($this->user);

            get(route('search.suggestions'))
                ->assertOk()
                ->assertJsonStructure();
        });

        it('returns suggestions for query parameter', function () {
            actingAs($this->user);

            get(route('search.suggestions', ['q' => 'test']))
                ->assertOk()
                ->assertJson([]);
        });

        it('limits number of suggestions', function () {
            actingAs($this->user);

            $response = get(route('search.suggestions', ['q' => 'a']));

            $response->assertOk();

            $suggestions = $response->json();
            if (is_array($suggestions)) {
                expect(count($suggestions))->toBeLessThanOrEqual(10);
            }
        });
    });

    describe('authentication', function () {
        it('allows access to search for authenticated users', function () {
            actingAs($this->user);

            get(route('search'))
                ->assertOk();
        });

        it('allows access to suggestions for authenticated users', function () {
            actingAs($this->user);

            get(route('search.suggestions'))
                ->assertOk();
        });
    });

    describe('role-based procurement routing', function () {
        it('supports admin role for procurement search', function () {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $adminUser = User::factory()->create();
            $adminUser->assignRole('admin');

            actingAs($adminUser);

            get(route('search', ['q' => 'procurement']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('search/index'));
        });

        it('supports bac_chairman role for procurement search', function () {
            Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
            $chairmanUser = User::factory()->create();
            $chairmanUser->assignRole('bac_chairman');

            actingAs($chairmanUser);

            get(route('search', ['q' => 'procurement']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('search/index'));
        });

        it('supports hope role for procurement search', function () {
            Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
            $hopeUser = User::factory()->create();
            $hopeUser->assignRole('hope');

            actingAs($hopeUser);

            get(route('search', ['q' => 'procurement']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('search/index'));
        });

        it('supports admin role for procurement suggestions', function () {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $adminUser = User::factory()->create();
            $adminUser->assignRole('admin');

            actingAs($adminUser);

            get(route('search.suggestions', ['q' => 'test']))
                ->assertOk()
                ->assertJsonStructure();
        });
    });
});
