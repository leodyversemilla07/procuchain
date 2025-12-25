<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores document requirements for each stage/mode combination.
     * Allows admins to customize required and optional documents.
     */
    public function up(): void
    {
        Schema::create('stage_document_configs', function (Blueprint $table) {
            $table->id();
            $table->string('stage');
            $table->string('procurement_mode');
            $table->string('stage_display_name');
            $table->json('required_documents');
            $table->json('optional_documents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['stage', 'procurement_mode']);
            $table->index(['stage', 'is_active']);
            $table->index(['procurement_mode', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_document_configs');
    }
};
