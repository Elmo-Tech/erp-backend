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
     * Creates polymorphic activities table for tracking activities across different entities
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_type');
            $table->index(['subject_id', 'subject_type']);

            // Activity details
            $table->string('type'); // meeting, call, email, note, technical_discovery
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('outcome_summary')->nullable(); // For meeting minutes
            $table->boolean('is_completed')->default(false);

            // Assignment and scheduling
            $table->foreignId('assigned_to')->constrained('users');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Audit columns
            $this->addAuditColumns($table);
            $table->timestamps();

            // Indexes
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['is_completed']);
            $table->index(['scheduled_at']);
            $table->index(['assigned_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
