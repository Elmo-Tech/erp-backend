<?php

namespace Tests\Unit\Policies;

use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Policies\ProjectPolicy;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Property-Based Tests for ProjectPolicy Authorization
 *
 * Feature: client-portal-security, Property 7: ProjectPolicy Authorization
 * Validates: Requirements 11.2, 11.3, 11.4
 */
class ProjectPolicyPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'client']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'employee']);
    }

    /**
     * Property 7: ProjectPolicy Authorization - Client View Access
     *
     * For any client user and for any project, view authorization SHALL succeed
     * only if project.client_id equals user.id.
     *
     * **Validates: Requirements 11.2**
     */
    public function test_client_can_only_view_own_projects(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2, 5) // Number of clients
            )
            ->then(function ($numClients) {
                $policy = new ProjectPolicy;

                // Create multiple clients
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create one project for each client
                $projects = [];
                foreach ($clients as $client) {
                    $projects[] = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);
                }

                // Test that each client can only view their own project
                foreach ($clients as $clientIndex => $client) {
                    foreach ($projects as $projectIndex => $project) {
                        $canView = $policy->view($client, $project);

                        if ($clientIndex === $projectIndex) {
                            // Client should be able to view their own project
                            $this->assertTrue($canView,
                                "Client {$client->id} should be able to view their own project {$project->id}");
                        } else {
                            // Client should NOT be able to view other clients' projects
                            $this->assertFalse($canView,
                                "Client {$client->id} should NOT be able to view project {$project->id} owned by client {$project->client_id}");
                        }
                    }
                }
            });
    }

    /**
     * Property 7: ProjectPolicy Authorization - Admin View Access
     *
     * For any admin user, view authorization SHALL succeed
     * for all projects.
     *
     * **Validates: Requirements 11.3**
     */
    public function test_admin_users_can_view_all_projects(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 5), // Number of projects
                Generator\elements(['admin', 'super-admin'])
            )
            ->then(function ($numProjects, $role) {
                $policy = new ProjectPolicy;

                // Create admin user
                $user = User::factory()->create();
                $user->assignRole($role);

                // Create multiple projects with different clients
                $clients = User::factory()->count($numProjects)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                $projects = [];
                foreach ($clients as $client) {
                    $projects[] = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);
                }

                // Admins should be able to view all projects
                foreach ($projects as $project) {
                    $canView = $policy->view($user, $project);
                    $this->assertTrue($canView,
                        "User with role '{$role}' should be able to view project {$project->id} owned by client {$project->client_id}");
                }
            });
    }

    /**
     * Property 7: ProjectPolicy Authorization - Client Create/Update/Delete Restrictions
     *
     * For any client user, create, update, and delete authorization SHALL fail.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_client_cannot_create_update_delete_projects(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 3) // Number of clients
            )
            ->then(function ($numClients) {
                $policy = new ProjectPolicy;

                // Create client users
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create a project for testing update/delete
                $project = Project::factory()->create(['client_id' => $clientProfiles[$clients[0]->id]->id]);

                foreach ($clients as $client) {
                    // Test create permission
                    $canCreate = $policy->create($client);
                    $this->assertFalse($canCreate,
                        "Client {$client->id} should NOT be able to create projects");

                    // Test update permission
                    $canUpdate = $policy->update($client, $project);
                    $this->assertFalse($canUpdate,
                        "Client {$client->id} should NOT be able to update project {$project->id}");

                    // Test delete permission
                    $canDelete = $policy->delete($client, $project);
                    $this->assertFalse($canDelete,
                        "Client {$client->id} should NOT be able to delete project {$project->id}");
                }
            });
    }

    /**
     * Property 7: ProjectPolicy Authorization - Admin Create/Update/Delete Access
     *
     * For any admin user, create, update, and delete authorization
     * SHALL succeed.
     *
     * **Validates: Requirements 11.3**
     */
    public function test_admin_users_can_create_update_delete_projects(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['admin', 'super-admin'])
            )
            ->then(function ($role) {
                $policy = new ProjectPolicy;

                // Create admin user
                $user = User::factory()->create();
                $user->assignRole($role);

                // Create a client and project for testing
                $client = User::factory()->create();
                $client->assignRole('client');
                $clientProfile = $this->ensureClientProfile($client);
                $project = Project::factory()->create(['client_id' => $clientProfile->id]);

                // Test create permission
                $canCreate = $policy->create($user);
                $this->assertTrue($canCreate,
                    "User with role '{$role}' should be able to create projects");

                // Test update permission
                $canUpdate = $policy->update($user, $project);
                $this->assertTrue($canUpdate,
                    "User with role '{$role}' should be able to update project {$project->id}");

                // Test delete permission
                $canDelete = $policy->delete($user, $project);
                $this->assertTrue($canDelete,
                    "User with role '{$role}' should be able to delete project {$project->id}");
            });
    }

    /**
     * Property 7: ProjectPolicy Authorization - UI Approval Access
     *
     * For any project, only the project's client or admin users SHALL be able
     * to approve UI deliverables.
     *
     * **Validates: Requirements 11.2, 11.3**
     */
    public function test_ui_approval_authorization(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(2, 4) // Number of clients
            )
            ->then(function ($numClients) {
                $policy = new ProjectPolicy;

                // Create multiple clients
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create admin user
                $admin = User::factory()->create();
                $admin->assignRole('admin');

                // Create super-admin user
                $superAdmin = User::factory()->create();
                $superAdmin->assignRole('super-admin');

                // Create employee user
                $employee = User::factory()->create();
                $employee->assignRole('employee');

                // Create project for first client
                $project = Project::factory()->create(['client_id' => $clientProfiles[$clients[0]->id]->id]);

                // Test that only the project's client can approve UI
                foreach ($clients as $index => $client) {
                    $canApproveUI = $policy->approveUI($client, $project);

                    if ($index === 0) {
                        // The project owner should be able to approve UI
                        $this->assertTrue($canApproveUI,
                            "Client {$client->id} should be able to approve UI for their own project {$project->id}");
                    } else {
                        // Other clients should NOT be able to approve UI
                        $this->assertFalse($canApproveUI,
                            "Client {$client->id} should NOT be able to approve UI for project {$project->id} owned by client {$project->client_id}");
                    }
                }

                // Test that admin and super-admin can approve UI
                $this->assertTrue($policy->approveUI($admin, $project),
                    'Admin should be able to approve UI for any project');
                $this->assertTrue($policy->approveUI($superAdmin, $project),
                    'Super-admin should be able to approve UI for any project');

                // Test that employee cannot approve UI (not mentioned in requirements)
                // Based on the policy implementation, employees cannot approve UI
                $this->assertFalse($policy->approveUI($employee, $project),
                    'Employee should NOT be able to approve UI');
            });
    }
}
