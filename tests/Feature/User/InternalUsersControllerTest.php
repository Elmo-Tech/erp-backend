<?php

namespace Tests\Feature\User;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalUsersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_lightweight_active_internal_users_for_dropdowns(): void
    {
        $requester = User::factory()->create([
            'is_active' => true,
            'is_employee' => true,
        ]);

        $activeEmployee = User::factory()->create([
            'name' => 'Active Employee',
            'email' => 'active.employee@example.com',
            'is_active' => true,
            'is_employee' => true,
        ]);

        $inactiveEmployee = User::factory()->create([
            'name' => 'Inactive Employee',
            'is_active' => false,
            'is_employee' => true,
        ]);

        $clientUser = User::factory()->create([
            'name' => 'Client User',
            'is_active' => true,
            'is_employee' => false,
        ]);

        $response = $this->actingAs($requester, 'sanctum')
            ->getJson('/api/v1/internal-users');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $activeEmployee->id,
                'name' => 'Active Employee',
                'email' => 'active.employee@example.com',
                'role' => null,
            ])
            ->assertJsonMissing(['name' => $inactiveEmployee->name])
            ->assertJsonMissing(['name' => $clientUser->name]);
    }
}
