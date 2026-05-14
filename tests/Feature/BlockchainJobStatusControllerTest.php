<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->givePermissionTo('view blockchain transactions');
    $this->otherUser = User::factory()->create();
    $this->otherUser->givePermissionTo('view blockchain transactions');
});

it('returns pending for unknown blockchain jobs', function () {
    actingAs($this->owner);

    getJson(route('blockchain.job.status', ['jobId' => 'missing-job']))
        ->assertStatus(202)
        ->assertJson(['status' => 'pending']);
});

it('returns the cached blockchain job status to the submitting user', function () {
    Cache::put('blockchain_job:job-owned', [
        'status' => 'done',
        'result' => ['txid' => 'abc123'],
        'user_id' => $this->owner->id,
    ], now()->addHour());

    actingAs($this->owner);

    getJson(route('blockchain.job.status', ['jobId' => 'job-owned']))
        ->assertOk()
        ->assertJson([
            'status' => 'done',
            'result' => ['txid' => 'abc123'],
            'user_id' => $this->owner->id,
        ]);
});

it('forbids blockchain job status access for a different authenticated user', function () {
    Cache::put('blockchain_job:job-owned', [
        'status' => 'failed',
        'error' => 'Nope',
        'user_id' => $this->owner->id,
    ], now()->addHour());

    actingAs($this->otherUser);

    getJson(route('blockchain.job.status', ['jobId' => 'job-owned']))
        ->assertForbidden();
});
