<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Employee-specific fields for HR & Attendance management:
     * - salary: Monthly salary
     * - work_hours_per_day: Expected work hours (default 8)
     * - work_start_time: Expected clock-in time (e.g., 10:00)
     * - grace_days_remaining: Days allowed to be late > 30 mins (default 5)
     * - grace_days_used: Days already used this month
     * - is_employee: Distinguishes employees from client accounts
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Employee identification
            $table->boolean('is_employee')->default(false)->after('is_active');

            // Salary & Work Schedule
            $table->decimal('salary', 10, 2)->nullable()->after('is_employee');
            $table->unsignedTinyInteger('work_hours_per_day')->default(8)->after('salary');
            $table->time('work_start_time')->default('10:00:00')->after('work_hours_per_day');

            // Grace period for lateness (5 days per month where > 30 min late is forgiven)
            $table->unsignedTinyInteger('grace_days_allowed')->default(5)->after('work_start_time');
            $table->unsignedTinyInteger('grace_days_used')->default(0)->after('grace_days_allowed');
            $table->date('grace_days_reset_date')->nullable()->after('grace_days_used');

            // Performance tracking
            $table->integer('total_points')->default(0)->after('grace_days_reset_date');
            $table->decimal('total_deductions', 10, 2)->default(0)->after('total_points');

            // Index for employee queries
            $table->index('is_employee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_employee']);
            $table->dropColumn([
                'is_employee',
                'salary',
                'work_hours_per_day',
                'work_start_time',
                'grace_days_allowed',
                'grace_days_used',
                'grace_days_reset_date',
                'total_points',
                'total_deductions',
            ]);
        });
    }
};
