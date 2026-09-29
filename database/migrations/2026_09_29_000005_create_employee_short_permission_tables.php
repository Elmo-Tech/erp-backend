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
        Schema::create('employee_permission_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedSmallInteger('allocated_minutes')->default(0);
            $table->unsignedSmallInteger('used_paid_minutes')->default(0);
            $table->foreignId('policy_version_id')->nullable()->constrained('hr_policies')->nullOnDelete();
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->unique(['employee_id', 'period']);
            $table->index(['employee_id', 'period']);
        });

        Schema::create('employee_short_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('permission_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_paid')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('policy_version_id')->nullable()->constrained('hr_policies')->nullOnDelete();
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->index(['employee_id', 'status', 'permission_date', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_short_permissions');
        Schema::dropIfExists('employee_permission_allowances');
    }
};
