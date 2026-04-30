<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Task Dependencies Table (for task blocking/dependencies)
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('depends_on_task_id')->constrained('tasks')->onDelete('cascade');
            $table->enum('dependency_type', ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'])->default('finish_to_start');
            $table->integer('lag_days')->default(0);
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
            $table->index(['task_id', 'dependency_type']);
        });

        // Task Templates Table
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->json('default_values')->nullable();
            $table->json('subtasks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // Recurring Tasks Configuration
        Schema::create('recurring_task_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_task_id')->constrained('tasks')->onDelete('cascade');
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly']);
            $table->integer('interval')->default(1);
            $table->json('days_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('max_occurrences')->nullable();
            $table->integer('occurrences_created')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // Add recurring config to tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('recurring_config_id')->nullable()->after('parent_id')->constrained('recurring_task_configs')->onDelete('set null');
            $table->foreignId('template_id')->nullable()->after('recurring_config_id')->constrained('task_templates')->onDelete('set null');
            $table->integer('occurrence_number')->nullable()->after('template_id');
            $table->boolean('is_recurring_instance')->default(false)->after('occurrence_number');
        });

        // Task Checklists
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('task_checklists')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_completed')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // Task Labels (for better organization)
        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#6B7280');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Task-Label Pivot
        Schema::create('task_label_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_label_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['task_id', 'task_label_id']);
        });

        // Task Time Estimates History (for tracking estimation accuracy)
        Schema::create('task_time_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->decimal('estimated_hours', 8, 2);
            $table->foreignId('estimated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Add indexes for performance
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['status', 'due_date']);
            $table->index(['assigned_to', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_date']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['project_id', 'status']);
            $table->dropColumn(['recurring_config_id', 'template_id', 'occurrence_number', 'is_recurring_instance']);
        });

        Schema::dropIfExists('task_time_estimates');
        Schema::dropIfExists('task_label_task');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('recurring_task_configs');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_dependencies');
    }
};
