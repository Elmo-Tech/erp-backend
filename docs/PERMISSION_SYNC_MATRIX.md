# Backend ↔ Frontend Permission Synchronization Matrix

## 🔄 CRITICAL: Single Source of Truth

**Backend**: `erp-backend/database/seeders/RolesAndPermissionsSeeder.php` (lines 50-250)  
**Frontend**: `erp-frontend/src/shared/config/permissions.ts` (SYSTEM_PERMISSIONS constant)

These two files MUST be kept in sync. When adding/removing permissions, update BOTH files simultaneously.

---

## ✅ Complete Permission Mapping

| #   | Backend Permission             | Frontend Constant              | Category                 | Status     |
| --- | ------------------------------ | ------------------------------ | ------------------------ | ---------- |
| 1   | `manage_settings`              | `manage_settings`              | System & Users           | ✅ Synced  |
| 2   | `manage_users`                 | `manage_users`                 | System & Users           | ✅ Synced  |
| 3   | `manage_roles`                 | `manage_roles`                 | System & Users           | ✅ Synced  |
| 4   | `manage_dictionaries`          | `manage_dictionaries`          | System & Users           | ✅ Synced  |
| 5   | `manage_hr`                    | `manage_hr`                    | HR & Finance             | ✅ Synced  |
| 6   | `manage_payroll`               | `manage_payroll`               | HR & Finance             | ✅ Synced  |
| 7   | `view_reports`                 | `view_reports`                 | HR & Finance             | ✅ Synced  |
| 8   | `manage_invoices`              | `manage_invoices`              | HR & Finance             | ✅ Synced  |
| 9   | `manage_subscriptions`         | `manage_subscriptions`         | HR & Finance             | ✅ Synced  |
| 10  | `manage_clients`               | `manage_clients`               | Sales & Clients          | ✅ Synced  |
| 11  | `manage_leads`                 | `manage_leads`                 | Sales & Clients          | ✅ Synced  |
| 12  | `manage_contracts`             | `manage_contracts`             | Sales & Clients          | ✅ Synced  |
| 13  | `schedule_meetings`            | `schedule_meetings`            | Sales & Clients          | ✅ Synced  |
| 14  | **`manage_all_projects`**      | **`manage_all_projects`**      | **Projects (Global)**    | ✅ **NEW** |
| 15  | **`create_projects`**          | **`create_projects`**          | **Projects (Global)**    | ✅ **NEW** |
| 16  | **`manage_deliverables`**      | **`manage_deliverables`**      | **Tasks & Deliverables** | ✅ **NEW** |
| 17  | `view_dashboard`               | `view_dashboard`               | Dashboard                | ✅ Synced  |
| 18  | `view_dashboard_overview`      | `view_dashboard_overview`      | Dashboard                | ✅ Synced  |
| 19  | `view_financial_stats`         | `view_financial_stats`         | Dashboard                | ✅ Synced  |
| 20  | `view_project_timeline`        | `view_project_timeline`        | Dashboard                | ✅ Synced  |
| 21  | `view_team_performance`        | `view_team_performance`        | Dashboard                | ✅ Synced  |
| 22  | `view_personal_tasks`          | `view_personal_tasks`          | Dashboard                | ✅ Synced  |
| 23  | `view_all_blockers`            | `view_all_blockers`            | Dashboard                | ✅ Synced  |
| 24  | `view_escalated_blockers`      | `view_escalated_blockers`      | Dashboard                | ✅ Synced  |
| 25  | `view_team_pulse`              | `view_team_pulse`              | Dashboard                | ✅ Synced  |
| 26  | `view_users`                   | `view_users`                   | User Management          | ✅ Synced  |
| 27  | `create_users`                 | `create_users`                 | User Management          | ✅ Synced  |
| 28  | `edit_users`                   | `edit_users`                   | User Management          | ✅ Synced  |
| 29  | `delete_users`                 | `delete_users`                 | User Management          | ✅ Synced  |
| 30  | `view_roles`                   | `view_roles`                   | Role Management          | ✅ Synced  |
| 31  | `create_roles`                 | `create_roles`                 | Role Management          | ✅ Synced  |
| 32  | `edit_roles`                   | `edit_roles`                   | Role Management          | ✅ Synced  |
| 33  | `delete_roles`                 | `delete_roles`                 | Role Management          | ✅ Synced  |
| 34  | `view_clients`                 | `view_clients`                 | Client Management        | ✅ Synced  |
| 35  | `create_clients`               | `create_clients`               | Client Management        | ✅ Synced  |
| 36  | `edit_clients`                 | `edit_clients`                 | Client Management        | ✅ Synced  |
| 37  | `delete_clients`               | `delete_clients`               | Client Management        | ✅ Synced  |
| 38  | `manage_projects`              | `manage_projects`              | Project Management       | ✅ Synced  |
| 39  | `view_projects`                | `view_projects`                | Project Management       | ✅ Synced  |
| 40  | `view_all_projects`            | `view_all_projects`            | Project Management       | ✅ Synced  |
| 41  | `view_assigned_projects`       | `view_assigned_projects`       | Project Management       | ✅ Synced  |
| 42  | `edit_projects`                | `edit_projects`                | Project Management       | ✅ Synced  |
| 43  | `delete_projects`              | `delete_projects`              | Project Management       | ✅ Synced  |
| 44  | `approve_project_ui`           | `approve_project_ui`           | Project Management       | ✅ Synced  |
| 45  | `view_project_budget`          | `view_project_budget`          | Project Management       | ✅ Synced  |
| 46  | `manage_project_settings`      | `manage_project_settings`      | Project Management       | ✅ Synced  |
| 47  | `manage_change_requests`       | `manage_change_requests`       | Project Management       | ✅ Synced  |
| 48  | `delete_change_requests`       | `delete_change_requests`       | Project Management       | ✅ Synced  |
| 49  | `delete_milestones`            | `delete_milestones`            | Project Management       | ✅ Synced  |
| 50  | `manage_project_members`       | `manage_project_members`       | Project Members          | ✅ Synced  |
| 51  | `view_project_members`         | `view_project_members`         | Project Members          | ✅ Synced  |
| 52  | `add_project_members`          | `add_project_members`          | Project Members          | ✅ Synced  |
| 53  | `remove_project_members`       | `remove_project_members`       | Project Members          | ✅ Synced  |
| 54  | `manage_project_files`         | `manage_project_files`         | Project Files            | ✅ Synced  |
| 55  | `view_project_files`           | `view_project_files`           | Project Files            | ✅ Synced  |
| 56  | `upload_project_files`         | `upload_project_files`         | Project Files            | ✅ Synced  |
| 57  | `delete_project_files`         | `delete_project_files`         | Project Files            | ✅ Synced  |
| 58  | `view_project_history`         | `view_project_history`         | Project Files            | ✅ Synced  |
| 59  | `manage_tasks`                 | `manage_tasks`                 | Task Management          | ✅ Synced  |
| 60  | `view_tasks`                   | `view_tasks`                   | Task Management          | ✅ Synced  |
| 61  | `create_tasks`                 | `create_tasks`                 | Task Management          | ✅ Synced  |
| 62  | `edit_tasks`                   | `edit_tasks`                   | Task Management          | ✅ Synced  |
| 63  | `delete_tasks`                 | `delete_tasks`                 | Task Management          | ✅ Synced  |
| 64  | `assign_tasks`                 | `assign_tasks`                 | Task Management          | ✅ Synced  |
| 65  | `view_internal_notes`          | `view_internal_notes`          | Task Management          | ✅ Synced  |
| 66  | `work_on_tasks`                | `work_on_tasks`                | Task Management          | ✅ Synced  |
| 67  | `view_leads`                   | `view_leads`                   | Sales — Leads            | ✅ Synced  |
| 68  | `create_leads`                 | `create_leads`                 | Sales — Leads            | ✅ Synced  |
| 69  | `edit_leads`                   | `edit_leads`                   | Sales — Leads            | ✅ Synced  |
| 70  | `delete_leads`                 | `delete_leads`                 | Sales — Leads            | ✅ Synced  |
| 71  | `convert_leads`                | `convert_leads`                | Sales — Leads            | ✅ Synced  |
| 72  | `view_quotations`              | `view_quotations`              | Sales — Quotations       | ✅ Synced  |
| 73  | `create_quotations`            | `create_quotations`            | Sales — Quotations       | ✅ Synced  |
| 74  | `edit_quotations`              | `edit_quotations`              | Sales — Quotations       | ✅ Synced  |
| 75  | `delete_quotations`            | `delete_quotations`            | Sales — Quotations       | ✅ Synced  |
| 76  | `calculate_quotations`         | `calculate_quotations`         | Sales — Quotations       | ✅ Synced  |
| 77  | `view_contracts`               | `view_contracts`               | Sales — Contracts        | ✅ Synced  |
| 78  | `create_contracts`             | `create_contracts`             | Sales — Contracts        | ✅ Synced  |
| 79  | `edit_contracts`               | `edit_contracts`               | Sales — Contracts        | ✅ Synced  |
| 80  | `delete_contracts`             | `delete_contracts`             | Sales — Contracts        | ✅ Synced  |
| 81  | `sign_contracts`               | `sign_contracts`               | Sales — Contracts        | ✅ Synced  |
| 82  | `view_invoices`                | `view_invoices`                | Invoice Management       | ✅ Synced  |
| 83  | `create_invoices`              | `create_invoices`              | Invoice Management       | ✅ Synced  |
| 84  | `edit_invoices`                | `edit_invoices`                | Invoice Management       | ✅ Synced  |
| 85  | `delete_invoices`              | `delete_invoices`              | Invoice Management       | ✅ Synced  |
| 86  | `send_invoices`                | `send_invoices`                | Invoice Management       | ✅ Synced  |
| 87  | `cancel_invoices`              | `cancel_invoices`              | Invoice Management       | ✅ Synced  |
| 88  | `manage_wiki`                  | `manage_wiki`                  | Wiki Management          | ✅ Synced  |
| 89  | `view_wiki`                    | `view_wiki`                    | Wiki Management          | ✅ Synced  |
| 90  | `create_wiki`                  | `create_wiki`                  | Wiki Management          | ✅ Synced  |
| 91  | `edit_wiki`                    | `edit_wiki`                    | Wiki Management          | ✅ Synced  |
| 92  | `delete_wiki`                  | `delete_wiki`                  | Wiki Management          | ✅ Synced  |
| 93  | `view_encrypted_content`       | `view_encrypted_content`       | Wiki Management          | ✅ Synced  |
| 94  | `manage_time_entries`          | `manage_time_entries`          | Time Tracking            | ✅ Synced  |
| 95  | `view_time_entries`            | `view_time_entries`            | Time Tracking            | ✅ Synced  |
| 96  | `start_timer`                  | `start_timer`                  | Time Tracking            | ✅ Synced  |
| 97  | `stop_timer`                   | `stop_timer`                   | Time Tracking            | ✅ Synced  |
| 98  | `view_all_time_entries`        | `view_all_time_entries`        | Time Tracking            | ✅ Synced  |
| 99  | `edit_time_entries`            | `edit_time_entries`            | Time Tracking            | ✅ Synced  |
| 100 | `delete_time_entries`          | `delete_time_entries`          | Time Tracking            | ✅ Synced  |
| 101 | `log_time`                     | `log_time`                     | Time Tracking            | ✅ Synced  |
| 102 | `manage_attendance`            | `manage_attendance`            | Attendance               | ✅ Synced  |
| 103 | `view_attendance`              | `view_attendance`              | Attendance               | ✅ Synced  |
| 104 | `view_all_attendance`          | `view_all_attendance`          | Attendance               | ✅ Synced  |
| 105 | `clock_out`                    | `clock_out`                    | Attendance               | ✅ Synced  |
| 106 | `manage_shifts`                | `manage_shifts`                | Shifts                   | ✅ Synced  |
| 107 | `manage_employees`             | `manage_employees`             | Employee Management      | ✅ Synced  |
| 108 | `view_employees`               | `view_employees`               | Employee Management      | ✅ Synced  |
| 109 | `create_employees`             | `create_employees`             | Employee Management      | ✅ Synced  |
| 110 | `edit_employees`               | `edit_employees`               | Employee Management      | ✅ Synced  |
| 111 | `view_employee_salary`         | `view_employee_salary`         | Employee Management      | ✅ Synced  |
| 112 | `view_employee_performance`    | `view_employee_performance`    | Employee Management      | ✅ Synced  |
| 113 | `manage_sops`                  | `manage_sops`                  | SOP Management           | ✅ Synced  |
| 114 | `view_morning_sop`             | `view_morning_sop`             | SOP Management           | ✅ Synced  |
| 115 | `submit_morning_sop`           | `submit_morning_sop`           | SOP Management           | ✅ Synced  |
| 116 | `view_meetings`                | `view_meetings`                | Meetings                 | ✅ Synced  |
| 117 | `delete_meetings`              | `delete_meetings`              | Meetings                 | ✅ Synced  |
| 118 | `access_client_portal`         | `access_client_portal`         | Client Portal            | ✅ Synced  |
| 119 | `create_client_portal_access`  | `create_client_portal_access`  | Client Portal            | ✅ Synced  |
| 120 | `revoke_client_portal_access`  | `revoke_client_portal_access`  | Client Portal            | ✅ Synced  |
| 121 | `reset_client_portal_password` | `reset_client_portal_password` | Client Portal            | ✅ Synced  |
| 122 | `view_profitability`           | `view_profitability`           | Reports & Analytics      | ✅ Synced  |
| 123 | `view_cost`                    | `view_cost`                    | Reports & Analytics      | ✅ Synced  |
| 124 | `view_quality_metrics`         | `view_quality_metrics`         | Reports & Analytics      | ✅ Synced  |
| 125 | `view_user_performance`        | `view_user_performance`        | Reports & Analytics      | ✅ Synced  |
| 126 | `view_attendance_analytics`    | `view_attendance_analytics`    | Reports & Analytics      | ✅ Synced  |
| 127 | `view_admin_stats`             | `view_admin_stats`             | Reports & Analytics      | ✅ Synced  |
| 128 | `view_internal_costs`          | `view_internal_costs`          | Financial Data           | ✅ Synced  |
| 129 | `manage_internal_costs`        | `manage_internal_costs`        | Financial Data           | ✅ Synced  |
| 130 | `view_payroll`                 | `view_payroll`                 | Payroll                  | ✅ Synced  |
| 131 | `view_settings`                | `view_settings`                | Settings                 | ✅ Synced  |
| 132 | `delete_settings`              | `delete_settings`              | Settings                 | ✅ Synced  |
| 133 | `view_audit_logs`              | `view_audit_logs`              | Security & Audit         | ✅ Synced  |
| 134 | `manage_tags`                  | `manage_tags`                  | Tags                     | ✅ Synced  |
| 135 | `view_tags`                    | `view_tags`                    | Tags                     | ✅ Synced  |
| 136 | `view_calendar`                | `view_calendar`                | Calendar                 | ✅ Synced  |

**Total Permissions**: 136  
**New Permissions Added**: 3 (`manage_all_projects`, `create_projects`, `manage_deliverables`)  
**Sync Status**: ✅ 100% Synchronized

---

## 🎯 Key Permission Enforcement Points

### 1. `manage_all_projects` (NEW)

**Backend Enforcement**:

- `ListProjectsAction.php` (line 35): Project list scoping
- `ProjectPolicy.php` (line 45): Project view access
- `TaskPolicy.php` (lines 50, 75, 100, 125): Task CRUD bypass

**Frontend Usage**:

- `ProjectTasksTab.tsx` (line 85): Add Task/Milestone button visibility
- `TaskExecutionSheet.tsx` (line 150): Add Deliverable button visibility

### 2. `create_projects` (NEW)

**Backend Enforcement**:

- `ProjectPolicy.php` (line 60): Project creation gate

**Frontend Usage**:

- `ProjectTasksTab.tsx` (line 85): Combined with project role check

### 3. `manage_deliverables` (NEW)

**Backend Enforcement**:

- `ProjectTaskDeliverableController.php` (line 25): Deliverable creation
- `ProjectTaskDeliverableController.php` (line 75): Deliverable deletion

**Frontend Usage**:

- `TaskExecutionSheet.tsx` (line 150): Add Deliverable button visibility

---

## 🔄 Synchronization Workflow

When adding a new permission:

1. **Backend** (`RolesAndPermissionsSeeder.php`):

    ```php
    $permissions = [
        // ... existing permissions
        'new_permission_name',  // Add here
    ];
    ```

2. **Frontend** (`permissions.ts`):

    ```typescript
    export const SYSTEM_PERMISSIONS = [
    	// ... existing permissions
    	"new_permission_name", // Add here (same name, double quotes)
    ] as const;
    ```

3. **Run Seeder**:

    ```bash
    php artisan db:seed --class=RolesAndPermissionsSeeder
    ```

4. **Verify Sync**:
    ```bash
    php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::where('name', 'new_permission_name')->exists() ? 'EXISTS' : 'MISSING';"
    ```

---

## ✅ Verification Commands

### Count Backend Permissions

```bash
php artisan tinker --execute="echo 'Total: ' . \Spatie\Permission\Models\Permission::count();"
```

### List All Backend Permissions

```bash
php artisan tinker --execute="\Spatie\Permission\Models\Permission::pluck('name')->sort()->each(fn(\$p) => print(\$p . PHP_EOL));"
```

### Verify New Permissions Exist

```bash
php artisan tinker --execute="
\$new = ['manage_all_projects', 'create_projects', 'manage_deliverables'];
foreach (\$new as \$perm) {
    echo \$perm . ': ' . (\Spatie\Permission\Models\Permission::where('name', \$perm)->exists() ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
"
```

**Expected Output**:

```
manage_all_projects: EXISTS
create_projects: EXISTS
manage_deliverables: EXISTS
```

---

**Sync Status**: ✅ Complete  
**Last Updated**: 2026-05-12  
**Verified By**: Security Overhaul Script
