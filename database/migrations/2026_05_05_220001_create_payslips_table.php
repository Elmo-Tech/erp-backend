<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll ledger — one row per employee per calendar month.
 *
 * The record is created as 'draft' by the generation command/endpoint and
 * can be promoted to 'approved' then 'paid' by HR admins.
 * Using updateOrCreate on (user_id, month, year) means the command is
 * safely re-runnable for draft payslips without creating duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('month');   // 1–12
            $table->unsignedSmallInteger('year');   // e.g. 2026

            // ── Salary components ─────────────────────────────────────────
            $table->decimal('base_salary', 12, 2)->default(0);

            // ── Gamification bonus ────────────────────────────────────────
            $table->integer('points_earned')->default(0);
            $table->decimal('points_bonus_amount', 12, 2)->default(0);

            // ── Attendance deduction ──────────────────────────────────────
            $table->integer('lateness_minutes')->default(0);
            $table->decimal('lateness_deduction_amount', 12, 2)->default(0);

            // ── Final figure ──────────────────────────────────────────────
            $table->decimal('net_salary', 12, 2)->default(0);

            // ── Lifecycle ─────────────────────────────────────────────────
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');

            $table->softDeletes();
            $table->timestamps();

            // Enforce one payslip per employee per month
            $table->unique(['user_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
