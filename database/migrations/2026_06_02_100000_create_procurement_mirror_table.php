<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores mirrored blockchain data from MultiChain streams,
     * with integrity verification and breach detection support.
     */
    public function up(): void
    {
        Schema::create('procurement_mirror', function (Blueprint $table) {
            $table->id();
            $table->string('stream', 100);
            $table->string('stream_key', 255);
            $table->string('txid', 128);
            $table->string('publisher_address', 128);
            $table->timestamp('blocktime')->nullable();
            $table->json('data_json');
            $table->string('data_hash', 64);
            $table->boolean('is_authorized')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('breach_detected_at')->nullable();
            $table->string('breach_type', 100)->nullable();
            $table->json('breach_data')->nullable();
            $table->timestamp('repaired_at')->nullable();
            $table->timestamp('synced_at');

            $table->unique(['stream', 'stream_key', 'txid']);
            $table->index('stream_key');
            $table->index('txid');
            $table->index('breach_detected_at');
            $table->index('publisher_address');
            $table->index('is_authorized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_mirror');
    }
};
