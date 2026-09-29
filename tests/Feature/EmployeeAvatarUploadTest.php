<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_requires_employee_management_permission(): void
    {
        Storage::fake('public');
        $actor = User::factory()->create();
        $employee = User::factory()->create(['is_employee' => true]);

        $this->actingAs($actor, 'sanctum')
            ->post("/api/v1/employees/{$employee->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull($employee->fresh()->getRawOriginal('avatar'));
    }

    public function test_avatar_upload_validates_the_image_type_and_size(): void
    {
        Storage::fake('public');
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);
        $url = "/api/v1/employees/{$employee->id}/avatar";

        $this->actingAs($manager, 'sanctum')
            ->post($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->actingAs($manager, 'sanctum')
            ->post($url, ['avatar' => UploadedFile::fake()->image('avatar.gif')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->actingAs($manager, 'sanctum')
            ->post($url, ['avatar' => UploadedFile::fake()->image('avatar.png')->size(5121)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_employee_manager_can_upload_an_avatar_and_receives_the_resource_url(): void
    {
        Storage::fake('public');
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);

        $response = $this->actingAs($manager, 'sanctum')
            ->post("/api/v1/employees/{$employee->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk();

        $path = $employee->fresh()->getRawOriginal('avatar');

        $this->assertStringStartsWith("employees/{$employee->id}/avatars/", $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.userId', $employee->id)
            ->assertJsonPath('data.avatar', Storage::disk('public')->url($path));
    }

    public function test_avatar_replacement_only_deletes_the_previous_owned_avatar(): void
    {
        Storage::fake('public');
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);
        $ownedPath = "employees/{$employee->id}/avatars/old-avatar.jpg";
        $unownedPath = 'avatars/unrelated-avatar.jpg';
        Storage::disk('public')->put($ownedPath, 'old avatar');
        Storage::disk('public')->put($unownedPath, 'unrelated avatar');

        $employee->update(['avatar' => $ownedPath]);

        $this->actingAs($manager, 'sanctum')
            ->post("/api/v1/employees/{$employee->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('first-avatar.jpg'),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($ownedPath);

        $employee->update(['avatar' => $unownedPath]);

        $this->actingAs($manager, 'sanctum')
            ->post("/api/v1/employees/{$employee->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('second-avatar.jpg'),
            ])
            ->assertOk();

        Storage::disk('public')->assertExists($unownedPath);
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }
}
