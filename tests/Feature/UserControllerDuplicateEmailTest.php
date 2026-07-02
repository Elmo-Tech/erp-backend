<?php

namespace Tests\Feature;

use App\Models\LookupOption;
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

    public function test_user_can_be_created_with_dynamic_lookup_specialization(): void
    {
        Permission::firstOrCreate(['name' => 'manage_users']);
        LookupOption::create([
            'type' => 'specialization',
            'value' => 'backend_dev',
            'label' => 'Backend Dev',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_users');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/users/manage', [
            'name' => 'Backend User',
            'email' => 'backend@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'specialization' => 'backend_dev',
            'roles' => [],
        ])->assertCreated()
            ->assertJsonPath('data.user.specialization', 'backend_dev')
            ->assertJsonPath('data.user.specializationLabel', 'Backend Dev');

        $this->assertDatabaseHas('users', [
            'email' => 'backend@example.com',
            'specialization' => 'backend_dev',
        ]);
    }
}
