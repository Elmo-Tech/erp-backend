<?php

namespace Tests\Feature\HR;

use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeProfilePermissionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_employees_grants_read_only_profile_access(): void
    {
        $viewer = $this->userWithPermissions(['view_employees']);
        $profile = $this->employeeProfile(User::factory()->create());

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/hr/employee-profiles')
            ->assertOk()
            ->assertJsonMissingPath('data.0.baseSalary')
            ->assertJsonMissingPath('data.0.hourlyRate');
        $this->getJson("/api/v1/hr/employee-profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.baseSalary')
            ->assertJsonMissingPath('data.hourlyRate');
        $this->postJson('/api/v1/hr/employee-profiles', $this->profilePayload(User::factory()->create()))
            ->assertForbidden();
    }

    public function test_salary_permission_reveals_profile_compensation_fields(): void
    {
        $viewer = $this->userWithPermissions(['view_employees', 'view_employee_salary']);
        $profile = $this->employeeProfile(User::factory()->create());

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/hr/employee-profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.baseSalary', '8000.00')
            ->assertJsonPath('data.hourlyRate', '50.00');
    }

    public function test_create_employees_grants_creation_for_an_ordinary_target_only(): void
    {
        $creator = $this->userWithPermissions(['create_employees']);
        $ordinaryTarget = User::factory()->create();
        $protectedTarget = $this->userWithPermissions(['protected_user_account']);

        $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/hr/employee-profiles', $this->profilePayload($ordinaryTarget))
            ->assertForbidden();

        $creator->givePermissionTo(Permission::findOrCreate('view_employee_salary', 'web'));
        $this->postJson('/api/v1/hr/employee-profiles', $this->profilePayload($ordinaryTarget))
            ->assertCreated();
        $this->postJson('/api/v1/hr/employee-profiles', $this->profilePayload($protectedTarget))
            ->assertForbidden();

        $this->assertDatabaseHas('employee_profiles', ['user_id' => $ordinaryTarget->id]);
        $this->assertDatabaseMissing('employee_profiles', ['user_id' => $protectedTarget->id]);
    }

    public function test_edit_employees_updates_ordinary_profiles_but_not_protected_peers(): void
    {
        $editor = $this->userWithPermissions(['edit_employees']);
        $ordinaryProfile = $this->employeeProfile(User::factory()->create());
        $protectedProfile = $this->employeeProfile(
            $this->userWithPermissions(['protected_user_account'])
        );

        $this->actingAs($editor, 'sanctum')
            ->putJson("/api/v1/hr/employee-profiles/{$ordinaryProfile->id}", ['join_date' => '2025-02-01'])
            ->assertOk();
        $this->putJson("/api/v1/hr/employee-profiles/{$ordinaryProfile->id}", ['hourly_rate' => 90])
            ->assertForbidden();

        $editor->givePermissionTo(Permission::findOrCreate('view_employee_salary', 'web'));
        $this->putJson("/api/v1/hr/employee-profiles/{$ordinaryProfile->id}", ['hourly_rate' => 90])
            ->assertOk();
        $this->putJson("/api/v1/hr/employee-profiles/{$protectedProfile->id}", ['hourly_rate' => 90])
            ->assertForbidden();
        $this->deleteJson("/api/v1/hr/employee-profiles/{$ordinaryProfile->id}")
            ->assertForbidden();

        $this->assertSame('90.00', $ordinaryProfile->fresh()->hourly_rate);
        $this->assertSame('2025-02-01', $ordinaryProfile->fresh()->join_date->toDateString());
        $this->assertSame('50.00', $protectedProfile->fresh()->hourly_rate);
    }

    public function test_delete_employees_deletes_ordinary_profiles_but_not_protected_peers(): void
    {
        $deleter = $this->userWithPermissions(['delete_employees']);
        $ordinaryProfile = $this->employeeProfile(User::factory()->create());
        $protectedProfile = $this->employeeProfile(
            $this->userWithPermissions(['protected_user_account'])
        );

        $this->actingAs($deleter, 'sanctum')
            ->deleteJson("/api/v1/hr/employee-profiles/{$ordinaryProfile->id}")
            ->assertOk();
        $this->deleteJson("/api/v1/hr/employee-profiles/{$protectedProfile->id}")
            ->assertForbidden();

        $this->assertSoftDeleted($ordinaryProfile);
        $this->assertNotSoftDeleted($protectedProfile);
    }

    public function test_manage_employees_is_the_employee_profile_crud_umbrella(): void
    {
        $manager = $this->userWithPermissions(['manage_employees']);
        $target = User::factory()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hr/employee-profiles', $this->profilePayload($target))
            ->assertCreated();
        $profileId = $response->json('data.id');

        $this->getJson('/api/v1/hr/employee-profiles')->assertOk();
        $this->getJson("/api/v1/hr/employee-profiles/{$profileId}")->assertOk();
        $this->putJson("/api/v1/hr/employee-profiles/{$profileId}", ['hourly_rate' => 75])
            ->assertOk();
        $this->deleteJson("/api/v1/hr/employee-profiles/{$profileId}")->assertOk();

        $this->assertSoftDeleted('employee_profiles', ['id' => $profileId]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function employeeProfile(User $user): EmployeeProfile
    {
        return EmployeeProfile::create($this->profilePayload($user));
    }

    /**
     * @return array<string, int|string>
     */
    private function profilePayload(User $user): array
    {
        return [
            'user_id' => $user->id,
            'base_salary' => 8000,
            'hourly_rate' => 50,
            'join_date' => '2025-01-01',
        ];
    }
}
