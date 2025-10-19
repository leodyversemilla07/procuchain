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
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->id();
            $table->string('procurement_id'); // foreign key to procurements.id
            $table->string('file_key'); // storage file key/path
            $table->string('file_name'); // original filename
            $table->string('document_type')->nullable(); // e.g., 'Minutes', 'Attendance'
            $table->string('stage')->nullable(); // procurement stage
            $table->json('metadata')->nullable(); // additional document metadata
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

            // Document correction tracking fields
            $table->boolean('is_corrected')->default(false);
            $table->text('correction_reason')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->string('corrected_by')->nullable();
            $table->string('correction_txid')->nullable()->comment('Blockchain txid of correction record');

            $table->timestamps();

            // Indexes for better performance
            $table->index('procurement_id');
            $table->index('file_key');
            $table->index('stage');
            $table->index('created_at');
            $table->index(['procurement_id', 'blockchain_status'], 'idx_proc_docs_blockchain_status');
            $table->index('blockchain_status', 'idx_docs_blockchain_status');

            // Foreign key constraint
            $table->foreign('procurement_id')
                ->references('id')
                ->on('procurements')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_documents');
    }
};
