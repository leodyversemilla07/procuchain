<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores workflow configuration for each procurement mode.
     * Allows admins to customize which stages appear in each mode's workflow.
     */
    public function up(): void
    {
        Schema::create('procurement_workflow_configs', function (Blueprint $table) {
            $table->id();
            $table->string('procurement_mode')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('stages');
            $table->json('optional_stages')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_workflow_configs');
    }
};
