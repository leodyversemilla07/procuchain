<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Permanent audit trail for all integrity verification results,
     * detected violations, and recovery operations. This table is
     * append-only — records are never updated or deleted.
     *
     * Separated from procurement_mirror to survive mirror row deletion
     * and provide forensic-level accountability (RA 12009 Sec. 3, Sec. 20).
     */
    public function up(): void
    {
        Schema::create('integrity_audit_logs', function (Blueprint $table) {
            $table->id();

            // What was checked
            $table->string('stream', 100);
            $table->string('stream_key', 255);
            $table->string('txid', 128)->nullable();

            // What happened
            $table->string('violation_type', 100); // BreachTypeEnums value
            $table->string('severity', 20)->default('medium'); // critical, high, medium, low

            // Field-level change detection
            $table->json('field_differences')->nullable(); // [{field, old_value, new_value}]
            $table->json('mirror_snapshot')->nullable(); // DB state at detection time
            $table->json('chain_snapshot')->nullable(); // Blockchain state at detection time

            // Recovery tracking
            $table->string('recovery_status', 20)->default('pending'); // pending, restored, failed, skipped
            $table->timestamp('recovered_at')->nullable();
            $table->json('recovery_result')->nullable(); // what was restored + outcome

            // Context
            $table->unsignedBigInteger('mirror_id')->nullable(); // FK to procurement_mirror (may be null if row deleted)
            $table->string('verification_run_id', 36)->nullable(); // groups violations from same audit run
            $table->string('source', 50)->default('scheduled'); // scheduled, manual, read_time

            // Append-only: no updated_at
            $table->timestamp('created_at');

            // Indexes for querying
            $table->index('stream_key');
            $table->index('violation_type');
            $table->index('severity');
            $table->index('recovery_status');
            $table->index('verification_run_id');
            $table->index('created_at');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrity_audit_logs');
    }
};
