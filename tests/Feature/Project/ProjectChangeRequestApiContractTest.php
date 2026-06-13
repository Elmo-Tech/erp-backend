<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\ChangeRequest;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectChangeRequestApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_request_index_returns_lightweight_backend_enum_contract(): void
    {
        $user = $this->userWithPermissions(['view_projects', 'manage_all_projects']);
        $project = Project::factory()->create();

        ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Extra onboarding workshop',
            'description' => 'Add a second workshop for client admins.',
            'price' => 750,
            'status' => 'sent_to_client',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/change-requests");

        $response->assertOk()
            ->assertJsonPath('data.changeRequests.0.title', 'Extra onboarding workshop')
            ->assertJsonPath('data.changeRequests.0.status', 'sent_to_client')
            ->assertJsonPath('data.changeRequests.0.statusLabel', 'Sent to Client')
            ->assertJsonMissingPath('data.changeRequests.0.internalCost')
            ->assertJsonMissingPath('data.changeRequests.0.approvedBy');

        $this->assertSame([
            'changeRequestId',
            'projectId',
            'title',
            'description',
            'price',
            'status',
            'statusLabel',
            'createdAt',
            'updatedAt',
        ], array_keys($response->json('data.changeRequests.0')));
    }

    public function test_change_request_update_rejects_frontend_only_statuses(): void
    {
        $user = $this->userWithPermissions(['manage_change_requests']);
        $project = Project::factory()->create();
        $changeRequest = ChangeRequest::create([
            'project_id' => $project->id,
            'title' => 'Extra onboarding workshop',
            'description' => 'Add a second workshop for client admins.',
            'price' => 750,
            'status' => 'draft',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/change-requests/{$changeRequest->id}", [
                'status' => 'in_progress',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
