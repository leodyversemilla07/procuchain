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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google2fa_secret')->nullable()->after('locked_reason');
            $table->boolean('mfa_enabled')->default(false)->after('google2fa_secret');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_enabled');
            $table->json('backup_codes')->nullable()->after('mfa_enabled_at');
            $table->timestamp('backup_codes_generated_at')->nullable()->after('backup_codes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google2fa_secret',
                'mfa_enabled',
                'mfa_enabled_at',
                'backup_codes',
                'backup_codes_generated_at'
            ]);
        });
    }
};
