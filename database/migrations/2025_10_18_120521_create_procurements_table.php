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
        Schema::create('procurements', function (Blueprint $table) {
            $table->string('id')->primary(); // procurement_id as primary key (e.g., PR-2025-0001-0001)
            $table->string('title'); // procurement_title
            $table->string('stage')->nullable(); // current stage
            $table->string('current_status')->nullable(); // Status enum value
            $table->string('user_address')->nullable(); // blockchain user address
            $table->integer('document_count')->default(0); // count of associated documents
            $table->timestamp('last_updated')->nullable(); // last update timestamp
            $table->string('blockchain_txid')->nullable()->comment('Blockchain transaction ID');
            
            // Blockchain status tracking fields
            $table->enum('blockchain_status', ['pending', 'confirmed', 'failed'])
                ->default('pending')
                ->comment('Status of blockchain publication');
            $table->timestamp('blockchain_status_updated_at')
                ->nullable()
                ->comment('When the blockchain status was last updated');
            $table->text('blockchain_error')
                ->nullable()
                ->comment('Error message if blockchain publication failed');
            $table->unsignedTinyInteger('blockchain_retry_count')
                ->default(0)
                ->comment('Number of times blockchain publication was retried');
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('current_status');
            $table->index('stage');
            $table->index('last_updated');
            $table->index('blockchain_status', 'idx_procurements_blockchain_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
