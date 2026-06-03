<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add blockchain backing columns to all audit/compliance tables.
     *
     * Each table gains:
     * - txid: blockchain transaction ID (links MySQL row to chain)
     * - data_hash: SHA-256 of the row data (integrity verification)
     * - blockchain_synced_at: when the row was written to chain
     *
     * This makes every audit table recoverable from blockchain
     * after total MySQL destruction.
     */
    public function up(): void
    {
        $tables = [
            'audit_logs',
            'document_views',
            'procurement_workflow_configs',
            'stage_document_configs',
            'user_login_logs',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('txid', 128)->nullable()->after('id');
                $table->string('data_hash', 64)->nullable()->after('txid');
                $table->timestamp('blockchain_synced_at')->nullable()->after('data_hash');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'audit_logs',
            'document_views',
            'procurement_workflow_configs',
            'stage_document_configs',
            'user_login_logs',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['txid', 'data_hash', 'blockchain_synced_at']);
            });
        }
    }
};
