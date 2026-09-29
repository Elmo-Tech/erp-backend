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
        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->date('terminated_on')->nullable()->after('end_date');
        });

        Schema::create('employee_partial_pays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employment_contract_id')->constrained('employment_contracts')->cascadeOnDelete();
            $table->enum('kind', ['first_month', 'termination']);
            $table->unsignedSmallInteger('payroll_year');
            $table->unsignedTinyInteger('payroll_month');
            $table->date('service_start_date');
            $table->date('service_end_date');
            $table->unsignedTinyInteger('payable_days');
            $table->decimal('base_salary_snapshot', 14, 2);
            $table->decimal('daily_rate_snapshot', 16, 6);
            $table->decimal('gross_amount', 14, 2);
            $table->foreignId('policy_version_id')->nullable()->constrained('hr_policies')->nullOnDelete();
            $table->json('policy_snapshot');
            $table->json('contract_work_days');
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $this->addAuditColumns($table);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('salary_adjustment_id')->nullable()->unique()
                ->constrained('salary_adjustment_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_year', 'payroll_month']);
            $table->index(['employee_id', 'payroll_year', 'payroll_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_partial_pays');

        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->dropColumn('terminated_on');
        });
    }
};
