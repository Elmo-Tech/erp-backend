<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_client_from_add_client_payload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('create_clients');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/clients', [
                'type' => 'company',
                'displayName' => 'Acme Inc.',
                'primaryEmail' => 'hello@acme.test',
                'primaryPhone' => '+20 100 000 0000',
                'companyName' => 'Acme Inc.',
                'country' => 'Egypt',
                'status' => 'active',
                'leadSource' => 'website',
                'interests' => ['web-development'],
                'accountManagerId' => null,
                'contactPerson' => [
                    'firstName' => 'Jane',
                    'lastName' => 'Cooper',
                    'displayName' => 'Jane Cooper',
                    'email' => 'jane@acme.test',
                    'phone' => '+20 100 000 0001',
                    'isPrimary' => true,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.displayName', 'Acme Inc.')
            ->assertJsonPath('data.primaryEmail', 'hello@acme.test')
            ->assertJsonPath('data.companyName', 'Acme Inc.')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.metadata.interests.0', 'web-development');

        $this->assertDatabaseHas('clients', [
            'display_name' => 'Acme Inc.',
            'primary_email' => 'hello@acme.test',
            'company_name' => 'Acme Inc.',
            'country' => 'Egypt',
            'status' => 'active',
            'source' => 'website',
        ]);

        $this->assertDatabaseHas('contacts', [
            'display_name' => 'Jane Cooper',
            'email' => 'jane@acme.test',
            'is_primary' => true,
        ]);
    }

    public function test_it_rejects_payload_without_any_display_name(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('create_clients');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/clients', [
                'type' => 'company',
                'primaryEmail' => 'hello@acme.test',
                'country' => 'Egypt',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.displayName.0', 'Display name is required.');
    }

    public function test_it_rejects_client_creation_without_create_or_manage_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/clients', [
                'type' => 'company',
                'displayName' => 'Acme Inc.',
                'primaryEmail' => 'hello@acme.test',
                'country' => 'Egypt',
            ])
            ->assertForbidden();
    }

    public function test_it_returns_lightweight_client_directory_rows(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('view_clients', 'web'));

        $client = Client::factory()->create([
            'type' => 'company',
            'display_name' => 'Legacy Display Name',
            'company_name' => 'Acme Operations',
            'primary_email' => 'ops@acme.test',
            'primary_phone' => '+20 100 000 0000',
            'position' => 'Procurement',
            'status' => 'active',
            'country' => 'Egypt',
            'notes' => 'This note must not be returned in the directory payload.',
        ]);

        Project::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/clients');

        $response->assertOk()
            ->assertJsonPath('data.clients.0.display_name', 'Acme Operations')
            ->assertJsonPath('data.clients.0.email', 'ops@acme.test')
            ->assertJsonPath('data.clients.0.projects_count', 2)
            ->assertJsonMissingPath('data.clients.0.contacts')
            ->assertJsonMissingPath('data.clients.0.accountManager')
            ->assertJsonMissingPath('data.clients.0.user')
            ->assertJsonMissingPath('data.clients.0.projects')
            ->assertJsonMissingPath('data.clients.0.notes');

        $executedSql = collect(DB::getQueryLog())->pluck('query')->implode("\n");

        $this->assertStringNotContainsString('"contacts"', $executedSql);
        $this->assertStringNotContainsString('"users"', $executedSql);
    }
}
