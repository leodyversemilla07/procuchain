<?php

use App\Models\DocumentView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DocumentView Model - Configuration', function () {
    test('has correct fillable fields', function () {
        $view = new DocumentView;
        $expectedFillable = [
            'user_id',
            'file_key',
            'pr_number',
            'procurement_title',
            'document_type',
            'stage',
            'ip_address',
            'user_agent',
            'view_duration',
            'metadata',
            'viewed_at',
            'txid',
            'data_hash',
            'blockchain_synced_at',
        ];

        expect($view->getFillable())->toBe($expectedFillable);
    });

    test('casts attributes correctly', function () {
        $view = DocumentView::factory()->create([
            'metadata' => ['browser' => 'Chrome', 'device' => 'desktop'],
            'viewed_at' => now(),
        ]);

        expect($view->metadata)->toBeArray();
        expect($view->viewed_at)->toBeInstanceOf(Carbon::class);
    });

    test('timestamps are managed automatically', function () {
        $view = DocumentView::factory()->create();

        expect($view->created_at)->toBeInstanceOf(Carbon::class);
        expect($view->updated_at)->toBeInstanceOf(Carbon::class);
    });
});

describe('DocumentView Model - Relationships', function () {
    test('belongs to user', function () {
        $user = User::factory()->create();
        $view = DocumentView::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($view->user)->toBeInstanceOf(User::class);
        expect($view->user->id)->toBe($user->id);
    });

    test('can eager load user relationship', function () {
        $user = User::factory()->create();
        $view = DocumentView::factory()->create([
            'user_id' => $user->id,
        ]);

        $loadedView = DocumentView::with('user')->find($view->id);

        expect($loadedView->relationLoaded('user'))->toBeTrue();
        expect($loadedView->user)->not->toBeNull();
    });

    test('user can have multiple document views', function () {
        $user = User::factory()->create();

        DocumentView::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);

        $views = DocumentView::where('user_id', $user->id)->get();

        expect($views)->toHaveCount(5);
    });
});

describe('DocumentView Model - Static Methods - Recent Views', function () {
    test('getRecentViewsForFile returns views for specific file', function () {
        $fileKey1 = 'file-abc-123';
        $fileKey2 = 'file-xyz-789';

        DocumentView::factory()->count(3)->create([
            'file_key' => $fileKey1,
            'viewed_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);

        DocumentView::factory()->count(2)->create([
            'file_key' => $fileKey2,
            'viewed_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);

        $views = DocumentView::getRecentViewsForFile($fileKey1);

        expect($views)->toHaveCount(3);
        expect($views->pluck('file_key')->unique()->first())->toBe($fileKey1);
    });

    test('getRecentViewsForFile respects limit parameter', function () {
        $fileKey = 'file-limit-test';

        DocumentView::factory()->count(15)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subMinutes(fake()->numberBetween(1, 100)),
        ]);

        $views = DocumentView::getRecentViewsForFile($fileKey, 5);

        expect($views)->toHaveCount(5);
    });

    test('getRecentViewsForFile orders by viewed_at descending', function () {
        $fileKey = 'file-order-test';

        $oldest = DocumentView::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(3),
        ]);

        $newest = DocumentView::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now(),
        ]);

        $middle = DocumentView::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(1),
        ]);

        $views = DocumentView::getRecentViewsForFile($fileKey);

        expect($views->first()->id)->toBe($newest->id);
        expect($views->last()->id)->toBe($oldest->id);
    });

    test('getRecentViewsForFile eager loads user relationship', function () {
        $user = User::factory()->create();
        $fileKey = 'file-eager-test';

        DocumentView::factory()->create([
            'file_key' => $fileKey,
            'user_id' => $user->id,
        ]);

        $views = DocumentView::getRecentViewsForFile($fileKey);

        expect($views->first()->relationLoaded('user'))->toBeTrue();
    });
});

describe('DocumentView Model - Static Methods - Procurement Stats', function () {
    test('getProcurementViewStats returns stats grouped by file', function () {
        $pr_number = 'PR-2025-996-0001';

        DocumentView::factory()->count(3)->create([
            'pr_number' => $pr_number,
            'file_key' => 'file-1',
            'document_type' => 'procurement_plan',
            'stage' => 'procurement_initiation',
        ]);

        DocumentView::factory()->count(2)->create([
            'pr_number' => $pr_number,
            'file_key' => 'file-2',
            'document_type' => 'bidding_documents',
            'stage' => 'submission_evaluation',
        ]);

        $stats = DocumentView::getProcurementViewStats($pr_number);

        expect($stats)->toHaveCount(2);
    });

    test('hasUserViewedFile returns true when user viewed file', function () {
        $user = User::factory()->create();
        $fileKey = 'file-viewed-test';

        DocumentView::factory()->create([
            'user_id' => $user->id,
            'file_key' => $fileKey,
        ]);

        $hasViewed = DocumentView::hasUserViewedFile($user->id, $fileKey);

        expect($hasViewed)->toBeTrue();
    });

    test('hasUserViewedFile returns false when user has not viewed file', function () {
        $user = User::factory()->create();
        $fileKey = 'file-not-viewed';

        $hasViewed = DocumentView::hasUserViewedFile($user->id, $fileKey);

        expect($hasViewed)->toBeFalse();
    });
});

describe('DocumentView Model - Static Methods - Most Viewed', function () {
    test('getMostViewedDocuments returns documents ordered by views', function () {
        DocumentView::factory()->count(5)->create([
            'file_key' => 'popular-file',
            'document_type' => 'bidding_documents',
            'procurement_title' => 'Popular Procurement',
            'stage' => 'submission_evaluation',
        ]);

        DocumentView::factory()->count(2)->create([
            'file_key' => 'less-popular-file',
            'document_type' => 'procurement_plan',
            'procurement_title' => 'Less Popular Procurement',
            'stage' => 'procurement_initiation',
        ]);

        $mostViewed = DocumentView::getMostViewedDocuments();

        expect($mostViewed->first()->file_key)->toBe('popular-file');
    });

    test('getMostViewedDocuments respects limit parameter', function () {
        for ($i = 1; $i <= 15; $i++) {
            DocumentView::factory()->count($i)->create([
                'file_key' => "file-{$i}",
                'document_type' => 'test_doc',
                'procurement_title' => "Procurement {$i}",
                'stage' => 'test_stage',
            ]);
        }

        $mostViewed = DocumentView::getMostViewedDocuments(5);

        expect($mostViewed)->toHaveCount(5);
    });
});

describe('DocumentView Model - Static Methods - File Statistics', function () {
    test('getFileStatistics returns total views', function () {
        $fileKey = 'file-total-views';

        DocumentView::factory()->count(7)->create([
            'file_key' => $fileKey,
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats['total_views'])->toBe(7);
    });

    test('getFileStatistics returns unique viewers', function () {
        $fileKey = 'file-unique-viewers';
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DocumentView::factory()->count(3)->create([
            'file_key' => $fileKey,
            'user_id' => $user1->id,
        ]);

        DocumentView::factory()->count(2)->create([
            'file_key' => $fileKey,
            'user_id' => $user2->id,
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats['unique_viewers'])->toBe(2);
    });

    test('getFileStatistics returns today views', function () {
        $fileKey = 'file-today-views';

        DocumentView::factory()->count(4)->create([
            'file_key' => $fileKey,
            'viewed_at' => now(),
        ]);

        DocumentView::factory()->count(2)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(2),
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats['today_views'])->toBe(4);
    });

    test('getFileStatistics returns week views', function () {
        $fileKey = 'file-week-views';

        DocumentView::factory()->count(5)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(3),
        ]);

        DocumentView::factory()->count(2)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(10),
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats['week_views'])->toBe(5);
    });

    test('getFileStatistics returns month views', function () {
        $fileKey = 'file-month-views';

        DocumentView::factory()->count(6)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(15),
        ]);

        DocumentView::factory()->count(2)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(45),
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats['month_views'])->toBe(6);
    });

    test('getFileStatistics returns first and last viewed dates', function () {
        $fileKey = 'file-dates-test';

        DocumentView::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(10),
        ]);

        DocumentView::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now(),
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats['first_viewed'])->not->toBeNull();
        expect($stats['last_viewed'])->not->toBeNull();
        expect($stats['first_viewed'])->toBeInstanceOf(Carbon::class);
        expect($stats['last_viewed'])->toBeInstanceOf(Carbon::class);
    });

    test('getFileStatistics returns all expected keys', function () {
        $fileKey = 'file-keys-test';

        DocumentView::factory()->create([
            'file_key' => $fileKey,
        ]);

        $stats = DocumentView::getFileStatistics($fileKey);

        expect($stats)->toHaveKeys([
            'total_views',
            'unique_viewers',
            'today_views',
            'week_views',
            'month_views',
            'first_viewed',
            'last_viewed',
        ]);
    });
});

describe('DocumentView Model - Metadata Operations', function () {
    test('stores metadata as array', function () {
        $metadata = [
            'browser' => 'Chrome',
            'device' => 'desktop',
            'screen_resolution' => '1920x1080',
        ];

        $view = DocumentView::factory()->create([
            'metadata' => $metadata,
        ]);

        expect($view->metadata)->toBeArray();
        expect($view->metadata)->toBe($metadata);
    });

    test('handles empty metadata', function () {
        $view = DocumentView::factory()->create([
            'metadata' => [],
        ]);

        expect($view->metadata)->toBeArray();
        expect($view->metadata)->toBeEmpty();
    });

    test('handles null metadata', function () {
        $view = DocumentView::factory()->create([
            'metadata' => null,
        ]);

        expect($view->metadata)->toBeNull();
    });

    test('can update metadata', function () {
        $view = DocumentView::factory()->create([
            'metadata' => ['key1' => 'value1'],
        ]);

        $view->update([
            'metadata' => ['key1' => 'value1', 'key2' => 'value2'],
        ]);

        expect($view->fresh()->metadata)->toHaveKey('key2');
        expect($view->fresh()->metadata['key2'])->toBe('value2');
    });

    test('metadata supports nested structures', function () {
        $metadata = [
            'user_details' => [
                'name' => 'John Doe',
                'role' => 'admin',
            ],
            'session' => [
                'id' => 'session-123',
                'started_at' => now()->toDateTimeString(),
            ],
        ];

        $view = DocumentView::factory()->create([
            'metadata' => $metadata,
        ]);

        expect($view->metadata['user_details'])->toBeArray();
        expect($view->metadata['user_details']['name'])->toBe('John Doe');
    });
});

describe('DocumentView Model - Data Integrity', function () {
    test('requires user_id', function () {
        expect(fn () => DocumentView::create([
            'file_key' => 'test-file',
            'pr_number' => 'PR-001',
            'viewed_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    test('requires file_key', function () {
        $user = User::factory()->create();

        expect(fn () => DocumentView::create([
            'user_id' => $user->id,
            'pr_number' => 'PR-001',
            'viewed_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    test('can store nullable fields', function () {
        $user = User::factory()->create();

        $view = DocumentView::factory()->create([
            'user_id' => $user->id,
            'view_duration' => null,
            'metadata' => null,
        ]);

        expect($view->view_duration)->toBeNull();
        expect($view->metadata)->toBeNull();
    });

    test('stores document details correctly', function () {
        $user = User::factory()->create();

        $view = DocumentView::factory()->create([
            'user_id' => $user->id,
            'file_key' => 'file-abc-123',
            'pr_number' => 'PR-2024-001-0001',
            'procurement_title' => 'Supply of Office Equipment',
            'document_type' => 'bidding_documents',
            'stage' => 'submission_evaluation',
        ]);

        expect($view->file_key)->toBe('file-abc-123');
        expect($view->pr_number)->toBe('PR-2024-001-0001');
        expect($view->procurement_title)->toBe('Supply of Office Equipment');
        expect($view->document_type)->toBe('bidding_documents');
        expect($view->stage)->toBe('submission_evaluation');
    });
});

describe('DocumentView Model - Query Scenarios', function () {
    test('can filter by pr_number', function () {
        $pr_number = 'PR-2025-996-0002';

        DocumentView::factory()->count(3)->create([
            'pr_number' => $pr_number,
        ]);

        DocumentView::factory()->count(2)->create([
            'pr_number' => 'PR-2025-400-0001',
        ]);

        $views = DocumentView::where('pr_number', $pr_number)->get();

        expect($views)->toHaveCount(3);
    });

    test('can filter by document_type', function () {
        DocumentView::factory()->count(4)->create([
            'document_type' => 'bidding_documents',
        ]);

        DocumentView::factory()->count(2)->create([
            'document_type' => 'procurement_plan',
        ]);

        $views = DocumentView::where('document_type', 'bidding_documents')->get();

        expect($views)->toHaveCount(4);
    });

    test('can filter by stage', function () {
        DocumentView::factory()->count(3)->create([
            'stage' => 'submission_evaluation',
        ]);

        DocumentView::factory()->count(2)->create([
            'stage' => 'procurement_initiation',
        ]);

        $views = DocumentView::where('stage', 'submission_evaluation')->get();

        expect($views)->toHaveCount(3);
    });

    test('can filter by date range', function () {
        DocumentView::factory()->count(3)->create([
            'viewed_at' => now()->subDays(2),
        ]);

        DocumentView::factory()->count(2)->create([
            'viewed_at' => now()->subDays(10),
        ]);

        $views = DocumentView::where('viewed_at', '>=', now()->subDays(5))->get();

        expect($views)->toHaveCount(3);
    });

    test('can get views by user', function () {
        $user = User::factory()->create();

        DocumentView::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);

        DocumentView::factory()->count(3)->create();

        $userViews = DocumentView::where('user_id', $user->id)->get();

        expect($userViews)->toHaveCount(5);
    });

    test('can track multiple views of same file by same user', function () {
        $user = User::factory()->create();
        $fileKey = 'file-multi-view';

        DocumentView::factory()->count(3)->create([
            'user_id' => $user->id,
            'file_key' => $fileKey,
        ]);

        $views = DocumentView::where('user_id', $user->id)
            ->where('file_key', $fileKey)
            ->get();

        expect($views)->toHaveCount(3);
    });
});
