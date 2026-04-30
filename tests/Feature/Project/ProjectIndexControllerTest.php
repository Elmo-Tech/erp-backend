<?php

namespace Tests\Feature\Project;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_projects_without_requiring_quotation_module(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme-projects@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        Project::create([
            'client_id' => $client->id,
            'title' => 'Website',
            'description' => 'Build the public website.',
            'status' => 'draft',
            'budget' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonPath('data.projects.0.title', 'Website');
    }

    public function test_it_shows_a_project_without_requiring_quotation_module(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme-project-show@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'title' => 'Mobile App',
            'description' => 'Build the mobile app.',
            'status' => 'draft',
            'budget' => 2000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Mobile App');
    }
}
