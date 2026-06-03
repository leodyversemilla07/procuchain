<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop procurement_records table
 *
 * This table is no longer needed. Data is now stored in normalized tables:
 * - procurements (from procurement.metadata)
 * - procurement_stages (from procurement.status)
 * - procurement_documents (from procurement.documents)
 * - procurement_events (from procurement.events)
 * - procurement_corrections (from procurement.corrections)
 * - files (from file.metadata)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('procurement_records');
    }

    public function down(): void
    {
        // Recreate procurement_records if needed
        Schema::create('procurement_records', function (Blueprint $table) {
            $table->id();
            $table->string('stream', 100);
            $table->string('stream_key', 255);
            $table->string('txid', 128);
            $table->unsignedInteger('revision_number')->default(1);
            $table->string('parent_txid', 128)->nullable();
            $table->boolean('is_latest_revision')->default(true);
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
            $table->timestamps();

            $table->unique(['stream', 'stream_key', 'txid']);
            $table->index('stream_key');
            $table->index('txid');
        });
    }
};
