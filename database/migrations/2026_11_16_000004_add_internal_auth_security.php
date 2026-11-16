<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->text('mfa_secret')->nullable();
            $table->unsignedBigInteger('mfa_last_step')->nullable();
        });

        Schema::create('global_role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role_key', 80);
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->unique(['user_id', 'role_key']);
        });

        Schema::create('user_mfa_recovery_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_recovery_codes');
        Schema::dropIfExists('global_role_assignments');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'mfa_secret', 'mfa_last_step']);
        });
    }
};
