<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Activity log for tasks - tracks all changes:
     * - Status changes
     * - Assignee changes
     * - Timer start/stop
     * - Test scenario updates
     * - Comments
     */
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Activity type: status_change, assignee_change, timer_start, timer_stop,
            // test_completed, test_verified, comment, created, updated, etc.
            $table->string('type');

            // Human-readable description
            $table->string('description');

            // JSON data for additional context
            // e.g., { "old_status": "todo", "new_status": "in_progress" }
            $table->json('metadata')->nullable();

            // For comments
            $table->text('comment')->nullable();
            $table->boolean('is_internal')->default(false); // Internal = admin only

            $table->timestamps();

            $table->index(['task_id', 'created_at']);
            $table->index(['task_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
