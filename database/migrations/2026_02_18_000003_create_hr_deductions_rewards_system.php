<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create HR Deductions and Rewards Tables
 *
 * This migration creates a comprehensive system for managing employee
 * deductions (lateness, absence, penalties) and rewards (bonuses, incentives).
 *
 * Features:
 * - Automatic deduction calculation for lateness/absence
 * - Manual deductions with approval workflow
 * - Performance-based rewards
 * - Project completion bonuses
 * - Innovation/suggestion rewards
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Deduction Types - Configuration for different deduction categories
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Lateness', 'Absence', 'Early Leave'
            $table->string('code')->unique(); // e.g., 'LATE', 'ABS', 'EARLY'
            $table->text('description')->nullable();
            $table->enum('calculation_type', ['fixed', 'per_occurrence', 'percentage', 'per_minute']);
            $table->decimal('amount', 10, 2)->default(0); // Fixed amount or percentage
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->unsignedInteger('grace_period_minutes')->default(0); // Grace period for lateness
            $table->json('rules')->nullable(); // Additional calculation rules
            $table->timestamps();
            $table->softDeletes();
        });

        // Employee Deductions - Individual deduction records
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained()->onDelete('restrict');
            $table->date('deduction_date');
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');

            // For automatic deductions (from attendance)
            $table->foreignId('attendance_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedInteger('late_minutes')->nullable();

            // Approval workflow
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Reference to related entity
            $table->morphs('deductible'); // Can reference attendance, task, project, etc.

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deduction_date']);
            $table->index(['status', 'deduction_date']);
        });

        // Reward Types - Configuration for different reward categories
        Schema::create('reward_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Performance Bonus', 'Project Completion', 'Innovation'
            $table->string('code')->unique(); // e.g., 'PERF_BONUS', 'PROJ_COMP', 'INNOV'
            $table->text('description')->nullable();
            $table->enum('calculation_type', ['fixed', 'percentage', 'points_based']);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->json('criteria')->nullable(); // Criteria for earning this reward
            $table->json('multipliers')->nullable(); // Performance multipliers
            $table->timestamps();
            $table->softDeletes();
        });

        // Employee Rewards - Individual reward records
        Schema::create('employee_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('reward_type_id')->constrained()->onDelete('restrict');
            $table->date('reward_date');
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending');

            // Points-based rewards
            $table->unsignedInteger('points_used')->nullable();

            // Reference to related entity
            $table->morphs('rewardable'); // Can reference project, task, evaluation, etc.

            // Approval workflow
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Payment tracking
            $table->date('paid_at')->nullable();
            $table->string('payment_reference')->nullable();

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'reward_date']);
            $table->index(['status', 'reward_date']);
        });

        // Deduction Rules - Automatic deduction configuration per user/role
        Schema::create('deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('deduction_type_id')->constrained()->onDelete('cascade');

            // Apply to specific user or all users
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // Time-based rules
            $table->time('work_start_time')->default('09:00:00');
            $table->unsignedInteger('grace_minutes')->default(15);

            // Deduction tiers
            $table->json('tiers')->nullable(); // e.g., [{'minutes': 15, 'amount': 10}, {'minutes': 30, 'amount': 25}]

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Salary Adjustments - Monthly summary of deductions and rewards
        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->unsignedTinyInteger('month'); // 1-12

            $table->decimal('base_salary', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('total_rewards', 10, 2)->default(0);
            $table->decimal('net_adjustment', 10, 2)->default(0);
            $table->decimal('final_salary', 10, 2)->default(0);

            $table->enum('status', ['draft', 'confirmed', 'paid'])->default('draft');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_adjustments');
        Schema::dropIfExists('deduction_rules');
        Schema::dropIfExists('employee_rewards');
        Schema::dropIfExists('reward_types');
        Schema::dropIfExists('employee_deductions');
        Schema::dropIfExists('deduction_types');
    }
};
