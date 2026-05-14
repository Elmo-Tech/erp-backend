# 🚀 Quick Reference: Permissions & Roles

## 🔑 The 3 New Critical Permissions

| Permission                | Who Has It                     | What It Does                                                               |
| ------------------------- | ------------------------------ | -------------------------------------------------------------------------- |
| **`manage_all_projects`** | Founder, PMO                   | **Global bypass**: See and edit ANY project, regardless of team membership |
| **`create_projects`**     | Founder, PMO, Manager          | Can create new projects                                                    |
| **`manage_deliverables`** | Founder, PMO, Manager, TechDev | Can add/edit/delete task deliverables                                      |

---

## 👥 Role Quick Reference

| Role        | Key Permissions                                               | Can See All Projects?   | Can Add Deliverables? | Can Update Any Task?       |
| ----------- | ------------------------------------------------------------- | ----------------------- | --------------------- | -------------------------- |
| **founder** | ALL (except `access_client_portal`)                           | ✅ Yes                  | ✅ Yes                | ✅ Yes                     |
| **pmo**     | `manage_all_projects`, `manage_deliverables`, `manage_tasks`  | ✅ Yes                  | ✅ Yes                | ✅ Yes                     |
| **manager** | `manage_deliverables`, `manage_tasks`, `create_projects`      | ❌ No (only assigned)   | ✅ Yes                | ✅ Yes (in their projects) |
| **techdev** | `manage_deliverables`, `manage_tasks`, `work_on_tasks`        | ❌ No (only assigned)   | ✅ Yes                | ✅ Yes (in their projects) |
| **qa**      | `work_on_tasks`, `view_tasks`                                 | ❌ No (only assigned)   | ❌ No                 | ❌ No (only own tasks)     |
| **dev**     | `work_on_tasks`, `view_tasks`                                 | ❌ No (only assigned)   | ❌ No                 | ❌ No (only own tasks)     |
| **client**  | `access_client_portal`, `view_projects`, `approve_project_ui` | ❌ No (only own client) | ❌ No                 | ❌ No                      |

---

## 🔒 Security Rules (STRICT)

### Rule 1: Project Isolation

```
IF user does NOT have manage_all_projects
THEN user can ONLY see projects where they are a team member
```

**Example**:

- Developer A is assigned to Project X
- Developer A navigates to `/dashboard/projects`
- Developer A sees ONLY Project X (not Projects Y or Z)

### Rule 2: Assignee Enforcement

```
IF user wants to update task status OR submit deliverables
THEN user MUST be the exact assignee (assignee_id === user.id)
OR user MUST have manage_tasks OR manage_all_projects
```

**Example**:

- Task #123 is assigned to Developer A
- Developer B tries to update Task #123 status
- Backend returns 403 Forbidden

### Rule 3: Deliverable Lockdown

```
IF user wants to add/edit/delete deliverables
THEN user MUST have manage_deliverables OR manage_all_projects
```

**Example**:

- Developer tries to add a deliverable
- Backend returns 403: "You do not have permission to add deliverables. Required: manage_deliverables."

---

## 🎯 Common Scenarios

### Scenario 1: Developer Wants to Add a Deliverable

**Question**: "Why can't I add a deliverable to my task?"  
**Answer**: Only users with `manage_deliverables` permission (Manager, TechDev, PMO, Founder) can add deliverables. Contact your project manager.

### Scenario 2: Developer Can't See a Project

**Question**: "Why don't I see Project X in my project list?"  
**Answer**: You are not assigned to Project X as a team member. Contact your project manager to be added to the team.

### Scenario 3: Developer Can't Update Another Developer's Task

**Question**: "Why can't I update Task #123?"  
**Answer**: Task #123 is assigned to another developer. You can only update tasks that are explicitly assigned to you.

### Scenario 4: Manager Wants to Edit Founder Role

**Question**: "Why can't I edit the Founder role?"  
**Answer**: Only the Founder can edit roles. You need `manage_roles` permission.

### Scenario 5: PMO Wants to Be Assigned a Task

**Question**: "Can I be assigned a task?"  
**Answer**: Yes! The old restriction has been removed. Any team member (including PMOs) can be assigned tasks.

---

## 🛠️ Developer Cheat Sheet

### Backend: Check Permission

```php
// Check if user has a permission
if ($user->can('manage_deliverables')) {
    // Allow action
}

// Check if user has manage_all_projects (global bypass)
if ($user->can('manage_all_projects')) {
    // Bypass project membership checks
}

// Check if user is the task assignee
if ((int) $task->assignee_id === $user->id) {
    // Allow task update
}
```

### Frontend: Check Permission

```typescript
// Import the permission check hook
import { useAuthStore } from "@/entities/user/model/auth.store";

// Check if user has a permission
const canManageDeliverables = useAuthStore((s) => s.hasPermission("manage_deliverables"));

// Check if user has manage_all_projects
const canManageAllProjects = useAuthStore((s) => s.hasPermission("manage_all_projects"));

// Use the Can component
<Can permission="manage_deliverables">
  <AddDeliverableButton />
</Can>

// Use the Can component with multiple permissions (OR logic)
<Can permission={["manage_deliverables", "manage_all_projects"]}>
  <AddDeliverableButton />
</Can>
```

---

## 📝 Adding a New Permission

### Step 1: Backend Seeder

Edit `erp-backend/database/seeders/RolesAndPermissionsSeeder.php`:

```php
$permissions = [
    // ... existing permissions
    'new_permission_name',  // Add here
];
```

### Step 2: Frontend Constant

Edit `erp-frontend/src/shared/config/permissions.ts`:

```typescript
export const SYSTEM_PERMISSIONS = [
	// ... existing permissions
	"new_permission_name", // Add here (same name, double quotes)
] as const;
```

### Step 3: Run Seeder

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Step 4: Assign to Roles

Edit the `$roles` array in the seeder:

```php
'founder' => [
    // ... existing permissions
    'new_permission_name',  // Add to founder
],
```

---

## 🔍 Debugging Permissions

### Check User Permissions

```bash
php artisan tinker
>>> $user = \App\Modules\Settings\Models\User::find(1);
>>> $user->getAllPermissions()->pluck('name');
```

### Check Role Permissions

```bash
php artisan tinker
>>> $role = \Spatie\Permission\Models\Role::where('name', 'dev')->first();
>>> $role->permissions->pluck('name');
```

### Check if Permission Exists

```bash
php artisan tinker
>>> \Spatie\Permission\Models\Permission::where('name', 'manage_deliverables')->exists();
```

### Clear Permission Cache

```bash
php artisan cache:clear
php artisan config:clear
```

---

## 🚨 Common Errors

### Error: "Permission not found"

**Solution**: Run the seeder again:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Error: "403 Forbidden"

**Cause**: User does not have the required permission  
**Solution**: Check the user's role and permissions. Assign the correct role or permission.

### Error: "User can still see all projects"

**Cause**: User has `manage_all_projects` permission  
**Solution**: This is intentional for PMO and Founder roles. If the user should not have this permission, remove it from their role.

### Error: "Frontend shows old permissions"

**Cause**: User has not re-logged in  
**Solution**: User must log out and log back in to receive the refreshed permissions array.

---

## 📞 Quick Help

| Issue                  | Command                                                                                                          |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------- |
| List all permissions   | `php artisan tinker --execute="\Spatie\Permission\Models\Permission::pluck('name')->sort()->values();"`          |
| List all roles         | `php artisan tinker --execute="\Spatie\Permission\Models\Role::pluck('name');"`                                  |
| Check user permissions | `php artisan tinker --execute="\App\Modules\Settings\Models\User::find(1)->getAllPermissions()->pluck('name');"` |
| Re-run seeder          | `php artisan db:seed --class=RolesAndPermissionsSeeder`                                                          |
| Clear caches           | `php artisan cache:clear && php artisan config:clear`                                                            |

---

**Last Updated**: May 12, 2026  
**Version**: 2.0 (Security Overhaul)
