<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `due_date` to project_tasks.
 *
 * This is the task-level deadline used by the Gamification Engine to determine
 * whether a task was completed on time or late. When null, the engine falls
 * back to the parent milestone's due_date (if one exists).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            // Nullable: tasks without an explicit deadline inherit from milestone
            $table->date('due_date')->nullable()->after('estimated_hours');

            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};
