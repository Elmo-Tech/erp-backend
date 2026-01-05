<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('innovation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('tool_name'); // e.g., "GitHub Copilot", "ChatGPT", "Custom Script"
            $table->enum('category', ['ai_tool', 'automation', 'new_tech', 'process_improvement']);
            $table->text('description');
            $table->text('impact_description')->nullable(); // How it helped
            $table->enum('impact_level', ['low', 'medium', 'high'])->default('medium');
            $table->integer('time_saved_minutes')->nullable(); // Estimated time saved
            
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('category');
            $table->index('impact_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('innovation_logs');
    }
};
