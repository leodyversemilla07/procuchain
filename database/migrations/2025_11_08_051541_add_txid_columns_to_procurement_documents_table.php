<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->string('data_txid')->nullable()->after('blockchain_txid')->comment('Blockchain transaction ID for file data on file.data stream');
            $table->string('metadata_txid')->nullable()->after('data_txid')->comment('Blockchain transaction ID for file metadata on file.metadata stream');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->dropColumn(['data_txid', 'metadata_txid']);
        });
    }
};
