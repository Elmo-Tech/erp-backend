<?php

namespace Tests\Feature;

use App\Modules\Settings\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_roles_before_assigning_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Role::where('name', 'founder')->where('guard_name', 'web')->exists());
        $this->assertTrue(
            User::where('email', 'founder@elmotech.it')->firstOrFail()->hasRole('founder')
        );
    }
}
