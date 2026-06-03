<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename procurement_mirror → procurement_records.
     *
     * The table stores procurement records backed by blockchain.
     * "mirror" was an implementation detail — the name should describe the data.
     */
    public function up(): void
    {
        Schema::rename('procurement_mirror', 'procurement_records');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('procurement_records', 'procurement_mirror');
    }
};
