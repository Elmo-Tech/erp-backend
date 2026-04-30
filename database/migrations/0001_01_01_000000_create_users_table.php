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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->json('skills')->nullable();
            $table->string('specialization')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_employee')->default(true);
            $table->decimal('salary', 15, 2)->nullable();
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('bonus_base_amount', 15, 2)->default(0);
            $table->integer('work_hours_per_day')->nullable()->default(8);
            $table->string('work_start_time')->nullable()->default('09:00:00');
            $table->integer('grace_days_allowed')->nullable()->default(2);
            $table->integer('grace_days_used')->default(0);
            $table->date('grace_days_reset_date')->nullable();
            $table->integer('total_points')->default(0);
            $table->decimal('internal_hourly_cost', 10, 2)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
