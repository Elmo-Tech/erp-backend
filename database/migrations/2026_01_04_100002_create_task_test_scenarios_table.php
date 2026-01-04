<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     * 
     * Test scenarios for tasks:
     * - Developer marks as completed
     * - Reviewer verifies and approves/rejects
     * - Affects points system
     */
    public function up(): void
    {
        Schema::create('task_test_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('expected_result')->nullable();
            $table->enum('status', ['pending', 'passed', 'failed', 'skipped'])->default('pending');
            $table->unsignedInteger('order')->default(0);
            
            // Developer completion
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            
            // Reviewer verification
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['task_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_test_scenarios');
    }
};
