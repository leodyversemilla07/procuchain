<?php

use App\Models\DocumentViewLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DocumentViewLog Model - Configuration', function () {
    test('has correct fillable fields', function () {
        $view = new DocumentViewLog;
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
        $view = DocumentViewLog::factory()->create([
            'metadata' => ['browser' => 'Chrome', 'device' => 'desktop'],
            'viewed_at' => now(),
        ]);

        expect($view->metadata)->toBeArray();
        expect($view->viewed_at)->toBeInstanceOf(Carbon::class);
    });

    test('timestamps are managed automatically', function () {
        $view = DocumentViewLog::factory()->create();

        expect($view->created_at)->toBeInstanceOf(Carbon::class);
        expect($view->updated_at)->toBeInstanceOf(Carbon::class);
    });
});

describe('DocumentViewLog Model - Relationships', function () {
    test('belongs to user', function () {
        $user = User::factory()->create();
        $view = DocumentViewLog::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($view->user)->toBeInstanceOf(User::class);
        expect($view->user->id)->toBe($user->id);
    });

    test('can eager load user relationship', function () {
        $user = User::factory()->create();
        $view = DocumentViewLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $loadedView = DocumentViewLog::with('user')->find($view->id);

        expect($loadedView->relationLoaded('user'))->toBeTrue();
        expect($loadedView->user)->not->toBeNull();
    });

    test('user can have multiple document views', function () {
        $user = User::factory()->create();

        DocumentViewLog::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);

        $views = DocumentViewLog::where('user_id', $user->id)->get();

        expect($views)->toHaveCount(5);
    });
});

describe('DocumentViewLog Model - Static Methods - Recent Views', function () {
    test('getRecentViewsForFile returns views for specific File', function () {
        $fileKey1 = 'File-abc-123';
        $fileKey2 = 'File-xyz-789';

        DocumentViewLog::factory()->count(3)->create([
            'file_key' => $fileKey1,
            'viewed_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'file_key' => $fileKey2,
            'viewed_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);

        $views = DocumentViewLog::getRecentViewsForFile($fileKey1);

        expect($views)->toHaveCount(3);
        expect($views->pluck('file_key')->unique()->first())->toBe($fileKey1);
    });

    test('getRecentViewsForFile respects limit parameter', function () {
        $fileKey = 'File-limit-test';

        DocumentViewLog::factory()->count(15)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subMinutes(fake()->numberBetween(1, 100)),
        ]);

        $views = DocumentViewLog::getRecentViewsForFile($fileKey, 5);

        expect($views)->toHaveCount(5);
    });

    test('getRecentViewsForFile orders by viewed_at descending', function () {
        $fileKey = 'File-order-test';

        $oldest = DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(3),
        ]);

        $newest = DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now(),
        ]);

        $middle = DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(1),
        ]);

        $views = DocumentViewLog::getRecentViewsForFile($fileKey);

        expect($views->first()->id)->toBe($newest->id);
        expect($views->last()->id)->toBe($oldest->id);
    });

    test('getRecentViewsForFile eager loads user relationship', function () {
        $user = User::factory()->create();
        $fileKey = 'File-eager-test';

        DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
            'user_id' => $user->id,
        ]);

        $views = DocumentViewLog::getRecentViewsForFile($fileKey);

        expect($views->first()->relationLoaded('user'))->toBeTrue();
    });
});

describe('DocumentViewLog Model - Static Methods - Procurement Stats', function () {
    test('getProcurementViewStats returns stats grouped by File', function () {
        $pr_number = 'PR-2025-996-0001';

        DocumentViewLog::factory()->count(3)->create([
            'pr_number' => $pr_number,
            'file_key' => 'File-1',
            'document_type' => 'procurement_plan',
            'stage' => 'procurement_initiation',
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'pr_number' => $pr_number,
            'file_key' => 'File-2',
            'document_type' => 'bidding_documents',
            'stage' => 'submission_evaluation',
        ]);

        $stats = DocumentViewLog::getProcurementViewStats($pr_number);

        expect($stats)->toHaveCount(2);
    });

    test('hasUserViewedFile returns true when user viewed File', function () {
        $user = User::factory()->create();
        $fileKey = 'File-viewed-test';

        DocumentViewLog::factory()->create([
            'user_id' => $user->id,
            'file_key' => $fileKey,
        ]);

        $hasViewed = DocumentViewLog::hasUserViewedFile($user->id, $fileKey);

        expect($hasViewed)->toBeTrue();
    });

    test('hasUserViewedFile returns false when user has not viewed File', function () {
        $user = User::factory()->create();
        $fileKey = 'File-not-viewed';

        $hasViewed = DocumentViewLog::hasUserViewedFile($user->id, $fileKey);

        expect($hasViewed)->toBeFalse();
    });
});

describe('DocumentViewLog Model - Static Methods - Most Viewed', function () {
    test('getMostViewedDocuments returns documents ordered by views', function () {
        DocumentViewLog::factory()->count(5)->create([
            'file_key' => 'popular-File',
            'document_type' => 'bidding_documents',
            'procurement_title' => 'Popular Procurement',
            'stage' => 'submission_evaluation',
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'file_key' => 'less-popular-File',
            'document_type' => 'procurement_plan',
            'procurement_title' => 'Less Popular Procurement',
            'stage' => 'procurement_initiation',
        ]);

        $mostViewed = DocumentViewLog::getMostViewedDocuments();

        expect($mostViewed->first()->file_key)->toBe('popular-File');
    });

    test('getMostViewedDocuments respects limit parameter', function () {
        for ($i = 1; $i <= 15; $i++) {
            DocumentViewLog::factory()->count($i)->create([
                'file_key' => "File-{$i}",
                'document_type' => 'test_doc',
                'procurement_title' => "Procurement {$i}",
                'stage' => 'test_stage',
            ]);
        }

        $mostViewed = DocumentViewLog::getMostViewedDocuments(5);

        expect($mostViewed)->toHaveCount(5);
    });
});

describe('DocumentViewLog Model - Static Methods - File Statistics', function () {
    test('getBlockchainFileStatistics returns total views', function () {
        $fileKey = 'File-total-views';

        DocumentViewLog::factory()->count(7)->create([
            'file_key' => $fileKey,
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

        expect($stats['total_views'])->toBe(7);
    });

    test('getBlockchainFileStatistics returns unique viewers', function () {
        $fileKey = 'File-unique-viewers';
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DocumentViewLog::factory()->count(3)->create([
            'file_key' => $fileKey,
            'user_id' => $user1->id,
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'file_key' => $fileKey,
            'user_id' => $user2->id,
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

        expect($stats['unique_viewers'])->toBe(2);
    });

    test('getBlockchainFileStatistics returns today views', function () {
        $fileKey = 'File-today-views';

        DocumentViewLog::factory()->count(4)->create([
            'file_key' => $fileKey,
            'viewed_at' => now(),
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(2),
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

        expect($stats['today_views'])->toBe(4);
    });

    test('getBlockchainFileStatistics returns week views', function () {
        $fileKey = 'File-week-views';

        DocumentViewLog::factory()->count(5)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(3),
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(10),
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

        expect($stats['week_views'])->toBe(5);
    });

    test('getBlockchainFileStatistics returns month views', function () {
        $fileKey = 'File-month-views';

        DocumentViewLog::factory()->count(6)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(15),
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(45),
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

        expect($stats['month_views'])->toBe(6);
    });

    test('getBlockchainFileStatistics returns first and last viewed dates', function () {
        $fileKey = 'File-dates-test';

        DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now()->subDays(10),
        ]);

        DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
            'viewed_at' => now(),
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

        expect($stats['first_viewed'])->not->toBeNull();
        expect($stats['last_viewed'])->not->toBeNull();
        expect($stats['first_viewed'])->toBeInstanceOf(Carbon::class);
        expect($stats['last_viewed'])->toBeInstanceOf(Carbon::class);
    });

    test('getBlockchainFileStatistics returns all expected keys', function () {
        $fileKey = 'File-keys-test';

        DocumentViewLog::factory()->create([
            'file_key' => $fileKey,
        ]);

        $stats = DocumentViewLog::getBlockchainFileStatistics($fileKey);

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

describe('DocumentViewLog Model - Metadata Operations', function () {
    test('stores metadata as array', function () {
        $metadata = [
            'browser' => 'Chrome',
            'device' => 'desktop',
            'screen_resolution' => '1920x1080',
        ];

        $view = DocumentViewLog::factory()->create([
            'metadata' => $metadata,
        ]);

        expect($view->metadata)->toBeArray();
        expect($view->metadata)->toBe($metadata);
    });

    test('handles empty metadata', function () {
        $view = DocumentViewLog::factory()->create([
            'metadata' => [],
        ]);

        expect($view->metadata)->toBeArray();
        expect($view->metadata)->toBeEmpty();
    });

    test('handles null metadata', function () {
        $view = DocumentViewLog::factory()->create([
            'metadata' => null,
        ]);

        expect($view->metadata)->toBeNull();
    });

    test('can update metadata', function () {
        $view = DocumentViewLog::factory()->create([
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

        $view = DocumentViewLog::factory()->create([
            'metadata' => $metadata,
        ]);

        expect($view->metadata['user_details'])->toBeArray();
        expect($view->metadata['user_details']['name'])->toBe('John Doe');
    });
});

describe('DocumentViewLog Model - Data Integrity', function () {
    test('requires user_id', function () {
        expect(fn () => DocumentViewLog::create([
            'file_key' => 'test-File',
            'pr_number' => 'PR-001',
            'viewed_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    test('requires file_key', function () {
        $user = User::factory()->create();

        expect(fn () => DocumentViewLog::create([
            'user_id' => $user->id,
            'pr_number' => 'PR-001',
            'viewed_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    test('can store nullable fields', function () {
        $user = User::factory()->create();

        $view = DocumentViewLog::factory()->create([
            'user_id' => $user->id,
            'view_duration' => null,
            'metadata' => null,
        ]);

        expect($view->view_duration)->toBeNull();
        expect($view->metadata)->toBeNull();
    });

    test('stores document details correctly', function () {
        $user = User::factory()->create();

        $view = DocumentViewLog::factory()->create([
            'user_id' => $user->id,
            'file_key' => 'File-abc-123',
            'pr_number' => 'PR-2024-001-0001',
            'procurement_title' => 'Supply of Office Equipment',
            'document_type' => 'bidding_documents',
            'stage' => 'submission_evaluation',
        ]);

        expect($view->file_key)->toBe('File-abc-123');
        expect($view->pr_number)->toBe('PR-2024-001-0001');
        expect($view->procurement_title)->toBe('Supply of Office Equipment');
        expect($view->document_type)->toBe('bidding_documents');
        expect($view->stage)->toBe('submission_evaluation');
    });
});

describe('DocumentViewLog Model - Query Scenarios', function () {
    test('can filter by pr_number', function () {
        $pr_number = 'PR-2025-996-0002';

        DocumentViewLog::factory()->count(3)->create([
            'pr_number' => $pr_number,
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'pr_number' => 'PR-2025-400-0001',
        ]);

        $views = DocumentViewLog::where('pr_number', $pr_number)->get();

        expect($views)->toHaveCount(3);
    });

    test('can filter by document_type', function () {
        DocumentViewLog::factory()->count(4)->create([
            'document_type' => 'bidding_documents',
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'document_type' => 'procurement_plan',
        ]);

        $views = DocumentViewLog::where('document_type', 'bidding_documents')->get();

        expect($views)->toHaveCount(4);
    });

    test('can filter by stage', function () {
        DocumentViewLog::factory()->count(3)->create([
            'stage' => 'submission_evaluation',
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'stage' => 'procurement_initiation',
        ]);

        $views = DocumentViewLog::where('stage', 'submission_evaluation')->get();

        expect($views)->toHaveCount(3);
    });

    test('can filter by date range', function () {
        DocumentViewLog::factory()->count(3)->create([
            'viewed_at' => now()->subDays(2),
        ]);

        DocumentViewLog::factory()->count(2)->create([
            'viewed_at' => now()->subDays(10),
        ]);

        $views = DocumentViewLog::where('viewed_at', '>=', now()->subDays(5))->get();

        expect($views)->toHaveCount(3);
    });

    test('can get views by user', function () {
        $user = User::factory()->create();

        DocumentViewLog::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);

        DocumentViewLog::factory()->count(3)->create();

        $userViews = DocumentViewLog::where('user_id', $user->id)->get();

        expect($userViews)->toHaveCount(5);
    });

    test('can track multiple views of same File by same user', function () {
        $user = User::factory()->create();
        $fileKey = 'File-multi-view';

        DocumentViewLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'file_key' => $fileKey,
        ]);

        $views = DocumentViewLog::where('user_id', $user->id)
            ->where('file_key', $fileKey)
            ->get();

        expect($views)->toHaveCount(3);
    });
});
