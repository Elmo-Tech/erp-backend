<?php

namespace Tests\Unit\Scopes;

use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Scopes\ClientScope;
use Database\Seeders\RolesAndPermissionsSeeder;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Property-Based Tests for ClientScope Data Isolation
 *
 * Feature: client-portal-security, Property 2: ClientScope Data Isolation
 * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
 */
class ClientScopePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Property 2: ClientScope Data Isolation - Project Filtering
     *
     * For any authenticated client user and for any query on Project model,
     * the results SHALL only include records where the project's client_id
     * matches the authenticated user's client profile id.
     *
     * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
     */
    public function test_client_scope_filters_projects_by_client_id(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 4),
                Generator\choose(1, 3)
            )
            ->then(function ($numClients, $projectsPerClient) {
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];

                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                $allProjects = collect();
                foreach ($clients as $client) {
                    $clientProjects = Project::factory()
                        ->count($projectsPerClient)
                        ->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $allProjects = $allProjects->merge($clientProjects);
                }

                foreach ($clients as $client) {
                    Auth::login($client);

                    $visibleProjects = Project::all();

                    foreach ($visibleProjects as $project) {
                        $this->assertEquals($clientProfiles[$client->id]->id, $project->client_id);
                    }

                    $expectedCount = $allProjects->where('client_id', $clientProfiles[$client->id]->id)->count();
                    $this->assertEquals($expectedCount, $visibleProjects->count());
                }

                Auth::logout();
            });
    }

    /**
     * Property 2: ClientScope Data Isolation - Admin Access
     *
     * For any admin user, queries SHALL return all records
     * without filtering (scope should not apply).
     *
     * **Validates: Requirements 2.4**
     */
    public function test_client_scope_does_not_apply_to_admin_users(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 3),
                Generator\choose(1, 2)
            )
            ->then(function ($numClients, $projectsPerClient) {
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['client', 'admin']);
                })->delete();

                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                $totalProjects = 0;
                foreach ($clients as $client) {
                    Project::factory()
                        ->count($projectsPerClient)
                        ->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $totalProjects += $projectsPerClient;
                }

                $admin = User::factory()->create();
                $admin->givePermissionTo('view_all_projects');

                Auth::login($admin);
                $adminVisibleProjects = Project::all();
                $this->assertEquals($totalProjects, $adminVisibleProjects->count());

                Auth::logout();
            });
    }

    /**
     * Property 2: ClientScope Data Isolation - Unauthenticated Access
     *
     * For any unauthenticated request, the scope SHALL not apply any filtering.
     *
     * **Validates: Requirements 2.3**
     */
    public function test_client_scope_does_not_apply_when_unauthenticated(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(1, 3)
            )
            ->then(function ($numProjects) {
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

                Auth::logout();

                $clients = User::factory()->count(2)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                $totalProjects = 0;
                foreach ($clients as $client) {
                    Project::factory()
                        ->count($numProjects)
                        ->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $totalProjects += $numProjects;
                }

                $visibleProjects = Project::all();
                $this->assertEquals($totalProjects, $visibleProjects->count());
            });
    }

    /**
     * Property 2: ClientScope Data Isolation - 404 Behavior
     *
     * For any client attempting to access a project not belonging to them,
     * the scope SHALL filter out inaccessible records.
     *
     * **Validates: Requirements 2.5**
     */
    public function test_client_scope_prevents_access_to_other_clients_projects(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 3)
            )
            ->then(function ($numClients) {
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                $projects = [];
                foreach ($clients as $client) {
                    $projects[] = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);
                }

                foreach ($clients as $index => $client) {
                    Auth::login($client);

                    foreach ($projects as $projectIndex => $project) {
                        if ($projectIndex !== $index) {
                            $foundProject = Project::where('id', $project->id)->first();
                            $this->assertNull($foundProject,
                                "Client {$client->id} should not be able to access project {$project->id}");
                        }
                    }

                    $ownProject = $projects[$index];
                    $foundOwnProject = Project::where('id', $ownProject->id)->first();
                    $this->assertNotNull($foundOwnProject,
                        "Client {$client->id} should be able to access their own project {$ownProject->id}");
                }

                Auth::logout();
            });
    }
}
