<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add task type and priority:
     * - type: main (root task), weekly (sprint task), subtask
     * - priority: low, medium, high, urgent
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('type', ['main', 'weekly', 'subtask'])->default('main')->after('status');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('type');
            $table->date('due_date')->nullable()->after('priority');
            $table->date('start_date')->nullable()->after('due_date');
            
            // For weekly tasks - sprint info
            $table->unsignedInteger('sprint_number')->nullable()->after('start_date');
            
            // Acceptance criteria (markdown)
            $table->text('acceptance_criteria')->nullable()->after('description');
            
            $table->index('type');
            $table->index('priority');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['due_date']);
            
            $table->dropColumn([
                'type',
                'priority',
                'due_date',
                'start_date',
                'sprint_number',
                'acceptance_criteria',
            ]);
        });
    }
};
