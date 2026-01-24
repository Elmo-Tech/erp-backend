<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\CreatedUpdatedByMigration;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->enum('status', ['pending', 'submitted', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->text('submission_notes')->nullable(); // Developer's submission notes
            $table->text('review_notes')->nullable(); // Reviewer's notes
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->integer('points_awarded')->default(0); // Points given for this deliverable
            $table->boolean('is_bonus')->default(false); // Extra credit deliverable
            $table->integer('order')->default(0); // Display order
            $table->timestamps();
            
            // Add audit columns
            $this->addCreatedUpdatedByColumns($table);
            
            // Indexes
            $table->index(['task_id', 'status']);
            $table->index(['task_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_deliverables');
    }
};