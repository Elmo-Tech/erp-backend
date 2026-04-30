<?php

namespace Tests\Feature\Client;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_client_from_add_client_payload(): void
    {
        $user = User::factory()->create();

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
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/clients', [
                'type' => 'company',
                'primaryEmail' => 'hello@acme.test',
                'country' => 'Egypt',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.displayName.0', 'Display name is required.');
    }
}
