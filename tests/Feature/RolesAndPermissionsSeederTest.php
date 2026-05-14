<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_permissions_separate_workspace_from_company_analytics(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Permission::where('name', 'view_my_workspace')->exists());
        $this->assertTrue(Permission::where('name', 'view_global_stats')->exists());

        foreach (['founder', 'admin', 'pmo', 'manager', 'techdev', 'qa', 'dev', 'client'] as $roleName) {
            $this->assertTrue(
                Role::findByName($roleName)->hasPermissionTo('view_my_workspace'),
                "{$roleName} should have workspace access"
            );
        }

        foreach (['founder', 'admin'] as $roleName) {
            $this->assertTrue(
                Role::findByName($roleName)->hasPermissionTo('view_global_stats'),
                "{$roleName} should have company analytics access"
            );
        }

        foreach (['pmo', 'manager', 'techdev', 'qa', 'dev', 'client'] as $roleName) {
            $this->assertFalse(
                Role::findByName($roleName)->hasPermissionTo('view_global_stats'),
                "{$roleName} should not have company analytics access"
            );
        }
    }
}
