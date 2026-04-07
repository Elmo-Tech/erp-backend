<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_period_id')->constrained()->cascadeOnDelete();

            // Technical Quality (30 points max)
            $table->decimal('bug_severity_score', 4, 2)->default(0); // 0-15
            $table->decimal('code_quality_score', 4, 2)->default(0); // 0-15
            $table->decimal('technical_quality_total', 4, 2)->default(0); // 0-30

            // Innovation & Tools (20 points max)
            $table->decimal('innovation_score', 4, 2)->default(0); // 0-20

            // Productivity (25 points max)
            $table->decimal('on_time_delivery_score', 4, 2)->default(0); // 0-15
            $table->decimal('flexibility_score', 4, 2)->default(0); // 0-10
            $table->decimal('productivity_total', 4, 2)->default(0); // 0-25

            // Soft Skills (25 points max)
            $table->decimal('communication_score', 4, 2)->default(0); // 0-10
            $table->decimal('attendance_score', 4, 2)->default(0); // 0-10
            $table->decimal('initiative_score', 4, 2)->default(0); // 0-5
            $table->decimal('soft_skills_total', 4, 2)->default(0); // 0-25

            // Totals
            $table->decimal('total_score', 5, 2)->default(0); // 0-100
            $table->enum('bonus_tier', ['excellent', 'good', 'average', 'poor'])->nullable();
            $table->decimal('bonus_percentage', 5, 2)->default(0); // 0, 50, 75, 100+
            $table->decimal('bonus_amount', 10, 2)->default(0);

            // Metadata
            $table->text('evaluator_notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved'])->default('pending');

            $table->timestamps();

            $table->unique(['user_id', 'evaluation_period_id']);
            $table->index('status');
            $table->index('bonus_tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_evaluations');
    }
};
