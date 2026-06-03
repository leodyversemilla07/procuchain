<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── PROCUREMENT ARCHIVES ────────────────────────────────────
        // Source: procurement.archive stream
        Schema::create('procurement_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();

            $table->string('action', 50); // archive, restore
            $table->text('reason')->nullable();
            $table->string('user_address', 128)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // Blockchain integrity
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->timestamp('archived_at');
            $table->timestamps();

            $table->index(['procurement_id', 'action']);
            $table->index('txid');
            $table->index('archived_at');
        });

        // ─── PROCUREMENT METADATA CORRECTIONS ────────────────────────
        // Source: procurement.metadata.corrections stream
        Schema::create('procurement_metadata_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();

            $table->string('correction_type', 100); // metadata, financial, dates, approval
            $table->text('reason');
            $table->string('corrected_by', 255);
            $table->string('user_address', 128)->nullable();

            // Original values (before correction)
            $table->string('original_title', 500)->nullable();
            $table->text('original_description')->nullable();
            $table->decimal('original_abc_amount', 15, 2)->nullable();
            $table->string('original_funding_source', 100)->nullable();
            $table->string('original_category', 50)->nullable();
            $table->string('original_procurement_mode', 100)->nullable();
            $table->string('original_office', 255)->nullable();
            $table->string('original_end_user', 255)->nullable();
            $table->date('original_delivery_date')->nullable();
            $table->string('original_bac_resolution_number', 100)->nullable();
            $table->date('original_bac_resolution_date')->nullable();
            $table->string('original_approved_by', 255)->nullable();
            $table->date('original_approval_date')->nullable();

            // Corrected values (after correction)
            $table->string('corrected_title', 500)->nullable();
            $table->text('corrected_description')->nullable();
            $table->decimal('corrected_abc_amount', 15, 2)->nullable();
            $table->string('corrected_funding_source', 100)->nullable();
            $table->string('corrected_category', 50)->nullable();
            $table->string('corrected_procurement_mode', 100)->nullable();
            $table->string('corrected_office', 255)->nullable();
            $table->string('corrected_end_user', 255)->nullable();
            $table->date('corrected_delivery_date')->nullable();
            $table->string('corrected_bac_resolution_number', 100)->nullable();
            $table->date('corrected_bac_resolution_date')->nullable();
            $table->string('corrected_approved_by', 255)->nullable();
            $table->date('corrected_approval_date')->nullable();

            // Blockchain integrity
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->timestamp('corrected_at');
            $table->timestamps();

            $table->index(['procurement_id', 'correction_type'], 'pmc_proc_correction');
            $table->index('txid');
            $table->index('corrected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_metadata_corrections');
        Schema::dropIfExists('procurement_archives');
    }
};
