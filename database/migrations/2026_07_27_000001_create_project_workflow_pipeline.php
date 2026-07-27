<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_STAGES = [
        ['name' => 'Backlog', 'status' => 'backlog', 'color' => '#64748b', 'is_terminal' => false],
        ['name' => 'Ready', 'status' => 'to_do', 'color' => '#3b82f6', 'is_terminal' => false],
        ['name' => 'In Progress', 'status' => 'in_progress', 'color' => '#f59e0b', 'is_terminal' => false],
        ['name' => 'On Hold', 'status' => 'on_hold', 'color' => '#f97316', 'is_terminal' => false],
        ['name' => 'In Review', 'status' => 'in_review', 'color' => '#8b5cf6', 'is_terminal' => false],
        ['name' => 'QA', 'status' => 'qa', 'color' => '#06b6d4', 'is_terminal' => false],
        ['name' => 'Done', 'status' => 'done', 'color' => '#22c55e', 'is_terminal' => true],
        ['name' => 'Cancelled', 'status' => 'cancelled', 'color' => '#ef4444', 'is_terminal' => true],
    ];

    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_template_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status');
            $table->string('color', 20);
            $table->unsignedInteger('position');
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->unique(['workflow_template_id', 'position']);
        });

        Schema::create('project_workflow_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('status');
            $table->string('color', 20);
            $table->unsignedInteger('position');
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'position']);
            $table->index(['project_id', 'status']);
        });

        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->foreignId('workflow_stage_id')
                ->nullable()
                ->after('status')
                ->constrained('project_workflow_stages')
                ->nullOnDelete();
            $table->unsignedInteger('pipeline_position')->default(0)->after('workflow_stage_id');
            $table->string('cover_image_path')->nullable()->after('description');
        });

        Schema::create('project_task_assignees', function (Blueprint $table): void {
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->primary(['project_task_id', 'user_id']);
            $table->index('user_id');
        });

        $this->seedDefaultTemplate();
        $this->backfillProjectWorkflows();
        $this->backfillTaskAssignees();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_assignees');

        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workflow_stage_id');
            $table->dropColumn(['pipeline_position', 'cover_image_path']);
        });

        Schema::dropIfExists('project_workflow_stages');
        Schema::dropIfExists('workflow_template_stages');
        Schema::dropIfExists('workflow_templates');
    }

    private function seedDefaultTemplate(): void
    {
        $templateId = DB::table('workflow_templates')->insertGetId([
            'name' => 'Standard Delivery',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workflow_template_stages')->insert(
            collect(self::DEFAULT_STAGES)->map(
                fn (array $stage, int $position): array => [
                    ...$stage,
                    'workflow_template_id' => $templateId,
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            )->all()
        );
    }

    private function backfillProjectWorkflows(): void
    {
        DB::table('projects')->orderBy('id')->pluck('id')->each(function (int $projectId): void {
            $stageIds = [];

            foreach (self::DEFAULT_STAGES as $position => $stage) {
                $stageIds[$stage['status']] = DB::table('project_workflow_stages')->insertGetId([
                    ...$stage,
                    'project_id' => $projectId,
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($stageIds as $status => $stageId) {
                DB::table('project_tasks')
                    ->where('project_id', $projectId)
                    ->where('status', $status)
                    ->update(['workflow_stage_id' => $stageId]);
            }
        });
    }

    private function backfillTaskAssignees(): void
    {
        DB::table('project_tasks')
            ->whereNotNull('assignee_id')
            ->orderBy('id')
            ->select(['id', 'assignee_id'])
            ->chunkById(500, function ($tasks): void {
                DB::table('project_task_assignees')->insertOrIgnore(
                    $tasks->map(fn ($task): array => [
                        'project_task_id' => $task->id,
                        'user_id' => $task->assignee_id,
                        'position' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });
    }
};
