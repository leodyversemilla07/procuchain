<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProcuChain Database Schema - Final Design
 *
 * Architecture:
 * - Normalized operational tables for fast queries
 * - Each table has blockchain columns for integrity verification
 * - integrity_audit_logs for violation tracking (append-only)
 *
 * Satisfies:
 * 1. Detect unauthorized modifications (data_hash vs blockchain_hash)
 * 2. Detect deleted records (blockchain PRs vs DB PRs)
 * 3. Compare DB vs blockchain (fetch via txid)
 * 4. Generate violation reports (query integrity_audit_logs)
 * 5. Restore from blockchain (fetch via txid, update tables)
 * 6. Audit trail of recovery (append-only integrity_audit_logs)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // SAFETY: Check if schema already exists
        // ═══════════════════════════════════════════════════════════════
        if (Schema::hasTable('procurements')) {
            return; // Already migrated
        }

        // ═══════════════════════════════════════════════════════════════
        // CORE PROCUREMENT TABLES
        // ═══════════════════════════════════════════════════════════════

        // ─── PROCUREMENTS ────────────────────────────────────────────
        // Main procurement record - PR-level data
        // Source: procurement.metadata stream
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();

            // Primary identifiers
            $table->string('pr_number', 50)->unique();
            $table->string('app_reference', 100)->nullable();
            $table->string('title', 500);

            // Classification
            $table->text('description')->nullable();
            $table->string('category', 50); // goods, services, infrastructure
            $table->string('procurement_mode', 100); // competitive_bidding, etc.
            $table->string('office', 255)->nullable();
            $table->string('end_user', 255)->nullable();
            $table->string('fund_source', 100)->nullable();
            $table->string('prepared_by', 255)->nullable();

            // Financial
            $table->decimal('abc_amount', 15, 2)->default(0);
            $table->decimal('approved_budget', 15, 2)->nullable();
            $table->decimal('contract_price', 15, 2)->nullable();

            // Delivery
            $table->string('delivery_location', 500)->nullable();
            $table->date('delivery_date')->nullable();
            $table->unsignedInteger('delivery_term_days')->nullable();

            // PhilGEPS
            $table->string('philgeps_reference', 100)->nullable();
            $table->date('philgeps_posting_date')->nullable();

            // BAC Resolution
            $table->string('bac_resolution_number', 100)->nullable();
            $table->date('bac_resolution_date')->nullable();

            // Approval (Notice of Award)
            $table->string('approved_by', 255)->nullable();
            $table->date('approval_date')->nullable();

            // Current state (denormalized for fast queries)
            $table->string('current_stage', 100)->nullable();
            $table->string('current_status', 100)->nullable();
            $table->string('previous_status', 100)->nullable();
            $table->unsignedInteger('stage_progress')->default(0);
            $table->unsignedInteger('documents_count')->default(0);

            // Key dates
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable()->comment('Blockchain transaction ID');
            $table->string('data_hash', 64)->nullable()->comment('Computed hash of this record');
            $table->string('blockchain_hash', 64)->nullable()->comment('Original hash from blockchain (immutable)');
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);
            $table->string('user_address', 128)->nullable();

            // Status flags
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('current_stage');
            $table->index('current_status');
            $table->index('procurement_mode');
            $table->index('category');
            $table->index('is_active');
            $table->index('has_breach');
            $table->index('txid');
            $table->index('last_verified_at');
        });

        // ─── PROCUREMENT STAGES ──────────────────────────────────────
        // Stage progression history
        // Source: procurement.status stream
        Schema::create('procurement_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();

            // Stage info
            $table->string('stage', 100);
            $table->string('status', 100);
            $table->string('previous_status', 100)->nullable();

            // Timing
            $table->timestamp('entered_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_hours')->nullable();

            // User tracking
            $table->string('user_address', 128)->nullable();
            $table->string('user_name', 255)->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['procurement_id', 'stage']);
            $table->index(['procurement_id', 'entered_at']);
            $table->index('txid');
            $table->index('has_breach');
        });

        // ─── PROCUREMENT DOCUMENTS ───────────────────────────────────
        // Document metadata
        // Source: procurement.documents stream
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();

            // Document info
            $table->string('document_type', 100);
            $table->string('stage', 100);
            $table->string('filename', 500);
            $table->string('file_key', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            // Content
            $table->string('hash', 64);
            $table->text('description')->nullable();

            // User tracking
            $table->string('uploaded_by', 255);
            $table->string('user_address', 128)->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('uploaded_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['procurement_id', 'document_type']);
            $table->index(['procurement_id', 'stage']);
            $table->index('file_key');
            $table->index('txid');
            $table->index('has_breach');
        });

        // ─── PROCUREMENT EVENTS ──────────────────────────────────────
        // Audit trail events
        // Source: procurement.events stream
        Schema::create('procurement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();

            // Event info
            $table->string('event_type', 100);
            $table->string('category', 50);
            $table->string('severity', 20); // info, warning, error
            $table->text('details');

            // Context
            $table->string('stage', 100);
            $table->unsignedInteger('document_count')->default(0);

            // User tracking
            $table->string('user_address', 128)->nullable();
            $table->string('user_name', 255)->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->json('metadata')->nullable();

            // Timing
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['procurement_id', 'event_type']);
            $table->index(['procurement_id', 'occurred_at']);
            $table->index(['procurement_id', 'stage']);
            $table->index('txid');
            $table->index('has_breach');
        });

        // ─── PROCUREMENT CORRECTIONS ─────────────────────────────────
        // Correction records
        // Source: procurement.corrections stream
        Schema::create('procurement_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();

            // Correction info
            $table->string('correction_type', 100);
            $table->string('action', 50); // amend, correct, update
            $table->text('reason');

            // Original reference
            $table->string('original_txid', 128);
            $table->string('original_document_hash', 64);

            // User tracking
            $table->string('corrected_by', 255);
            $table->string('user_address', 128)->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->json('corrected_metadata')->nullable();

            $table->timestamp('corrected_at');
            $table->timestamps();

            $table->index(['procurement_id', 'correction_type']);
            $table->index(['procurement_id', 'corrected_at']);
            $table->index('txid');
            $table->index('original_txid');
            $table->index('has_breach');
        });

        // ─── FILES ───────────────────────────────────────────────────
        // File storage metadata
        // Source: file.metadata stream
        Schema::create('files', function (Blueprint $table) {
            $table->id();

            // File info
            $table->string('file_key', 255)->unique();
            $table->string('filename', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('hash', 64);

            // Storage
            $table->string('storage_method', 50); // blockchain, local, s3

            // Blockchain references
            $table->string('data_txid', 128)->nullable();
            $table->string('data_key', 255)->nullable();

            // Procurement link
            $table->string('pr_number', 50)->nullable();
            $table->string('stage', 100)->nullable();
            $table->string('document_type', 100)->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->json('additional_metadata')->nullable();

            $table->timestamp('stored_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('pr_number');
            $table->index('txid');
            $table->index('hash');
            $table->index('has_breach');
        });

        // ═══════════════════════════════════════════════════════════════
        // INTEGRITY & AUDIT TABLES
        // ═══════════════════════════════════════════════════════════════

        // ─── INTEGRITY AUDIT LOGS ────────────────────────────────────
        // Append-only violation tracking
        // Source: integrity.violations stream
        // NOTE: May already exist from previous migration
        if (! Schema::hasTable('integrity_audit_logs')) {
            Schema::create('integrity_audit_logs', function (Blueprint $table) {
                $table->id();

                // What was checked
                $table->unsignedBigInteger('record_id')->nullable()->comment('Which record');
                $table->string('stream', 100);
                $table->string('stream_key', 255);
                $table->string('txid', 128)->nullable();

                // What happened
                $table->string('violation_type', 100); // hash_mismatch, deleted, unauthorized, etc.
                $table->string('severity', 20)->default('medium'); // critical, high, medium, low

                // Snapshots for comparison
                $table->json('database_snapshot')->nullable(); // DB state at detection
                $table->json('blockchain_snapshot')->nullable(); // Blockchain state at detection
                $table->json('field_differences')->nullable(); // [{field, old_value, new_value}]

                // Recovery tracking
                $table->string('recovery_status', 20)->default('pending'); // pending, restored, failed, skipped
                $table->timestamp('recovered_at')->nullable();
                $table->json('recovery_result')->nullable(); // what was restored + outcome

                // Context
                $table->string('verification_run_id', 36)->nullable();
                $table->string('source', 50)->default('scheduled'); // scheduled, manual, read_time
                $table->string('detected_by', 100)->nullable(); // system, user

                // Append-only: no updated_at
                $table->timestamp('created_at');

                // Indexes
                $table->index('record_id');
                $table->index('stream_key');
                $table->index('violation_type');
                $table->index('severity');
                $table->index('recovery_status');
                $table->index('verification_run_id');
                $table->index('created_at');
                $table->index('txid');
            });
        } // end if integrity_audit_logs

        // ─── BLOCKCHAIN AUDIT TRAIL ──────────────────────────────────
        // Immutable audit trail
        // Source: audit.trail stream
        Schema::create('blockchain_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Action info
            $table->string('action', 100); // create, update, delete
            $table->string('subject_type', 100); // model class
            $table->string('subject_id', 100); // model ID
            $table->string('pr_number', 50)->nullable();

            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // User context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['pr_number', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index('txid');
            $table->index('action');
            $table->index('has_breach');
        });

        // ─── USER SESSIONS ───────────────────────────────────────────
        // Login/logout tracking
        // Source: user.login_sessions stream
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Session info
            $table->string('session_id', 100)->nullable();
            $table->string('action', 50); // login, logout
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('location', 255)->nullable();

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index('txid');
            $table->index('ip_address');
            $table->index('has_breach');
        });

        // ─── DOCUMENT ACCESS ─────────────────────────────────────────
        // Document access tracking
        // Source: document.access stream
        Schema::create('document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Access info
            $table->string('file_key', 255);
            $table->string('pr_number', 50);
            $table->string('document_type', 100)->nullable();
            $table->string('stage', 100)->nullable();

            $table->string('action', 50); // view, download, print
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('view_duration')->nullable(); // seconds

            // ═════════════════════════════════════════════════════════
            // BLOCKCHAIN INTEGRITY COLUMNS
            // ═════════════════════════════════════════════════════════
            $table->string('txid', 128)->nullable();
            $table->string('data_hash', 64)->nullable();
            $table->string('blockchain_hash', 64)->nullable();
            $table->boolean('is_blockchain_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('has_breach')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['user_id', 'accessed_at']);
            $table->index(['file_key', 'accessed_at']);
            $table->index(['pr_number', 'accessed_at']);
            $table->index('txid');
            $table->index('has_breach');
        });

        // ═══════════════════════════════════════════════════════════════
        // CONFIGURATION TABLES
        // ═══════════════════════════════════════════════════════════════

        // ─── WORKFLOW CONFIGS ────────────────────────────────────────
        // NOTE: May already exist from previous migration
        if (! Schema::hasTable('procurement_workflow_configs')) {
            Schema::create('procurement_workflow_configs', function (Blueprint $table) {
                $table->id();
                $table->string('procurement_mode')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->json('stages');
                $table->json('optional_stages')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                // Blockchain integrity
                $table->string('txid', 128)->nullable();
                $table->string('blockchain_hash', 64)->nullable();

                $table->timestamps();

                $table->index('is_active');
            });
        } // end if procurement_workflow_configs

        // ─── STAGE DOCUMENT CONFIGS ──────────────────────────────────
        // NOTE: May already exist from previous migration
        if (! Schema::hasTable('stage_document_configs')) {
            Schema::create('stage_document_configs', function (Blueprint $table) {
                $table->id();
                $table->string('stage');
                $table->string('procurement_mode');
                $table->string('stage_display_name');
                $table->json('required_documents');
                $table->json('optional_documents')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                // Blockchain integrity
                $table->string('txid', 128)->nullable();
                $table->string('blockchain_hash', 64)->nullable();

                $table->timestamps();

                $table->unique(['stage', 'procurement_mode']);
                $table->index(['stage', 'is_active']);
                $table->index(['procurement_mode', 'is_active']);
            });
        } // end if stage_document_configs
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_document_configs');
        Schema::dropIfExists('procurement_workflow_configs');
        Schema::dropIfExists('document_access');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('blockchain_audit_trail');
        Schema::dropIfExists('integrity_audit_logs');
        Schema::dropIfExists('files');
        Schema::dropIfExists('procurement_corrections');
        Schema::dropIfExists('procurement_events');
        Schema::dropIfExists('procurement_documents');
        Schema::dropIfExists('procurement_stages');
        Schema::dropIfExists('procurements');
    }
};
