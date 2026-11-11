<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteTaskAttachments;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Tasks\TaskVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    public function __construct() { $this->middleware(['auth:sanctum', 'locale']); }

    public function index(Request $request, Task $task)
    {
        $this->authorizeView($request->user(), $task);
        return ApiResponse::success($task->attachments()->orderBy('id')->get()->map(fn ($file) => $this->payload($file))->all());
    }

    public function store(Request $request, Task $task)
    {
        $this->authorizeView($request->user(), $task);
        abort_unless($this->canManage($request->user(), $task), 403);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:pdf,ppt,pptx', 'extensions:pdf,ppt,pptx', 'max:25600']]);
        $file = $data['file'];
        $path = $file->store("task-attachments/{$task->id}", 'local');
        if (! $path) throw new \RuntimeException('Could not save the attachment.');
        try {
            $attachment = DB::transaction(function () use ($task, $file, $path, $request) {
                $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
                if ($locked->attachments()->count() >= 10) throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'A task can have at most 10 document attachments.']);
                return $locked->attachments()->create(['uploaded_by' => $request->user()->id, 'original_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())), 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
            });
        } catch (\Throwable $exception) { Storage::disk('local')->delete($path); throw $exception; }
        return ApiResponse::success($this->payload($attachment), 'Attachment uploaded successfully', 201);
    }

    public function download(Request $request, Task $task, TaskAttachment $attachment)
    {
        $this->authorizeView($request->user(), $task);
        abort_unless($attachment->task_id === $task->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Task $task, TaskAttachment $attachment)
    {
        $this->authorizeView($request->user(), $task);
        abort_unless($attachment->task_id === $task->id, 404);
        abort_unless($this->canManage($request->user(), $task) || $attachment->uploaded_by === $request->user()->id, 403);
        DB::transaction(function () use ($attachment): void {
            $path = $attachment->path;
            $attachment->delete();
            DB::afterCommit(fn () => DeleteTaskAttachments::dispatch([$path]));
        });
        return ApiResponse::success(null, 'Attachment deleted successfully');
    }

    private function authorizeView(User $user, Task $task): void { abort_unless(app(TaskVisibility::class)->canView($user, $task), 403); }
    private function canManage(User $user, Task $task): bool
    {
        return $user->can('manage_tasks') || match ($task->type?->value) {
            'main' => $user->can('edit_main_tasks'),
            'weekly' => $user->can('edit_weekly_tasks'),
            'subtask' => $user->can('edit_subtasks'),
            default => false,
        } || (int) $task->assigned_to === $user->id || $task->isAssignedTo($user) || $task->isSupervisor($user);
    }
    private function payload(TaskAttachment $file): array
    {
        return ['id' => $file->id, 'name' => $file->original_name, 'size' => $file->size, 'mimeType' => $file->mime_type, 'uploadedBy' => $file->uploaded_by, 'createdAt' => $file->created_at?->toISOString()];
    }
}
