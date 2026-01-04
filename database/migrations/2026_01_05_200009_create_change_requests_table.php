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
     */
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('cr_number', 50)->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('impact_analysis')->nullable();
            
            // Cost
            $table->boolean('is_billable')->default(true);
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->decimal('approved_cost', 15, 2)->nullable();
            
            // Timeline impact
            $table->integer('additional_days')->default(0);
            
            // Status
            $table->string('status')->default('pending'); // pending, under_review, approved, rejected, implemented
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('is_billable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
