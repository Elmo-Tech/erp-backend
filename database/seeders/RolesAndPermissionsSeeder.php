<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Exhaustive PBAC seeder — every permission maps 1-to-1 with a UI action
 * or data-access gate. Re-running is fully idempotent (firstOrCreate).
 *
 * Run with:
 *   php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─────────────────────────────────────────────────────────────────────
        // LEGACY PERMISSIONS CLEANUP
        // These names came from older one-off seeders and no longer map to an
        // active route, policy, or frontend PBAC gate. Keep this list explicit:
        // it is safer than deleting every permission not in the catalogue.
        // ─────────────────────────────────────────────────────────────────────
        $legacyPermissions = [
            'manage_deliverables',
            'view_lead_conversion_status',
            'manage_client_portal_access',
            'view_points',
            'manage_points',
            'award_points',
            'deduct_points',
            'view_point_transactions',
            'view_own_projects',
            'view_own_invoices',
            'approve_ui_deliverables',
            'pay_invoices',
            'create_milestone_invoice',
            'mark_milestone_paid',
            'link_milestone_invoice',
            'view_milestones',
            'create_milestones',
            'edit_milestones',
            'manage_milestones',
            'edit_attendance',
            'view_change_requests',
            'create_change_requests',
            'approve_change_requests',
            'reject_change_requests',
            'manage_test_scenarios',
            'manage_sales',
            'view_performance_evaluations',
            'manage_performance_evaluations',
            'approve_performance_evaluations',
            'view_bonuses',
            'view_all_code_reviews',
            'manage_code_reviews',
            'view_all_innovation_logs',
            'verify_innovation_logs',
            'manage_innovation_logs',
            'manage_user_points',
            'create_main_tasks',
            'create_weekly_tasks',
            'create_subtasks',
            'edit_main_tasks',
            'edit_weekly_tasks',
            'edit_subtasks',
            'manage_task_team',
            'add_deliverables',
            'complete_task',
            'edit_task_status',
            'delete_project_task_deliverables',
            'manage_task_test_scenarios',
            'view_test_scenarios',
            'create_test_scenarios',
            'complete_test_scenarios',
            'verify_test_scenarios',
        ];

        $removedLegacyCount = Permission::whereIn('name', $legacyPermissions)->delete();
        Role::whereIn('name', ['super-admin', 'admin', 'manager', 'tech_lead', 'developer'])->delete();

        // ─────────────────────────────────────────────────────────────────────
        // PERMISSION CATALOGUE
        // ─────────────────────────────────────────────────────────────────────
        $permissions = [

            // ── Dashboard ────────────────────────────────────────────────────
            'view_dashboard',
            'view_dashboard_overview',
            'view_financial_stats',
            'view_project_timeline',
            'view_team_performance',
            'view_personal_tasks',
            'view_all_blockers',
            'view_escalated_blockers',
            'view_team_pulse',

            // ── User Management ──────────────────────────────────────────────
            'manage_users',
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // ── Role & Permission Management ─────────────────────────────────
            'manage_roles',
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // ── Client Management ────────────────────────────────────────────
            'manage_clients',
            'view_clients',
            'create_clients',
            'edit_clients',
            'delete_clients',

            // ── Project Management ───────────────────────────────────────────
            'manage_projects',
            'view_projects',
            'view_all_projects',       // see every project regardless of membership
            'view_assigned_projects',  // see only projects the user is a member of
            'manage_all_projects',     // edit/delete any project regardless of membership
            'create_projects',
            'edit_projects',
            'delete_projects',
            'approve_project_ui',
            'view_project_budget',
            'manage_project_settings',
            'manage_change_requests',
            'delete_change_requests',
            'delete_milestones',

            // ── Project Members ──────────────────────────────────────────────
            'manage_project_members',
            'view_project_members',
            'add_project_members',
            'remove_project_members',

            // ── Project Files ────────────────────────────────────────────────
            'manage_project_files',
            'view_project_files',
            'upload_project_files',
            'delete_project_files',
            'view_project_history',

            // ── Task Management ──────────────────────────────────────────────
            'manage_tasks',
            'view_tasks',
            'create_tasks',
            'edit_tasks',
            'delete_tasks',
            'assign_tasks',
            'view_internal_notes',
            'work_on_tasks',

            // ── Sales — Leads ────────────────────────────────────────────────
            'manage_leads',
            'view_leads',
            'create_leads',
            'edit_leads',
            'delete_leads',
            'convert_leads',

            // ── Sales — Quotations ───────────────────────────────────────────
            'view_quotations',
            'create_quotations',
            'edit_quotations',
            'delete_quotations',
            'calculate_quotations',

            // ── Sales — Contracts ────────────────────────────────────────────
            'manage_contracts',
            'view_contracts',
            'create_contracts',
            'edit_contracts',
            'delete_contracts',
            'sign_contracts',

            // ── Invoice Management ───────────────────────────────────────────
            'manage_invoices',
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',
            'send_invoices',
            'cancel_invoices',

            // ── Wiki Management ──────────────────────────────────────────────
            'manage_wiki',
            'view_wiki',
            'create_wiki',
            'edit_wiki',
            'delete_wiki',
            'view_encrypted_content',

            // ── Time Tracking ────────────────────────────────────────────────
            'manage_time_entries',
            'view_time_entries',
            'start_timer',
            'stop_timer',
            'view_all_time_entries',
            'edit_time_entries',
            'delete_time_entries',
            'log_time',

            // ── Attendance ───────────────────────────────────────────────────
            'manage_attendance',
            'view_attendance',
            'view_all_attendance',
            'clock_out',

            // ── Shifts ──────────────────────────────────────────────────────
            'manage_shifts',    // HR admins: full CRUD on work shifts

            // ── Employee Management ──────────────────────────────────────────
            'manage_employees',
            'view_employees',
            'create_employees',
            'edit_employees',
            'view_employee_salary',
            'view_employee_performance',

            // ── SOP Management ───────────────────────────────────────────────
            'manage_sops',
            'view_morning_sop',
            'submit_morning_sop',

            // ── Meetings ─────────────────────────────────────────────────────
            'schedule_meetings',
            'view_meetings',
            'delete_meetings',

            // ── Client Portal ────────────────────────────────────────────────
            // access_client_portal  → assigned to the 'client' role; gates the
            //                         /client/* routes and ClientScope.
            // The three management permissions are for internal staff who
            // administer portal accounts on behalf of clients.
            'access_client_portal',
            'create_client_portal_access',
            'revoke_client_portal_access',
            'reset_client_portal_password',

            // ── Reports & Analytics ──────────────────────────────────────────
            'view_reports',
            'view_profitability',
            'view_cost',
            'view_quality_metrics',
            'view_user_performance',
            'view_attendance_analytics',
            'view_admin_stats',

            // ── Financial Data ───────────────────────────────────────────────
            'view_internal_costs',
            'manage_internal_costs',

            // ── Payroll ──────────────────────────────────────────────────────
            'manage_payroll',   // HR admins: generate, approve, mark-paid, delete drafts
            'view_payroll',     // Employees: read-only access to own payslips

            // ── Settings ─────────────────────────────────────────────────────
            'view_settings',
            'manage_settings',
            'delete_settings',

            // ── Security & Audit ─────────────────────────────────────────────
            'view_audit_logs',

            // ── Tags ─────────────────────────────────────────────────────────
            'manage_tags',
            'view_tags',

            // ── Finance — Invoices (Phase 4) ──────────────────────────────────
            'manage_invoices',       // already present above; kept here for clarity

            // ── Finance — SaaS Subscriptions (Phase 4) ───────────────────────
            'manage_subscriptions',  // full CRUD on internal SaaS subscriptions

            // ── Calendar (Phase 4) ────────────────────────────────────────────
            'view_calendar',         // access the master calendar aggregator
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // ROLE DEFINITIONS
        // Internal roles are founder, pmo, techdev, and dev. The client role is
        // a portal-only technical role used by ClientPortalAccessController.
        // ─────────────────────────────────────────────────────────────────────
        $founderPermissions = array_values(array_diff($permissions, [
            'access_client_portal',
        ]));

        $roles = [

            // ── Founder ──────────────────────────────────────────────────────
            // Business owner: all internal capabilities, not client-portal mode.
            'founder' => $founderPermissions,

            // ── PMO ──────────────────────────────────────────────────────────
            // Project oversight and planning — no financial data.
            'pmo' => [
                'view_dashboard', 'view_dashboard_overview',
                'view_project_timeline', 'view_all_blockers',
                'view_morning_sop', 'submit_morning_sop',
                'view_projects', 'view_all_projects', 'create_projects',
                'view_project_members', 'add_project_members', 'remove_project_members',
                'view_project_files', 'upload_project_files',
                'view_project_history',
                'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks', 'assign_tasks',
                'view_internal_notes',
                'manage_change_requests',
                'schedule_meetings', 'view_meetings',
                'view_leads',
                'view_reports', 'view_quality_metrics', 'view_user_performance',
                'view_attendance_analytics',
                'view_users',
                'view_tags',
                'create_client_portal_access',
                'revoke_client_portal_access',
                'reset_client_portal_password',
                'view_calendar',
            ],

            // ── TechDev ──────────────────────────────────────────────────────
            // Technical lead/developer hybrid: execution and team delivery.
            'techdev' => [
                'view_dashboard', 'view_dashboard_overview',
                'view_team_performance', 'view_project_timeline',
                'view_projects', 'view_assigned_projects',
                'view_project_members',
                'view_project_files', 'upload_project_files',
                'view_project_history',
                'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks', 'delete_tasks',
                'assign_tasks', 'view_internal_notes', 'work_on_tasks',
                'view_wiki', 'create_wiki', 'edit_wiki',
                'view_time_entries', 'start_timer', 'stop_timer',
                'view_attendance', 'clock_out',
                'view_payroll',
                'view_tags',
                'view_calendar',
            ],

            // ── Dev ──────────────────────────────────────────────────────────
            // Task execution, time tracking, limited project visibility.
            'dev' => [
                'view_dashboard', 'view_dashboard_overview',
                'view_personal_tasks',
                'view_projects', 'view_assigned_projects',
                'view_project_members',
                'view_project_files',
                'view_project_history',
                'view_tasks', 'work_on_tasks', 'log_time',
                'view_wiki', 'create_wiki', 'edit_wiki',
                'view_time_entries', 'start_timer', 'stop_timer',
                'view_attendance', 'clock_out',
                'view_payroll',
                'view_tags',
                'view_calendar',
            ],

            // ── Client ───────────────────────────────────────────────────────
            // External client portal access only.
            'client' => [
                'access_client_portal',
                'view_projects',
                'view_invoices',
                'view_dashboard',
                'approve_project_ui',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ])->syncPermissions($rolePermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles & permissions seeded successfully.');
        if ($removedLegacyCount > 0) {
            $this->command->info("   Removed {$removedLegacyCount} legacy permission(s).");
        }
        $this->command->info('   Source of truth: RolesAndPermissionsSeeder.');
    }
}
