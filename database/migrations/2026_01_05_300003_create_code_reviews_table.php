<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('developer_id')->constrained('users')->cascadeOnDelete();
            
            // Scores (1-5 scale)
            $table->tinyInteger('code_quality_score')->default(0); // Clean code, best practices
            $table->tinyInteger('readability_score')->default(0); // Easy to understand
            $table->tinyInteger('scalability_score')->default(0); // Future-proof, maintainable
            $table->tinyInteger('testing_score')->default(0); // Test coverage
            $table->tinyInteger('documentation_score')->default(0); // Comments, docs
            
            $table->decimal('average_score', 3, 2)->default(0); // Calculated average
            
            $table->text('review_notes')->nullable();
            $table->text('improvement_suggestions')->nullable();
            
            $table->enum('status', ['pending', 'approved', 'needs_revision', 'rejected'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();

            $table->index(['task_id', 'developer_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_reviews');
    }
};
