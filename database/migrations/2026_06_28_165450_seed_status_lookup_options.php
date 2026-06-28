<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $projectStatuses = [
            ['value' => 'draft', 'label' => 'Draft', 'color' => '#64748b', 'sort_order' => 1],
            ['value' => 'active', 'label' => 'Active', 'color' => '#3b82f6', 'sort_order' => 2],
            ['value' => 'ui_ux', 'label' => 'UI/UX Phase', 'color' => '#8b5cf6', 'sort_order' => 3],
            ['value' => 'development', 'label' => 'Development', 'color' => '#eab308', 'sort_order' => 4],
            ['value' => 'testing', 'label' => 'Testing', 'color' => '#f97316', 'sort_order' => 5],
            ['value' => 'completed', 'label' => 'Completed', 'color' => '#22c55e', 'sort_order' => 6],
            ['value' => 'on_hold', 'label' => 'On Hold', 'color' => '#f59e0b', 'sort_order' => 7],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'color' => '#ef4444', 'sort_order' => 8],
        ];

        foreach ($projectStatuses as $status) {
            DB::table('lookup_options')->updateOrInsert(
                [
                    'type' => 'project_status',
                    'value' => $status['value'],
                ],
                [
                    'label' => $status['label'],
                    'color' => $status['color'],
                    'icon' => null,
                    'metadata' => json_encode(['is_system' => true]),
                    'sort_order' => $status['sort_order'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $taskStatuses = [
            ['value' => 'todo', 'label' => 'To Do', 'color' => '#64748b', 'sort_order' => 1],
            ['value' => 'in_progress', 'label' => 'In Progress', 'color' => '#3b82f6', 'sort_order' => 2],
            ['value' => 'review', 'label' => 'Review', 'color' => '#f59e0b', 'sort_order' => 3],
            ['value' => 'testing', 'label' => 'Testing', 'color' => '#8b5cf6', 'sort_order' => 4],
            ['value' => 'completed', 'label' => 'Completed', 'color' => '#22c55e', 'sort_order' => 5],
        ];

        foreach ($taskStatuses as $status) {
            DB::table('lookup_options')->updateOrInsert(
                [
                    'type' => 'task_status',
                    'value' => $status['value'],
                ],
                [
                    'label' => $status['label'],
                    'color' => $status['color'],
                    'icon' => null,
                    'metadata' => json_encode(['is_system' => true]),
                    'sort_order' => $status['sort_order'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lookup_options')->whereIn('type', ['project_status', 'task_status'])->delete();
    }
};
