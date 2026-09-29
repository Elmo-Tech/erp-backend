<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    public function up(): void
    {
        Schema::create('employee_late_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('policy_version_id')->nullable()->constrained('hr_policies')->nullOnDelete();
            $table->json('policy_snapshot');
            $table->date('attendance_date');
            $table->unsignedInteger('original_late_minutes');
            $table->unsignedInteger('eligible_late_minutes');
            $table->unsignedSmallInteger('occurrence_number')->nullable();
            $table->unsignedInteger('tier_minimum_minutes')->nullable();
            $table->unsignedInteger('tier_maximum_minutes')->nullable();
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->decimal('calculated_amount', 12, 2)->default(0);
            $table->decimal('override_amount', 12, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->enum('status', ['calculated', 'waived', 'overridden'])->default('calculated');
            $table->text('waiver_reason')->nullable();
            $table->foreignId('salary_adjustment_id')->nullable()->unique()
                ->constrained('salary_adjustment_transactions')->nullOnDelete();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->index(['employee_id', 'attendance_date']);
            $table->index(['employee_id', 'status', 'attendance_date']);
            $table->index('policy_version_id');
        });

        Schema::table('salary_adjustment_transactions', function (Blueprint $table) {
            $table->unique(['reference_type', 'reference_id'], 'salary_adjustment_transactions_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_late_deductions');

        Schema::table('salary_adjustment_transactions', function (Blueprint $table) {
            $table->dropUnique('salary_adjustment_transactions_reference_unique');
        });
    }
};
