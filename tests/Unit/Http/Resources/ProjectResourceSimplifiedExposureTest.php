<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\V1\Client\ClientProjectResource;
use App\Http\Resources\V1\Projects\ProjectResource;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectResourceSimplifiedExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_project_resource_branch_hides_internal_fields_and_files(): void
    {
        config(['simplification.enabled' => true]);
        Role::firstOrCreate(['name' => 'client']);

        $clientUser = User::factory()->create();
        $clientUser->assignRole('client');
        $clientProfile = $this->ensureClientProfile($clientUser);

        $creator = User::factory()->create();
        $project = Project::factory()->create([
            'client_id' => $clientProfile->id,
            'budget' => 25000,
            'ui_approved_at' => now(),
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Client brief',
            'type' => 'link',
            'url' => 'https://example.com/client-brief',
            'description' => 'Internal storage note',
            'category' => 'document',
            'is_client_visible' => true,
            'is_downloadable' => true,
            'visible_to_roles' => ['client'],
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Internal scope',
            'type' => 'link',
            'url' => 'https://example.com/internal-scope',
            'description' => 'Internal only',
            'category' => 'document',
            'is_client_visible' => false,
            'is_downloadable' => true,
            'visible_to_roles' => ['client'],
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $request = Request::create('/api/v1/projects/'.$project->id);
        $request->setUserResolver(fn () => $clientUser);

        $response = (new ProjectResource($project->load('files')))->toArray($request);

        foreach ([
            'clientId',
            'quotationId',
            'description',
            'previousStatus',
            'budget',
            'canViewBudget',
            'uiApprovedAt',
            'autoApprovalJobId',
            'totalTimeSpent',
            'members',
            'quotation',
            'createdBy',
            'updatedBy',
            'invoices',
            'payments',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $response);
        }

        $files = $response['files']->toArray($request);

        $this->assertCount(1, $files);
        $this->assertSame('Client brief', $files[0]['name']);

        foreach (['description', 'isClientVisible', 'visibleToRoles', 'createdBy'] as $forbiddenFileKey) {
            $this->assertArrayNotHasKey($forbiddenFileKey, $files[0]);
        }
    }

    public function test_client_portal_project_resource_includes_only_client_visible_files(): void
    {
        config(['simplification.enabled' => true]);
        Role::firstOrCreate(['name' => 'client']);

        $clientUser = User::factory()->create();
        $clientUser->assignRole('client');
        $clientProfile = $this->ensureClientProfile($clientUser);

        $project = Project::factory()->create([
            'client_id' => $clientProfile->id,
            'budget' => 50000,
            'ui_approved_at' => now(),
        ]);

        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Client media pack',
            'type' => 'link',
            'url' => 'https://example.com/client-media',
            'description' => 'Private storage note',
            'category' => 'media',
            'is_client_visible' => true,
            'is_downloadable' => true,
        ]);

        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Internal production notes',
            'type' => 'link',
            'url' => 'https://example.com/internal-production-notes',
            'description' => 'Internal only',
            'category' => 'internal',
            'is_client_visible' => false,
            'is_downloadable' => true,
        ]);

        $request = Request::create('/api/v1/client/projects/'.$project->id);
        $request->setUserResolver(fn () => $clientUser);

        $response = (new ClientProjectResource($project->load('files')))->toArray($request);

        foreach ([
            'budget',
            'quotation',
            'quotationId',
            'invoices',
            'payments',
            'uiApprovedAt',
            'tasks',
            'members',
            'timeEntries',
            'internalNotes',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $response);
        }

        $files = $response['files']->toArray($request);

        $this->assertCount(1, $files);
        $this->assertSame('Client media pack', $files[0]['name']);
        $this->assertSame('https://example.com/client-media', $files[0]['fileUrl']);

        foreach (['description', 'isClientVisible', 'visibleToRoles', 'createdBy'] as $forbiddenFileKey) {
            $this->assertArrayNotHasKey($forbiddenFileKey, $files[0]);
        }
    }
}
