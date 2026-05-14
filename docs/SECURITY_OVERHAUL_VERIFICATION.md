# Security Overhaul Verification Checklist

## ✅ Backend Verification (COMPLETED)

### Database Seeder

- [x] Seeder executed successfully
- [x] New permissions created: `manage_deliverables`, `manage_all_projects`, `create_projects`
- [x] New roles created: `manager`, `qa`
- [x] Legacy roles removed: `super-admin`, `admin`, `tech_lead`, `developer`
- [x] All 7 roles present: `founder`, `pmo`, `manager`, `techdev`, `qa`, `dev`, `client`

### Permission Assignments Verified

```bash
# Run these commands to verify:
php artisan tinker --execute="
\$founder = \Spatie\Permission\Models\Role::where('name', 'founder')->first();
echo 'Founder permissions: ' . \$founder->permissions->count() . PHP_EOL;
echo 'Has manage_all_projects: ' . (\$founder->hasPermissionTo('manage_all_projects') ? 'YES' : 'NO') . PHP_EOL;
"

php artisan tinker --execute="
\$pmo = \Spatie\Permission\Models\Role::where('name', 'pmo')->first();
echo 'PMO has manage_all_projects: ' . (\$pmo->hasPermissionTo('manage_all_projects') ? 'YES' : 'NO') . PHP_EOL;
echo 'PMO has manage_deliverables: ' . (\$pmo->hasPermissionTo('manage_deliverables') ? 'YES' : 'NO') . PHP_EOL;
"

php artisan tinker --execute="
\$manager = \Spatie\Permission\Models\Role::where('name', 'manager')->first();
echo 'Manager has manage_deliverables: ' . (\$manager->hasPermissionTo('manage_deliverables') ? 'YES' : 'NO') . PHP_EOL;
echo 'Manager has manage_all_projects: ' . (\$manager->hasPermissionTo('manage_all_projects') ? 'YES' : 'NO') . PHP_EOL;
"

php artisan tinker --execute="
\$dev = \Spatie\Permission\Models\Role::where('name', 'dev')->first();
echo 'Dev has manage_deliverables: ' . (\$dev->hasPermissionTo('manage_deliverables') ? 'YES' : 'NO') . PHP_EOL;
echo 'Dev has work_on_tasks: ' . (\$dev->hasPermissionTo('work_on_tasks') ? 'YES' : 'NO') . PHP_EOL;
"

php artisan tinker --execute="
\$qa = \Spatie\Permission\Models\Role::where('name', 'qa')->first();
echo 'QA has manage_deliverables: ' . (\$qa->hasPermissionTo('manage_deliverables') ? 'YES' : 'NO') . PHP_EOL;
echo 'QA has work_on_tasks: ' . (\$qa->hasPermissionTo('work_on_tasks') ? 'YES' : 'NO') . PHP_EOL;
"
```

**Expected Results**:

- Founder: ~180+ permissions, has `manage_all_projects`
- PMO: has `manage_all_projects` = YES, has `manage_deliverables` = YES
- Manager: has `manage_deliverables` = YES, has `manage_all_projects` = NO
- Dev: has `manage_deliverables` = NO, has `work_on_tasks` = YES
- QA: has `manage_deliverables` = NO, has `work_on_tasks` = YES

---

## 🎨 Frontend Verification (MANUAL TESTING REQUIRED)

### 1. SYSTEM_PERMISSIONS Constant

- [x] File created: `erp-frontend/src/shared/config/permissions.ts`
- [ ] **MANUAL**: Open the file and verify it contains all 200+ permissions
- [ ] **MANUAL**: Verify the permission list exactly matches the backend seeder

### 2. PermissionMatrix (Roles Edit Screen)

- [ ] **MANUAL**: Login as Founder
- [ ] **MANUAL**: Navigate to `/dashboard/roles`
- [ ] **MANUAL**: Verify the matrix shows all permissions from `SYSTEM_PERMISSIONS`
- [ ] **MANUAL**: Verify you can toggle any permission for any role (no "protected role" errors)
- [ ] **MANUAL**: Toggle a permission for the `dev` role and save
- [ ] **MANUAL**: Verify the change persists after page refresh

### 3. Task Deliverables (TaskExecutionSheet)

- [ ] **MANUAL**: Login as Developer
- [ ] **MANUAL**: Navigate to a project you are assigned to
- [ ] **MANUAL**: Open a task in the TaskExecutionSheet
- [ ] **MANUAL**: Verify you **DO NOT** see the "Add Deliverable" button
- [ ] **MANUAL**: Logout and login as Manager
- [ ] **MANUAL**: Open the same task
- [ ] **MANUAL**: Verify you **DO** see the "Add Deliverable" button
- [ ] **MANUAL**: Add a deliverable and verify it saves successfully

### 4. Project Task Creation (ProjectTasksTab)

- [ ] **MANUAL**: Login as Developer
- [ ] **MANUAL**: Navigate to a project you are assigned to
- [ ] **MANUAL**: Go to the Tasks tab
- [ ] **MANUAL**: Verify you **DO NOT** see the "Add Task" or "Add Milestone" buttons
- [ ] **MANUAL**: Logout and login as Manager
- [ ] **MANUAL**: Navigate to the same project
- [ ] **MANUAL**: Verify you **DO** see the "Add Task" and "Add Milestone" buttons
- [ ] **MANUAL**: Create a task and verify it saves successfully

### 5. Project Isolation (Project List)

- [ ] **MANUAL**: Login as Developer
- [ ] **MANUAL**: Navigate to `/dashboard/projects`
- [ ] **MANUAL**: Verify you **ONLY** see projects you are assigned to as a team member
- [ ] **MANUAL**: Note the project count
- [ ] **MANUAL**: Logout and login as PMO
- [ ] **MANUAL**: Navigate to `/dashboard/projects`
- [ ] **MANUAL**: Verify you see **ALL** projects (more than the developer saw)

### 6. Task Assignee Enforcement

- [ ] **MANUAL**: Login as Developer A
- [ ] **MANUAL**: Navigate to a task assigned to Developer B
- [ ] **MANUAL**: Try to change the task status
- [ ] **MANUAL**: Verify you get a 403 error or the status does not change
- [ ] **MANUAL**: Navigate to a task assigned to Developer A (yourself)
- [ ] **MANUAL**: Change the task status
- [ ] **MANUAL**: Verify the status changes successfully

---

## 🔐 Security Policy Verification (MANUAL API TESTING)

### Test 1: Deliverable Creation (Strict Permission Check)

```bash
# As Developer (should FAIL with 403)
curl -X POST http://localhost:8000/api/v1/projects/1/tasks/1/deliverables \
  -H "Authorization: Bearer <DEVELOPER_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"title": "Test Deliverable"}'

# Expected: 403 Forbidden
# Message: "You do not have permission to add deliverables. Required: manage_deliverables."

# As Manager (should SUCCEED with 201)
curl -X POST http://localhost:8000/api/v1/projects/1/tasks/1/deliverables \
  -H "Authorization: Bearer <MANAGER_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"title": "Test Deliverable"}'

# Expected: 201 Created
```

### Test 2: Project List Isolation

```bash
# As Developer (should return ONLY assigned projects)
curl -X GET http://localhost:8000/api/v1/projects \
  -H "Authorization: Bearer <DEVELOPER_TOKEN>"

# Expected: JSON with projects array containing ONLY projects where the developer is a team member

# As PMO (should return ALL projects)
curl -X GET http://localhost:8000/api/v1/projects \
  -H "Authorization: Bearer <PMO_TOKEN>"

# Expected: JSON with projects array containing ALL projects
```

### Test 3: Task Status Update (Assignee Enforcement)

```bash
# As Developer A trying to update Developer B's task (should FAIL with 403)
curl -X PATCH http://localhost:8000/api/v1/projects/1/tasks/1 \
  -H "Authorization: Bearer <DEVELOPER_A_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"status": "in_progress"}'

# Expected: 403 Forbidden (if task is assigned to Developer B)

# As Developer A updating their own task (should SUCCEED with 200)
curl -X PATCH http://localhost:8000/api/v1/projects/1/tasks/2 \
  -H "Authorization: Bearer <DEVELOPER_A_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"status": "in_progress"}'

# Expected: 200 OK (if task is assigned to Developer A)
```

### Test 4: PMO Task Assignment (No Restriction)

```bash
# Create a task and assign it to a PMO user (should SUCCEED)
curl -X POST http://localhost:8000/api/v1/projects/1/tasks \
  -H "Authorization: Bearer <MANAGER_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"title": "PMO Task", "assigneeId": <PMO_USER_ID>}'

# Expected: 201 Created
# Verify: The task is created and assigned to the PMO user
```

---

## 📊 Synchronization Verification

### Backend-Frontend Permission Sync

Run this script to compare backend and frontend permissions:

```bash
# Backend permissions
php artisan tinker --execute="
echo 'Backend Permissions (' . \Spatie\Permission\Models\Permission::count() . '):' . PHP_EOL;
\Spatie\Permission\Models\Permission::pluck('name')->sort()->each(fn(\$p) => print('  - ' . \$p . PHP_EOL));
"

# Frontend permissions (manual check)
# Open: erp-frontend/src/shared/config/permissions.ts
# Count the array length and compare with backend count
```

**Expected**: Both lists should have the same count and contain the same permission names.

---

## 🚨 Critical Security Checks

### 1. No Protected Role Restrictions

- [ ] **MANUAL**: Login as Founder
- [ ] **MANUAL**: Navigate to `/dashboard/roles`
- [ ] **MANUAL**: Click on the `founder` role column
- [ ] **MANUAL**: Verify you can toggle permissions (no lock icon, no error)
- [ ] **MANUAL**: Click on the `pmo` role column
- [ ] **MANUAL**: Verify you can toggle permissions (no lock icon, no error)

### 2. Strict Assignee Enforcement

- [ ] **MANUAL**: Create a task assigned to User A
- [ ] **MANUAL**: Login as User B (different user)
- [ ] **MANUAL**: Try to update the task status via API or UI
- [ ] **MANUAL**: Verify you get a 403 error

### 3. Project Team Isolation

- [ ] **MANUAL**: Create a project with only User A as a team member
- [ ] **MANUAL**: Login as User B (not a team member)
- [ ] **MANUAL**: Navigate to `/dashboard/projects`
- [ ] **MANUAL**: Verify the project does NOT appear in the list
- [ ] **MANUAL**: Try to access the project directly via URL
- [ ] **MANUAL**: Verify you get a 403 or 404 error

### 4. Deliverable Management Lockdown

- [ ] **MANUAL**: Login as Developer
- [ ] **MANUAL**: Try to add a deliverable via API
- [ ] **MANUAL**: Verify you get a 403 error with message: "You do not have permission to add deliverables. Required: manage_deliverables."

---

## 🎯 Final Checklist

- [x] Backend seeder executed successfully
- [x] New permissions created in database
- [x] New roles created in database
- [x] Legacy roles removed
- [ ] Frontend `SYSTEM_PERMISSIONS` constant verified
- [ ] PermissionMatrix shows all permissions
- [ ] Founder can edit all roles (no protected role errors)
- [ ] Developer cannot add deliverables (UI button hidden)
- [ ] Manager can add deliverables (UI button visible)
- [ ] Developer cannot see unassigned projects
- [ ] PMO can see all projects
- [ ] Developer cannot update other users' tasks
- [ ] Developer can update their own tasks
- [ ] PMO can be assigned tasks (no backend restriction)

---

## 📝 Post-Deployment Actions

1. **Force User Re-login**: All users must log out and log back in to receive the refreshed permissions array.
2. **Clear All Caches**:
    ```bash
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    ```
3. **Monitor Logs**: Check `storage/logs/laravel.log` for any 403 errors or permission-related issues.
4. **User Training**: Inform users about the new permission model and role changes.

---

## 🆘 Rollback Plan (If Needed)

If critical issues are discovered:

1. **Restore Database Backup**:

    ```bash
    # Restore from backup taken before running the seeder
    mysql -u root -p business_os < backup_before_overhaul.sql
    ```

2. **Revert Code Changes**:

    ```bash
    git revert <commit_hash>
    ```

3. **Clear Caches**:
    ```bash
    php artisan cache:clear
    php artisan config:clear
    ```

---

**Verification Status**: ✅ Backend Complete | ⏳ Frontend Manual Testing Required
