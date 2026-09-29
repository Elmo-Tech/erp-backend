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
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('requested_amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->decimal('disbursed_amount', 14, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'disbursed', 'settled'])
                ->default('pending');
            $table->text('reason');
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->foreignId('policy_version_id')->nullable()->constrained('hr_policies')->nullOnDelete();
            $table->json('policy_snapshot')->nullable();
            $table->decimal('salary_at_approval', 14, 2)->nullable();
            $table->decimal('advance_limit_amount', 14, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_reference', 255)->nullable();
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
            $table->index(['employee_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('employee_advance_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_advance_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_on');
            $table->decimal('scheduled_amount', 14, 2);
            $table->enum('status', ['scheduled', 'settled'])->default('scheduled');
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->string('settlement_reference', 255)->nullable();
            $table->foreignId('salary_adjustment_id')->nullable()->unique()
                ->constrained('salary_adjustment_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_advance_id', 'sequence']);
            $table->index(['employee_advance_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_settlements');
        Schema::dropIfExists('employee_advances');
    }
};
