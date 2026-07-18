<?php

use App\Models\Procurement;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();
});

it('creates and finds a procurement record', function () {
    Procurement::create([
        'pr_number' => 'PR-2025-001-0001',
        'title' => 'Office Supplies Procurement',
        'description' => 'Purchase of office supplies',
        'abc_amount' => 150000,
        'category' => 'goods',
        'procurement_mode' => 'small_value_procurement',
        'office' => 'General Services',
        'current_status' => 'draft',
        'user_id' => '1',
        'initiated_at' => now(),
    ]);

    $procurement = Procurement::where('pr_number', 'PR-2025-001-0001')->first();

    expect($procurement)->not->toBeNull()
        ->and($procurement->title)->toBe('Office Supplies Procurement');
});

it('returns null when procurement not found', function () {
    $procurement = Procurement::where('pr_number', 'non-existent-id')->first();

    expect($procurement)->toBeNull();
});
