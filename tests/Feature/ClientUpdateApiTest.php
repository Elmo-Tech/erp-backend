<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_an_existing_client_preserves_its_email_and_persists_website_and_city(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('manage_clients', 'web'));
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/clients/{$client->id}", [
                'name' => $client->name,
                'email' => $client->email,
                'website' => 'https://updated.example.com',
                'city' => 'San Francisco',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.website', 'https://updated.example.com')
            ->assertJsonPath('data.city', 'San Francisco');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'email' => $client->email,
            'website' => 'https://updated.example.com',
            'city' => 'San Francisco',
        ]);

        foreach (['San Francisco', 'updated.example.com'] as $search) {
            $this->getJson('/api/v1/clients?'.http_build_query(['filter' => ['search' => $search]]))
                ->assertOk()
                ->assertJsonCount(1, 'data.clients')
                ->assertJsonPath('data.clients.0.city', 'San Francisco')
                ->assertJsonPath('data.clients.0.website', 'https://updated.example.com');
        }
    }

    public function test_client_update_rejects_another_clients_email(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('manage_clients', 'web'));
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/clients/{$client->id}", ['email' => $otherClient->email])
            ->assertUnprocessable();

        $this->assertSame($client->email, $client->fresh()->email);
    }
}
