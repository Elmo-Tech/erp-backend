<?php

namespace App\Modules\PersonalTasks\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\PersonalTasks\Actions\TogglePersonalTaskTimerAction;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
use App\Modules\PersonalTasks\Resources\PersonalTaskResource;
use App\Modules\PersonalTasks\Resources\PersonalTimeEntryResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalTaskController extends Controller
{
    public function __construct(
        private readonly TogglePersonalTaskTimerAction $toggleTimer,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request): JsonResponse
    {
        $tasks = PersonalTask::query()
            ->where('user_id', $request->user()->id)
            ->with('timeEntries')
            ->orderByRaw('status = ? asc', ['done'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success([
            'tasks' => PersonalTaskResource::collection($tasks),
        ], 'Personal tasks retrieved successfully');
    }

    public function active(Request $request): JsonResponse
    {
        $entry = PersonalTimeEntry::query()
            ->with('personalTask')
            ->where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        return ApiResponse::success(
            $entry ? new PersonalTimeEntryResource($entry) : null,
            $entry ? 'Active personal time entry retrieved successfully' : 'No active personal time entry'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->taskRules());

        $task = PersonalTask::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['dueDate'] ?? null,
            'estimated_minutes' => $data['estimatedMinutes'] ?? null,
        ]);

        return ApiResponse::success(new PersonalTaskResource($task), 'Personal task created successfully', 201);
    }

    public function update(Request $request, PersonalTask $task): JsonResponse
    {
        $this->assertTaskOwner($request, $task);

        $data = $request->validate($this->taskRules(requiredTitle: false));
        $status = $data['status'] ?? $task->status;

        $task->update([
            'title' => $data['title'] ?? $task->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task->description,
            'status' => $status,
            'priority' => $data['priority'] ?? $task->priority,
            'due_date' => array_key_exists('dueDate', $data) ? $data['dueDate'] : $task->due_date,
            'estimated_minutes' => array_key_exists('estimatedMinutes', $data) ? $data['estimatedMinutes'] : $task->estimated_minutes,
            'completed_at' => $status === 'done' ? ($task->completed_at ?? now()) : null,
        ]);

        return ApiResponse::success(new PersonalTaskResource($task->refresh()), 'Personal task updated successfully');
    }

    public function destroy(Request $request, PersonalTask $task): JsonResponse
    {
        $this->assertTaskOwner($request, $task);
        $task->delete();

        return ApiResponse::success(null, 'Personal task deleted successfully');
    }

    public function complete(Request $request, PersonalTask $task): JsonResponse
    {
        $this->assertTaskOwner($request, $task);
        $task->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        return ApiResponse::success(new PersonalTaskResource($task->refresh()), 'Personal task completed successfully');
    }

    public function toggleTimeEntry(Request $request, PersonalTask $task): JsonResponse
    {
        $this->assertTaskOwner($request, $task);

        $entry = $this->toggleTimer->execute($task, $request->user());
        $entry->load('personalTask');

        return ApiResponse::success(
            new PersonalTimeEntryResource($entry),
            $entry->isRunning() ? 'Timer started' : 'Timer stopped',
            $entry->isRunning() ? 201 : 200
        );
    }

    public function storeTimeEntry(Request $request, PersonalTask $task): JsonResponse
    {
        $this->assertTaskOwner($request, $task);
        $data = $request->validate($this->timeEntryRules());

        $start = Carbon::parse($data['startTime']);
        $end = Carbon::parse($data['endTime']);

        $entry = $task->timeEntries()->create([
            'user_id' => $request->user()->id,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'is_manual' => true,
            'note' => $data['note'] ?? null,
        ]);

        return ApiResponse::success(new PersonalTimeEntryResource($entry), 'Personal time entry logged successfully', 201);
    }

    public function updateTimeEntry(Request $request, PersonalTask $task, PersonalTimeEntry $entry): JsonResponse
    {
        $this->assertTaskOwner($request, $task);
        $this->assertEntryBelongsToTask($entry, $task);
        $data = $request->validate($this->timeEntryRules());

        $start = Carbon::parse($data['startTime']);
        $end = Carbon::parse($data['endTime']);
        $entry->update([
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'is_manual' => true,
            'note' => $data['note'] ?? null,
        ]);

        return ApiResponse::success(new PersonalTimeEntryResource($entry->refresh()), 'Personal time entry updated successfully');
    }

    public function destroyTimeEntry(Request $request, PersonalTask $task, PersonalTimeEntry $entry): JsonResponse
    {
        $this->assertTaskOwner($request, $task);
        $this->assertEntryBelongsToTask($entry, $task);
        $entry->delete();

        return ApiResponse::success(null, 'Personal time entry deleted successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRules(bool $requiredTitle = true): array
    {
        return [
            'title' => [$requiredTitle ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'dueDate' => ['nullable', 'date'],
            'estimatedMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timeEntryRules(): array
    {
        return [
            'startTime' => ['required', 'date'],
            'endTime' => ['required', 'date', 'after:startTime'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function assertTaskOwner(Request $request, PersonalTask $task): void
    {
        abort_if($task->user_id !== $request->user()->id, 404, 'Personal task not found.');
    }

    private function assertEntryBelongsToTask(PersonalTimeEntry $entry, PersonalTask $task): void
    {
        abort_if($entry->personal_task_id !== $task->id || $entry->user_id !== $task->user_id, 404, 'Personal time entry not found.');
    }
}
