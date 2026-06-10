<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename mirror_id → record_id in integrity_audit_logs.
     *
     * The column references procurement_records (formerly procurement_mirror).
     * "record_id" is the correct name after the table rename.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('integrity_audit_logs', 'mirror_id')) {
            return;
        }

        Schema::table('integrity_audit_logs', function (Blueprint $table) {
            $table->renameColumn('mirror_id', 'record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrity_audit_logs', function (Blueprint $table) {
            $table->renameColumn('record_id', 'mirror_id');
        });
    }
};
