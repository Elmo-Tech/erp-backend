<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SimplifiedRolePermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_simplified_roles_receive_only_operational_default_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Role::findByName('admin');
        $manager = Role::findByName('manager');
        $developer = Role::findByName('developer');
        $client = Role::findByName('client');
        $superAdmin = Role::findByName('super-admin');

        $hiddenPermissions = [
            'view_leads',
            'create_leads',
            'view_quotations',
            'calculate_quotations',
            'view_contracts',
            'view_invoices',
            'send_invoices',
            'view_own_invoices',
            'pay_invoices',
            'approve_project_ui',
            'approve_ui_deliverables',
            'view_points',
            'manage_points',
            'manage_user_points',
            'view_performance_evaluations',
            'view_bonuses',
            'view_all_code_reviews',
            'manage_code_reviews',
            'view_all_innovation_logs',
            'verify_innovation_logs',
            'view_profitability',
            'view_cost',
            'view_internal_costs',
            'manage_internal_costs',
            'view_quality_metrics',
            'view_user_performance',
            'view_employee_salary',
            'create_milestone_invoice',
            'mark_milestone_paid',
            'manage_deliverables',
        ];

        foreach ([$admin, $manager, $developer, $client] as $role) {
            foreach ($hiddenPermissions as $permission) {
                if (! Permission::where('name', $permission)->exists()) {
                    $this->assertDatabaseMissing('permissions', ['name' => $permission]);

                    continue;
                }

                $this->assertFalse(
                    $role->hasPermissionTo($permission),
                    "{$role->name} should not have hidden permission {$permission}"
                );
            }
        }

        foreach ([
            'manage_users',
            'assign_user_roles',
            'manage_roles',
            'manage_clients',
            'manage_projects',
            'manage_project_workflows',
            'manage_tasks',
            'manage_attendance',
            'view_admin_stats',
        ] as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission));
        }

        foreach (['view_clients', 'view_projects', 'manage_tasks', 'view_all_time_entries', 'view_all_attendance', 'view_reports', 'manage_employees'] as $permission) {
            $this->assertTrue($manager->hasPermissionTo($permission));
        }
        $this->assertFalse($manager->hasPermissionTo('view_all_projects'));

        foreach (['view_projects', 'view_tasks', 'edit_tasks', 'start_timer', 'stop_timer', 'view_attendance'] as $permission) {
            $this->assertTrue($developer->hasPermissionTo($permission));
        }

        $this->assertSame(['view_dashboard', 'view_own_projects'], $client->permissions->pluck('name')->sort()->values()->all());
        foreach ([
            'manage_users',
            'manage_roles',
            'assign_user_roles',
            'manage_protected_users',
            'manage_project_workflows',
            'manage_workflow_templates',
        ] as $permission) {
            $this->assertTrue($superAdmin->hasPermissionTo($permission));
        }

        $this->assertFalse($admin->hasPermissionTo('manage_protected_users'));
        $this->assertFalse($admin->hasPermissionTo('manage_workflow_templates'));
        $this->assertDatabaseHas('workflow_templates', [
            'name' => 'Standard Delivery Workflow',
            'is_default' => true,
        ]);
        $this->assertDatabaseCount('workflow_template_stages', 7);
        $this->assertDatabaseHas('lookup_options', [
            'type' => 'task_status',
            'value' => 'on_hold',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('lookup_options', [
            'type' => 'specialization',
            'value' => 'backend',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('lookup_options', [
            'type' => 'task_category',
            'value' => 'general',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@thetables.com',
            'name' => 'The Tables (Super Admin)',
        ]);
        $defaultAccount = User::where('email', 'admin@thetables.com')->firstOrFail();
        $this->assertTrue($defaultAccount->hasRole('super-admin'));
        $this->assertFalse($defaultAccount->hasRole('admin'));
        $this->assertDatabaseMissing('users', ['email' => 'm.saber@elmotech.it']);
    }
}
