<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add revision tracking to procurement_mirror and integrity_audit_logs.
     *
     * Revision tracking enables hierarchical lineage for mirror records:
     * - Each publish to a stream key creates a new revision (incrementing revision_number)
     * - parent_txid links to the previous revision's txid, forming a revision chain
     * - is_latest_revision flags the most recent revision for fast lookups
     *
     * Audit logs gain revision_context to record which revision was current
     * when a violation was detected, and the full revision lineage path.
     */
    public function up(): void
    {
        Schema::table('procurement_mirror', function (Blueprint $table) {
            $table->unsignedInteger('revision_number')->default(1)->after('txid');
            $table->string('parent_txid', 128)->nullable()->after('revision_number');
            $table->boolean('is_latest_revision')->default(true)->after('parent_txid');

            $table->index(['stream', 'stream_key', 'revision_number'], 'mirror_revision_lookup');
            $table->index('is_latest_revision');
        });

        Schema::table('integrity_audit_logs', function (Blueprint $table) {
            // Revision context: which revision was current when the violation was detected
            $table->unsignedInteger('revision_number')->nullable()->after('txid');
            $table->string('parent_txid', 128)->nullable()->after('revision_number');

            // The full revision lineage at detection time (e.g. ["txid1", "txid2", "txid3"])
            $table->json('revision_lineage')->nullable()->after('parent_txid');

            $table->index('revision_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_mirror', function (Blueprint $table) {
            $table->dropIndex('mirror_revision_lookup');
            $table->dropIndex(['is_latest_revision']);
            $table->dropColumn(['revision_number', 'parent_txid', 'is_latest_revision']);
        });

        Schema::table('integrity_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['revision_number']);
            $table->dropColumn(['revision_number', 'parent_txid', 'revision_lineage']);
        });
    }
};
