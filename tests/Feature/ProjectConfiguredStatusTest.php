<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LookupOption;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectConfiguredStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_status_can_be_created_filtered_updated_and_retained_when_inactive(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(array_map(fn ($permission) => Permission::findOrCreate($permission, 'web'), ['manage_projects', 'view_all_projects', 'manage_project_statuses']));
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $client = Client::factory()->create();
        $custom = LookupOption::create(['type' => 'project_status', 'value' => 'client_review', 'label' => 'Client Review', 'is_active' => true, 'sort_order' => 20]);
        $inactive = LookupOption::create(['type' => 'project_status', 'value' => 'retired_status', 'label' => 'Retired', 'is_active' => false, 'sort_order' => 21]);
        $this->actingAs($actor, 'sanctum');

        $created = $this->postJson('/api/v1/projects/direct', ['clientId' => $client->id, 'title' => 'Configured project', 'budget' => 100, 'status' => $custom->value])
            ->assertCreated()->assertJsonPath('data.status', 'client_review');
        $this->postJson('/api/v1/projects/direct', ['clientId' => $client->id, 'title' => 'Invalid project', 'budget' => 100, 'status' => $inactive->value])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $id = $created->json('data.projectId');
        LookupOption::create(['type' => 'project_status', 'value' => 'inactive_work', 'label' => 'Inactive work', 'is_active' => true, 'sort_order' => 22]);
        $other = Project::factory()->create(['status' => 'inactive_work']);
        $this->getJson('/api/v1/projects?filter[status]=active')->assertOk()->assertJsonMissing(['projectId' => $other->id]);
        $this->putJson("/api/v1/settings/lookup-options/{$custom->id}", ['type' => 'project_status', 'label' => 'Client Review', 'value' => 'renamed_key'])
            ->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson("/api/v1/settings/lookup-options/{$custom->id}", ['type' => 'project_status', 'label' => 'Client Review Updated', 'value' => 'client_review'])
            ->assertOk()->assertJsonPath('data.label', 'Client Review Updated');
        $this->getJson('/api/v1/projects?filter[status]=client_review')->assertOk()->assertJsonFragment(['projectId' => $id, 'status' => 'client_review']);
        $this->putJson("/api/v1/projects/{$id}", ['status' => $inactive->value])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("/api/v1/projects/{$id}", ['status' => 'missing_status'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $custom->update(['is_active' => false]);
        $this->putJson("/api/v1/projects/{$id}", ['title' => 'Retained project', 'status' => $custom->value])->assertOk()->assertJsonPath('data.status', 'client_review');
        $this->patchJson("/api/v1/projects/{$id}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->patchJson("/api/v1/projects/{$id}/status", ['status' => $custom->value])->assertUnprocessable()->assertJsonValidationErrors('status');
        $custom->update(['is_active' => true]);
        $this->patchJson("/api/v1/projects/{$id}/status", ['status' => $custom->value])->assertOk()->assertJsonPath('data.status', 'client_review');
        $this->putJson("/api/v1/projects/{$id}", ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('project_histories', ['project_id' => $id, 'field_name' => 'status', 'old_value' => 'client_review', 'new_value' => 'completed']);
        $this->assertSame('completed', Project::withoutGlobalScopes()->findOrFail($id)->status->value);
    }
}
