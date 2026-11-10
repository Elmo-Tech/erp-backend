<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\Tasks\TaskVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskChecklistController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function update(Request $request, Task $task, string $itemId)
    {
        $user = $request->user();
        abort_unless(app(TaskVisibility::class)->canView($user, $task), 403);
        $canEdit = $user->can('manage_tasks') || match ($task->type?->value) {
            'main' => $user->can('edit_main_tasks'),
            'weekly' => $user->can('edit_weekly_tasks'),
            'subtask' => $user->can('edit_subtasks'),
            default => false,
        };
        abort_unless($canEdit || $user->can('update', $task) || (int) $task->assigned_to === $user->id || $task->isAssignedTo($user) || $task->isSupervisor($user), 403);
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $checklist = DB::transaction(function () use ($task, $itemId, $data) {
            $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $items = $locked->description_checklist ?? [];
            $index = collect($items)->search(fn ($item) => ($item['id'] ?? null) === $itemId);
            abort_if($index === false, 404);
            $items[$index]['completed'] = $data['completed'];
            $locked->update(['description_checklist' => $items]);
            return $items;
        });
        return ApiResponse::success($checklist, 'Checklist updated');
    }
}
