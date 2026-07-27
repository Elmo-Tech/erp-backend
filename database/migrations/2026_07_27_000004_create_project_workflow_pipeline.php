<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const DEFAULT_STAGES = [
        ['name' => 'Backlog', 'slug' => 'backlog', 'color' => '#64748b', 'category' => 'backlog', 'is_terminal' => false],
        ['name' => 'Ready', 'slug' => 'ready', 'color' => '#3b82f6', 'category' => 'planned', 'is_terminal' => false],
        ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#f59e0b', 'category' => 'active', 'is_terminal' => false],
        ['name' => 'On Hold', 'slug' => 'on_hold', 'color' => '#f97316', 'category' => 'paused', 'is_terminal' => false],
        ['name' => 'In Review', 'slug' => 'in_review', 'color' => '#8b5cf6', 'category' => 'review', 'is_terminal' => false],
        ['name' => 'Done', 'slug' => 'done', 'color' => '#22c55e', 'category' => 'done', 'is_terminal' => true],
        ['name' => 'Cancelled', 'slug' => 'cancelled', 'color' => '#ef4444', 'category' => 'cancelled', 'is_terminal' => true],
    ];

    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('workflow_template_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 20)->default('#64748b');
            $table->string('category', 30);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->unique(['workflow_template_id', 'slug']);
            $table->unique(['workflow_template_id', 'position']);
        });

        Schema::create('project_workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_template_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 20)->default('#64748b');
            $table->string('category', 30);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'slug']);
            $table->unique(['project_id', 'position']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('workflow_stage_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_workflow_stages')
                ->nullOnDelete();
            $table->boolean('is_pipeline_task')->default(false)->after('workflow_stage_id')->index();
            $table->unsignedInteger('pipeline_position')->default(0)->after('is_pipeline_task');
            $table->index(['project_id', 'workflow_stage_id', 'pipeline_position'], 'tasks_pipeline_order_index');
        });

        foreach (['manage_project_workflows', 'manage_workflow_templates'] as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        foreach (['admin', 'super-admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->givePermissionTo([
                'view_all_projects',
                'manage_all_projects',
                'manage_project_workflows',
            ]);
        }

        Role::where('name', 'super-admin')
            ->first()
            ?->givePermissionTo('manage_workflow_templates');

        $now = now();
        $templateId = DB::table('workflow_templates')->insertGetId([
            'name' => 'Standard Delivery Workflow',
            'description' => 'Reusable project delivery pipeline with planning, execution, review, and terminal stages.',
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $templateStageIds = [];
        foreach (self::DEFAULT_STAGES as $position => $stage) {
            $templateStageIds[$position] = DB::table('workflow_template_stages')->insertGetId([
                'workflow_template_id' => $templateId,
                ...$stage,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('projects')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($projects) use ($templateStageIds, $now): void {
                $rows = [];
                foreach ($projects as $project) {
                    foreach (self::DEFAULT_STAGES as $position => $stage) {
                        $rows[] = [
                            'project_id' => $project->id,
                            'workflow_template_stage_id' => $templateStageIds[$position],
                            ...$stage,
                            'position' => $position,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('project_workflow_stages')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_pipeline_order_index');
            $table->dropIndex(['is_pipeline_task']);
            $table->dropConstrainedForeignId('workflow_stage_id');
            $table->dropColumn(['is_pipeline_task', 'pipeline_position']);
        });

        Schema::dropIfExists('project_workflow_stages');
        Schema::dropIfExists('workflow_template_stages');
        Schema::dropIfExists('workflow_templates');

        Permission::whereIn('name', [
            'manage_project_workflows',
            'manage_workflow_templates',
        ])->delete();
    }
};
