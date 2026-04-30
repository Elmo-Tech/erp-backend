<?php

namespace Tests\Unit\Scopes;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Scopes\ClientScope;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
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

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'client']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'employee']);
    }

    /**
     * Property 2: ClientScope Data Isolation - Project Filtering
     *
     * For any authenticated client user and for any query on Project model,
     * the results SHALL only include records where the project's client_id
     * matches the authenticated user's id.
     *
     * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
     */
    public function test_client_scope_filters_projects_by_client_id(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 4), // Number of clients
                Generator\choose(1, 3)   // Number of projects per client
            )
            ->then(function ($numClients, $projectsPerClient) {
                // Clear any existing data
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

                // Create multiple clients
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];

                // Assign client role to all users
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create projects for each client
                $allProjects = collect();
                foreach ($clients as $client) {
                    $clientProjects = Project::factory()
                        ->count($projectsPerClient)
                        ->create(['client_id' => $clientProfiles[$client->id]->id]);
                    $allProjects = $allProjects->merge($clientProjects);
                }

                // Test each client can only see their own projects
                foreach ($clients as $client) {
                    Auth::login($client);

                    // Use the actual model query which should have the scope applied
                    $visibleProjects = Project::all();

                    // Assert all visible projects belong to the authenticated client
                    foreach ($visibleProjects as $project) {
                        $this->assertEquals($clientProfiles[$client->id]->id, $project->client_id);
                    }

                    // Assert the client can see exactly their own projects
                    $expectedCount = $allProjects->where('client_id', $clientProfiles[$client->id]->id)->count();
                    $this->assertEquals($expectedCount, $visibleProjects->count());
                }

                Auth::logout();
            });
    }

    /**
     * Property 2: ClientScope Data Isolation - Invoice Filtering
     *
     * For any authenticated client user and for any query on Invoice model,
     * the results SHALL only include invoices belonging to the client's projects.
     *
     * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
     */
    public function test_client_scope_filters_invoices_by_project_client_id(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 3), // Number of clients
                Generator\choose(1, 2)  // Number of invoices per client
            )
            ->then(function ($numClients, $invoicesPerClient) {
                // Clear any existing data
                Invoice::withoutGlobalScopes()->delete();
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

                // Create multiple clients
                $clients = User::factory()->count($numClients)->create();
                $clientProfiles = [];

                // Assign client role to all users
                foreach ($clients as $client) {
                    $client->assignRole('client');
                    $clientProfiles[$client->id] = $this->ensureClientProfile($client);
                }

                // Create projects and invoices for each client
                $allInvoices = collect();
                foreach ($clients as $index => $client) {
                    $project = Project::factory()->create(['client_id' => $clientProfiles[$client->id]->id]);

                    for ($i = 0; $i < $invoicesPerClient; $i++) {
                        // Generate unique invoice numbers to avoid conflicts
                        $invoiceNumber = 'INV-'.now()->year.'-'.uniqid().'-'.str_pad(($index * $invoicesPerClient + $i + 1), 6, '0', STR_PAD_LEFT);

                        $invoice = Invoice::factory()->create([
                            'project_id' => $project->id,
                            'invoice_number' => $invoiceNumber,
                        ]);
                        $allInvoices->push($invoice);
                    }
                }

                // Test each client can only see invoices for their own projects
                foreach ($clients as $client) {
                    Auth::login($client);

                    // Use the actual model query which should have the scope applied
                    $visibleInvoices = Invoice::all();

                    // Assert all visible invoices belong to projects owned by the authenticated client
                    foreach ($visibleInvoices as $invoice) {
                        $this->assertEquals($clientProfiles[$client->id]->id, $invoice->project->client_id);
                    }

                    // Assert the client can see exactly their own invoices
                    $expectedInvoices = $allInvoices->filter(function ($invoice) use ($client) {
                        // Reload the invoice to ensure project relationship is available
                        $freshInvoice = Invoice::withoutGlobalScopes()->with('project')->find($invoice->id);

                        return $freshInvoice && $freshInvoice->project && $freshInvoice->project->client_id === $this->ensureClientProfile($client)->id;
                    });
                    $this->assertEquals($expectedInvoices->count(), $visibleInvoices->count());
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
                Generator\choose(2, 3), // Number of clients
                Generator\choose(1, 2)  // Number of projects per client
            )
            ->then(function ($numClients, $projectsPerClient) {
                // Clear any existing data
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['client', 'admin']);
                })->delete();

                // Create multiple clients and their projects
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

                // Create admin user
                $admin = User::factory()->create();
                $admin->assignRole('admin');

                // Test admin can see all projects
                Auth::login($admin);
                $adminVisibleProjects = Project::all();
                $this->assertEquals($totalProjects, $adminVisibleProjects->count());

                Auth::logout();
            });
    }

    /**
     * Property 2: ClientScope Data Isolation - Unauthenticated Access
     *
     * For any unauthenticated request, the scope SHALL not apply any filtering
     * (returns all records, letting other middleware handle authentication).
     *
     * **Validates: Requirements 2.3**
     */
    public function test_client_scope_does_not_apply_when_unauthenticated(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(1, 3) // Number of projects
            )
            ->then(function ($numProjects) {
                // Clear any existing data
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

                // Ensure no user is authenticated
                Auth::logout();

                // Create some projects
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

                // Query projects when unauthenticated
                $visibleProjects = Project::all();

                // Should see all projects (no filtering applied)
                $this->assertEquals($totalProjects, $visibleProjects->count());
            });
    }

    /**
     * Property 2: ClientScope Data Isolation - 404 Behavior
     *
     * For any client attempting to access a project not belonging to them,
     * the system SHALL return 404 Not Found (not 403, to prevent enumeration).
     * This is tested by ensuring the scope filters out inaccessible records.
     *
     * **Validates: Requirements 2.5**
     */
    public function test_client_scope_prevents_access_to_other_clients_projects(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2, 3) // Number of clients
            )
            ->then(function ($numClients) {
                // Clear any existing data
                Project::withoutGlobalScopes()->delete();
                User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->delete();

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

                // Test that each client can only access their own project
                foreach ($clients as $index => $client) {
                    Auth::login($client);

                    // Try to find other clients' projects - should return empty
                    foreach ($projects as $projectIndex => $project) {
                        if ($projectIndex !== $index) {
                            // This project belongs to another client
                            $foundProject = Project::where('id', $project->id)->first();
                            $this->assertNull($foundProject,
                                "Client {$client->id} should not be able to access project {$project->id} owned by client {$project->client_id}");
                        }
                    }

                    // But should be able to find their own project
                    $ownProject = $projects[$index];
                    $foundOwnProject = Project::where('id', $ownProject->id)->first();
                    $this->assertNotNull($foundOwnProject,
                        "Client {$client->id} should be able to access their own project {$ownProject->id}");
                }

                Auth::logout();
            });
    }
}
