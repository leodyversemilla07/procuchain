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
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45); // Support both IPv4 and IPv6
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable(); // Desktop, Mobile, Tablet
            $table->string('browser')->nullable();
            $table->string('platform')->nullable(); // OS information
            $table->string('location')->nullable(); // City, Country (if using geolocation)
            $table->boolean('successful')->default(true); // Track failed attempts too
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['user_id', 'login_at']);
            $table->index('ip_address');
            $table->index('login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};
