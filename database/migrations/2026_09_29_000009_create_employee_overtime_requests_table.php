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
        Schema::create('employee_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('overtime_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('requested_minutes');
            $table->unsignedSmallInteger('actual_minutes')->nullable();
            $table->unsignedSmallInteger('approved_minutes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('reason');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('policy_version_id')->nullable()->constrained('hr_policies')->nullOnDelete();
            $table->decimal('hourly_rate', 14, 4)->nullable();
            $table->decimal('overtime_multiplier', 8, 4)->nullable();
            $table->decimal('payable_amount', 14, 2)->nullable();
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->index(['employee_id', 'overtime_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_overtime_requests');
    }
};
