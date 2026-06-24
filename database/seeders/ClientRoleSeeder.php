<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ClientRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Note: This seeder adds additional client-specific permissions
     * that are not included in AdminUserSeeder.
     */
    public function run(): void
    {
        // Client portal permissions for simplified project visibility.
        $clientSpecificPermissions = [
            'view_own_projects',
        ];

        foreach ($clientSpecificPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Get or create client role (may already exist from AdminUserSeeder)
        $clientRole = Role::firstOrCreate([
            'name' => 'client',
            'guard_name' => 'web',
        ]);

        $clientRole->syncPermissions($clientSpecificPermissions);

        $this->command->info('Client role simplified permissions created successfully.');
    }
}
