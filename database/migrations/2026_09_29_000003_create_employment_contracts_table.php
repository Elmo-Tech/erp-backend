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
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('employment_type', ['full_time', 'part_time']);
            $table->decimal('base_salary', 12, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('duration_months');
            $table->json('work_days');
            $table->decimal('work_hours_per_day', 4, 2);
            $table->time('work_start_time');
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'superseded']);
            $table->text('notes')->nullable();
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->index(['employee_id', 'status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
