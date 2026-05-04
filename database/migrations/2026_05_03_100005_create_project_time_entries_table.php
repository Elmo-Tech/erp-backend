<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `project_time_entries` for the Project Execution layer.
 *
 * NOTE: A polymorphic `time_entries` table already exists for the
 * App\Modules\TimeEntry module. This table is intentionally separate,
 * scoped directly to project_tasks, and carries additional fields
 * (is_manual, note) specific to execution-layer time tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_time_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('project_tasks')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();

            // Computed on stop; nullable while timer is running
            $table->unsignedInteger('duration_minutes')->nullable();

            // true = manually entered entry (not timer-based)
            $table->boolean('is_manual')->default(false);

            $table->text('note')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('task_id');
            $table->index('user_id');
            $table->index(['user_id', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_entries');
    }
};
