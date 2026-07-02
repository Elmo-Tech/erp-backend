<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserControllerDuplicateEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_user_email_returns_validation_error_without_creating_user(): void
    {
        Permission::firstOrCreate(['name' => 'manage_users']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_users');
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/users/manage', [
            'name' => 'Duplicate User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'specialization' => 'qa',
            'roles' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }
}
