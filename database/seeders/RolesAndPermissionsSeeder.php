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
 * PERMISSION CATALOGUE is the single source of truth for both backend
 * enforcement and frontend SYSTEM_PERMISSIONS constant.
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
            'view_lead_conversion_status',
            'manage_client_portal_access',
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
        // Remove legacy system roles; keep founder/admin/pmo/techdev/dev/client/manager/qa
        Role::whereIn('name', ['super-admin', 'tech_lead', 'developer'])->delete();

        // ─────────────────────────────────────────────────────────────────────
        // PERMISSION CATALOGUE
        // This list is the single source of truth. It is mirrored exactly in
        // erp-frontend/src/shared/config/permissions.ts → SYSTEM_PERMISSIONS.
        // ─────────────────────────────────────────────────────────────────────
        $permissions = [

            // ── System & Users ───────────────────────────────────────────────
            'manage_settings',
            'manage_users',
            'manage_roles',
            'manage_dictionaries',
            'use_ai_agent',

            // ── HR & Finance ─────────────────────────────────────────────────
            'manage_hr',
            'manage_payroll',
            'manage_points',
            'award_points',
            'deduct_points',
            'view_reports',
            'manage_invoices',
            'manage_subscriptions',

            // ── Sales & Clients ──────────────────────────────────────────────
            'manage_clients',
            'manage_leads',
            'manage_contracts',
            'schedule_meetings',

            // ── Projects (Global vs Local) ───────────────────────────────────
            // manage_all_projects: Global Admin/PMO bypass — edit/delete any
            //   project regardless of team membership.
            // create_projects: Allowed to create new projects.
            'manage_all_projects',
            'create_projects',

            // ── Tasks & Deliverables ─────────────────────────────────────────
            // manage_deliverables: STRICTLY for users allowed to define what
            //   deliverables are required on a task. Developers/QA are excluded.
            'manage_deliverables',

            // ── Dashboard ────────────────────────────────────────────────────
            'view_my_workspace',
            'view_global_stats',
            'view_dashboard',
            'view_dashboard_overview',
            'view_financial_stats',
            'view_project_timeline',
            'view_team_performance',
            'view_personal_tasks',
            'view_all_blockers',
            'view_escalated_blockers',
            'view_team_pulse',

            // ── User Management (granular) ───────────────────────────────────
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // ── Role & Permission Management (granular) ──────────────────────
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // ── Client Management (granular) ─────────────────────────────────
            'view_clients',
            'create_clients',
            'edit_clients',
            'delete_clients',

            // ── Project Management (granular) ────────────────────────────────
            'manage_projects',
            'view_projects',
            'view_all_projects',
            'view_assigned_projects',
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

            // ── Task Management (granular) ───────────────────────────────────
            'manage_tasks',
            'view_tasks',
            'create_tasks',
            'edit_tasks',
            'delete_tasks',
            'assign_tasks',
            'view_internal_notes',
            'work_on_tasks',

            // ── Sales — Leads (granular) ─────────────────────────────────────
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

            // ── Sales — Contracts (granular) ─────────────────────────────────
            'view_contracts',
            'create_contracts',
            'edit_contracts',
            'delete_contracts',
            'sign_contracts',

            // ── Invoice Management (granular) ────────────────────────────────
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',
            'send_invoices',
            'cancel_invoices',

            // ── Time Tracking ────────────────────────────────────────────────
            'manage_time_entries',
            'manage_team_timers',
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
            'manage_shifts',

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

            // ── Meetings (granular) ──────────────────────────────────────────
            'view_meetings',
            'delete_meetings',

            // ── Client Portal ────────────────────────────────────────────────
            'access_client_portal',
            'create_client_portal_access',
            'revoke_client_portal_access',
            'reset_client_portal_password',

            // ── Reports & Analytics ──────────────────────────────────────────
            'view_profitability',
            'view_cost',
            'view_quality_metrics',
            'view_user_performance',
            'view_attendance_analytics',
            'view_admin_stats',

            // ── Financial Data ───────────────────────────────────────────────
            'view_internal_costs',
            'manage_internal_costs',

            // ── Payroll (granular) ───────────────────────────────────────────
            'view_payroll',

            // ── Settings (granular) ──────────────────────────────────────────
            'view_settings',
            'delete_settings',

            // ── Security & Audit ─────────────────────────────────────────────
            'view_audit_logs',

            // ── Tags ─────────────────────────────────────────────────────────
            'manage_tags',
            'view_tags',

            // ── Calendar ─────────────────────────────────────────────────────
            'view_calendar',
            'view_calendar_events',
            'view_calendar_meetings',
            'view_calendar_tasks',
            'view_calendar_milestones',
            'view_calendar_invoices',
            'view_calendar_subscriptions',
            'collect_subscription_payments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // ROLE DEFINITIONS
        //
        // System roles:
        //   founder  — Business owner: ALL internal permissions, zero restrictions.
        //              CRITICAL: No "protected role" lock. Founder can edit everything.
        //   pmo      — Project oversight & planning. manage_all_projects bypass.
        //   manager  — Project-level manager. manage_deliverables + task management.
        //   techdev  — Technical lead/developer hybrid. manage_deliverables included.
        //   qa       — QA engineer. Work-on-tasks only, NO manage_deliverables.
        //   dev      — Developer. Execution-only, NO manage_deliverables.
        //   client   — External portal access only.
        // ─────────────────────────────────────────────────────────────────────

        // Founder gets every internal permission — no exceptions, no locks.
        $founderPermissions = array_values(array_diff($permissions, [
            'access_client_portal',
        ]));

        $roles = [

            // ── Founder ──────────────────────────────────────────────────────
            // ALL internal capabilities. The Founder must be able to edit
            // every role and every permission — zero "protected role" blocks.
            'founder' => $founderPermissions,

            // ── Admin ────────────────────────────────────────────────────────
            // Company-wide operational analytics. Does not include founder-only
            // role/system ownership capabilities.
            'admin' => [
                'view_my_workspace',
                'view_global_stats',
                'view_dashboard', 'view_dashboard_overview',
                'view_financial_stats', 'view_project_timeline',
                'view_team_performance', 'view_all_blockers',
                'view_escalated_blockers', 'view_team_pulse',
                'view_reports', 'view_admin_stats',
                'use_ai_agent',
                'view_profitability', 'view_quality_metrics',
                'view_user_performance', 'view_attendance_analytics',
                'manage_invoices', 'manage_subscriptions', 'collect_subscription_payments',
                'view_projects', 'view_all_projects',
                'view_project_members', 'view_project_files', 'view_project_history',
                'view_tasks', 'view_time_entries', 'view_all_time_entries',
                'view_attendance', 'view_all_attendance',
                'view_employees', 'view_employee_performance',
                'manage_points', 'award_points', 'deduct_points',
                'view_meetings',
                'view_tags',
                'view_calendar', 'view_calendar_events', 'view_calendar_meetings',
                'view_calendar_tasks', 'view_calendar_milestones',
                'view_calendar_invoices', 'view_calendar_subscriptions',
            ],

            // ── PMO ──────────────────────────────────────────────────────────
            // Project oversight and planning. Has manage_all_projects (global
            // bypass) and manage_deliverables. No financial data.
            'pmo' => [
                'view_my_workspace',
                'view_dashboard', 'view_dashboard_overview',
                'use_ai_agent',
                'view_project_timeline', 'view_all_blockers',
                'view_morning_sop', 'submit_morning_sop',
                'view_projects', 'view_all_projects', 'manage_all_projects',
                'create_projects',
                'view_project_members', 'add_project_members', 'remove_project_members',
                'view_project_files', 'upload_project_files',
                'view_project_history',
                'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks',
                'delete_tasks', 'assign_tasks', 'view_internal_notes',
                'manage_deliverables',
                'manage_team_timers',
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
                'view_calendar', 'view_calendar_events', 'view_calendar_meetings',
                'view_calendar_tasks', 'view_calendar_milestones',
            ],

            // ── Manager ──────────────────────────────────────────────────────
            // Project-level manager. Can manage deliverables and tasks within
            // their assigned projects. Does NOT have manage_all_projects.
            'manager' => [
                'view_my_workspace',
                'view_dashboard', 'view_dashboard_overview',
                'use_ai_agent',
                'view_team_performance', 'view_project_timeline',
                'view_projects', 'view_assigned_projects', 'create_projects',
                'edit_projects',
                'view_project_members', 'add_project_members', 'remove_project_members',
                'view_project_files', 'upload_project_files',
                'view_project_history',
                'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks',
                'delete_tasks', 'assign_tasks', 'view_internal_notes', 'work_on_tasks',
                'manage_deliverables',
                'manage_change_requests',
                'schedule_meetings', 'view_meetings',
                'view_time_entries', 'view_all_time_entries', 'start_timer', 'stop_timer',
                'manage_team_timers',
                'view_attendance', 'clock_out',
                'view_payroll',
                'view_tags',
                'view_calendar', 'view_calendar_events', 'view_calendar_meetings',
                'view_calendar_tasks', 'view_calendar_milestones',
            ],

            // ── TechDev ──────────────────────────────────────────────────────
            // Technical lead/developer hybrid. Has manage_deliverables because
            // tech leads define what deliverables are required.
            'techdev' => [
                'view_my_workspace',
                'view_dashboard', 'view_dashboard_overview',
                'use_ai_agent',
                'view_team_performance', 'view_project_timeline',
                'view_projects', 'view_assigned_projects',
                'view_project_members',
                'view_project_files', 'upload_project_files',
                'view_project_history',
                'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks', 'delete_tasks',
                'assign_tasks', 'view_internal_notes', 'work_on_tasks',
                'manage_deliverables',
                'view_time_entries', 'start_timer', 'stop_timer',
                'view_attendance', 'clock_out',
                'view_payroll',
                'view_tags',
                'view_calendar', 'view_calendar_events', 'view_calendar_meetings',
                'view_calendar_tasks', 'view_calendar_milestones',
            ],

            // ── QA ───────────────────────────────────────────────────────────
            // QA engineer. Execution-only. NO manage_deliverables — QA does
            // not define deliverables, they verify them.
            'qa' => [
                'view_my_workspace',
                'view_dashboard', 'view_dashboard_overview',
                'use_ai_agent',
                'view_personal_tasks',
                'view_projects', 'view_assigned_projects',
                'view_project_members',
                'view_project_files',
                'view_project_history',
                'view_tasks', 'work_on_tasks', 'log_time',
                'view_time_entries', 'start_timer', 'stop_timer',
                'view_attendance', 'clock_out',
                'view_payroll',
                'view_tags',
                'view_calendar', 'view_calendar_tasks',
            ],

            // ── Dev ──────────────────────────────────────────────────────────
            // Developer. Task execution, time tracking, limited project
            // visibility. NO manage_deliverables.
            'dev' => [
                'view_my_workspace',
                'view_dashboard', 'view_dashboard_overview',
                'use_ai_agent',
                'view_personal_tasks',
                'view_projects', 'view_assigned_projects',
                'view_project_members',
                'view_project_files',
                'view_project_history',
                'view_tasks', 'work_on_tasks', 'log_time',
                'view_time_entries', 'start_timer', 'stop_timer',
                'view_attendance', 'clock_out',
                'view_payroll',
                'view_tags',
                'view_calendar', 'view_calendar_tasks',
            ],

            // ── Client ───────────────────────────────────────────────────────
            // External client portal access only.
            'client' => [
                'view_my_workspace',
                'access_client_portal',
                'view_projects',
                'view_invoices',
                'view_dashboard',
                'approve_project_ui',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'web',
            ])->syncPermissions($rolePermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles & permissions seeded successfully.');
        if ($removedLegacyCount > 0) {
            $this->command->info("   Removed {$removedLegacyCount} legacy permission(s).");
        }
        $this->command->info('   Roles: founder, admin, pmo, manager, techdev, qa, dev, client');
        $this->command->info('   Source of truth: RolesAndPermissionsSeeder + SYSTEM_PERMISSIONS (frontend).');
    }
}
