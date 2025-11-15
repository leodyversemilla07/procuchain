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
        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('file_key'); // The file key/path in storage
            $table->string('pr_number'); // Links to procurement
            $table->string('procurement_title')->nullable();
            $table->string('document_type')->nullable(); // e.g., 'Minutes', 'Attendance', etc.
            $table->string('stage')->nullable(); // e.g., 'Pre-Bid Conference'
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->integer('view_duration')->nullable(); // in seconds, if tracked
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamp('viewed_at');
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['user_id', 'file_key']);
            $table->index(['pr_number', 'viewed_at']);
            $table->index(['file_key', 'viewed_at']);
            $table->index('viewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_views');
    }
};
