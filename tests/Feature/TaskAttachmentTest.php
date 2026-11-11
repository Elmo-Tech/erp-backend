<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_can_upload_download_and_delete_a_private_document(): void
    {
        Storage::fake('local');
        Permission::firstOrCreate(['name' => 'view_tasks']);
        $user = User::factory()->create();
        $user->givePermissionTo('view_tasks');
        $task = Task::factory()->create(['project_id' => null, 'assigned_to' => $user->id]);
        $response = $this->actingAs($user, 'sanctum')->post("/api/v1/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create('brief.pdf', 5, 'application/pdf')]);
        $response->assertCreated()->assertJsonPath('data.name', 'brief.pdf');
        $attachment = $task->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get("/api/v1/tasks/{$task->id}/attachments/{$attachment->id}/download")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = User::factory()->create();
        $other->givePermissionTo('view_tasks');
        $this->actingAs($other, 'sanctum')->getJson("/api/v1/tasks/{$task->id}/attachments")->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/tasks/{$task->id}/attachments/{$attachment->id}")->assertOk();
        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
    }

    public function test_powerpoint_can_be_uploaded(): void
    {
        Storage::fake('local');
        Permission::firstOrCreate(['name' => 'view_tasks']);
        $user = User::factory()->create();
        $user->givePermissionTo('view_tasks');
        $task = Task::factory()->create(['project_id' => null, 'assigned_to' => $user->id]);
        $this->actingAs($user, 'sanctum')->post("/api/v1/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create('slides.pptx', 5, 'application/vnd.openxmlformats-officedocument.presentationml.presentation')])->assertCreated();
    }

    public function test_disallowed_files_are_rejected(): void
    {
        Storage::fake('local');
        Permission::firstOrCreate(['name' => 'view_tasks']);
        $user = User::factory()->create();
        $user->givePermissionTo('view_tasks');
        $task = Task::factory()->create(['project_id' => null, 'assigned_to' => $user->id]);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create('script.html', 5, 'text/html')])->assertUnprocessable();
    }
}
