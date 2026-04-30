<?php

namespace Tests\Feature\Client;

use App\Modules\Client\Models\Client;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientMeetingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_an_empty_meetings_list_for_a_client_without_meetings(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Acme',
            'primary_email' => 'acme@example.com',
            'lifecycle_stage' => 'client',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/clients/{$client->id}/meetings");

        $response->assertOk()
            ->assertJsonPath('data.meetings', []);
    }
}
