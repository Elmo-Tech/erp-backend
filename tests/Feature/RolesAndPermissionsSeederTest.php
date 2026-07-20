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

    public function test_operational_roles_have_explicit_client_directory_visibility(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['founder', 'admin', 'pmo', 'manager'] as $roleName) {
            $this->assertTrue(
                Role::findByName($roleName)->hasPermissionTo('view_clients'),
                "{$roleName} should have client directory visibility"
            );
        }

        foreach (['techdev', 'qa', 'dev', 'client'] as $roleName) {
            $this->assertFalse(
                Role::findByName($roleName)->hasPermissionTo('view_clients'),
                "{$roleName} should not have internal client directory visibility"
            );
        }
    }

    public function test_blocker_mutations_and_company_team_pulse_are_separate_capabilities(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['founder', 'admin'] as $roleName) {
            $role = Role::findByName($roleName);

            $this->assertTrue($role->hasPermissionTo('view_team_pulse'));
            $this->assertTrue($role->hasPermissionTo('escalate_blockers'));
            $this->assertTrue($role->hasPermissionTo('resolve_blockers'));
        }

        $pmo = Role::findByName('pmo');
        $this->assertTrue($pmo->hasPermissionTo('escalate_blockers'));
        $this->assertFalse($pmo->hasPermissionTo('resolve_blockers'));
        $this->assertFalse($pmo->hasPermissionTo('view_team_pulse'));

        $manager = Role::findByName('manager');
        $this->assertTrue($manager->hasPermissionTo('manage_team_timers'));
        $this->assertFalse($manager->hasPermissionTo('view_team_pulse'));
        $this->assertFalse($manager->hasPermissionTo('escalate_blockers'));
        $this->assertFalse($manager->hasPermissionTo('resolve_blockers'));
    }

    public function test_existing_internal_note_writers_receive_the_separate_create_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['founder', 'pmo', 'manager', 'techdev'] as $roleName) {
            $role = Role::findByName($roleName);

            $this->assertTrue($role->hasPermissionTo('view_internal_notes'), $roleName.' must retain note reads');
            $this->assertTrue($role->hasPermissionTo('create_internal_notes'), $roleName.' must retain note writes');
        }
    }
}
