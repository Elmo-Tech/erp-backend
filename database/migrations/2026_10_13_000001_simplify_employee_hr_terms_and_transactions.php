<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->decimal('required_monthly_hours', 8, 2)->nullable();
        });
        Schema::table('employee_advances', function (Blueprint $table): void {
            $table->boolean('is_salary_withdrawal')->default(false);
        });
        Schema::table('employee_deductions', function (Blueprint $table): void {
            $table->dateTime('occurred_at')->nullable();
        });
        Schema::table('performance_evaluations', function (Blueprint $table): void {
            $table->json('work_commitment_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employment_contracts', fn (Blueprint $table) => $table->dropColumn('required_monthly_hours'));
        Schema::table('employee_advances', fn (Blueprint $table) => $table->dropColumn('is_salary_withdrawal'));
        Schema::table('employee_deductions', fn (Blueprint $table) => $table->dropColumn('occurred_at'));
        Schema::table('performance_evaluations', fn (Blueprint $table) => $table->dropColumn('work_commitment_snapshot'));
    }
};
