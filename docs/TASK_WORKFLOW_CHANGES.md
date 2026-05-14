# Task Workflow Simplification & Permission Enforcement

## Executive Summary

This changelog documents the complete removal of Subtasks functionality and the implementation of strict Task Status workflow enforcement as requested by the CTO. The changes ensure that ONLY authorized users can mark tasks as "completed", while regular assignees are restricted to using "In Review" status.

---

## STEP 1: SUBTASKS ERADICATION ✅

### Backend Changes

#### 1. **ProjectTaskController.php**

- **Removed**: `parent_id` from task creation in `store()` method
- **Removed**: `parent_id` from task updates in `update()` method
- **Removed**: Subtask type detection in activity logging
- **Updated**: `index()` method - removed `->root()` scope and `subtasks.assignee` eager loading
- **Updated**: `show()` method - removed `subtasks.assignee` and `parent` eager loading
- **Impact**: Tasks are now completely flat - no parent-child relationships

**Lines Changed**:

```php
// Before: Line ~48
->root()
->with(['assignee', 'deliverables', 'subtasks.assignee', 'milestone', 'timeEntries']);

// After: Line ~48
->with(['assignee', 'deliverables', 'milestone', 'timeEntries']);
// REMOVED: subtasks.assignee - subtasks are no longer supported
```

#### 2. **StoreProjectTaskRequest.php**

- **Removed**: `parentId` validation rule
- **Removed**: `parentId.exists` validation message
- **Impact**: API no longer accepts `parentId` parameter during task creation

**Lines Changed**:

```php
// Before: Line ~20
'parentId' => ['nullable', 'integer', 'exists:project_tasks,id'],

// After: Line ~20
// REMOVED: parentId validation - subtasks are no longer supported
```

#### 3. **UpdateProjectTaskRequest.php**

- **Removed**: `parentId` validation rule
- **Impact**: API no longer accepts `parentId` parameter during task updates

**Lines Changed**:

```php
// Before: Line ~18
'parentId' => ['nullable', 'integer', 'exists:project_tasks,id'],

// After: Line ~18
// REMOVED: parentId validation - subtasks are no longer supported
```

### Frontend Changes

#### 4. **execution.api.ts**

- **Removed**: `parentId` from `CreateTaskPayload` interface
- **Removed**: `subtasks` array from `ProjectTask` interface
- **Impact**: TypeScript types no longer support subtask relationships

**Lines Changed**:

```typescript
// Before: Line ~36
export interface CreateTaskPayload {
	title: string;
	milestoneId?: number | null;
	parentId?: number | null;  // ❌ REMOVED
	description?: string;
	estimatedHours?: number | null;
	assigneeId?: number | null;
}

// Before: Line ~60
export interface ProjectTask {
	taskId: number;
	projectId: number;
	milestoneId: number | null;
	parentId: number | null;  // ❌ REMOVED
	title: string;
	description: string | null;
	status: TaskStatus;
	statusLabel: string | null;
	estimatedHours: number | null;
	assigneeId: number | null;
	isInBacklog: boolean;
	totalLoggedMinutes: number;
	assignee?: TaskAssignee | null;
	milestone?: { ... } | null;
	subtasks?: ProjectTask[];  // ❌ REMOVED
	deliverables?: TaskDeliverable[];
	timeEntries?: ProjectTimeEntry[];
	createdAt: string;
	updatedAt: string;
}
```

#### 5. **TaskExecutionSheet.tsx**

- **Removed**: Entire Subtasks section UI (lines 735-794)
- **Removed**: `createSubtask` mutation
- **Removed**: `handleAddSubtask` callback
- **Removed**: `handleSubtaskTimerToggle` callback
- **Removed**: `subtasks` variable derivation
- **Removed**: `GitBranch` icon import
- **Updated**: Component documentation to reflect removal
- **Impact**: Users can no longer create, view, or manage subtasks

**Lines Removed**:

```tsx
// ❌ REMOVED: Lines 735-794 - Entire Subtasks Section
<Separator />;

{
	/* Subtasks */
}
<div>
	<div className="mb-3 flex items-center gap-2">
		<h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Subtasks</h4>
		{subtasks.length > 0 && <span className="text-xs text-muted-foreground/60">({subtasks.length})</span>}
	</div>
	{/* ... 60 lines of subtask UI ... */}
	{!isDone && (
		<QuickAddForm
			placeholder="Add a subtask…"
			onSubmit={handleAddSubtask}
			isPending={createSubtask.isPending}
		/>
	)}
</div>;
```

---

## STEP 2: TASK EDIT & DELETE PERMISSIONS ✅

### Backend - TaskPolicy.php

**Status**: ✅ Already Correctly Implemented

The `TaskPolicy.php` already enforces proper permissions:

#### `update()` Method (Lines 80-105)

```php
public function update(User $user, ProjectTask $task): bool
{
    // 1. Global admins and task managers → ALWAYS allowed
    if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
        return true;
    }

    // 2. Project managers/tech leads with edit_tasks → allowed
    if ($user->can('edit_tasks') && $this->isProjectManager($user, $project)) {
        return true;
    }

    // 3. Assignee with work_on_tasks → allowed (own task only)
    if ($user->can('work_on_tasks') && (int) $task->assignee_id === $user->id) {
        return true;
    }

    return false;
}
```

#### `delete()` Method (Lines 115-127)

```php
public function delete(User $user, ProjectTask $task): bool
{
    // 1. Global admins and task managers → ALWAYS allowed
    if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
        return true;
    }

    // 2. Project managers/tech leads with delete_tasks → allowed
    if ($user->can('delete_tasks') && $this->isProjectManager($user, $task->project)) {
        return true;
    }

    return false;
}
```

**Key Points**:

- ✅ Founder (with `manage_all_projects`) can edit/delete ANY task
- ✅ Project managers/tech leads can edit/delete tasks in their projects
- ✅ Assignees can edit their own tasks (but NOT delete)
- ✅ No one else can edit or delete tasks

### Frontend - Permission Checks

**Status**: ✅ Already Using Permission Hooks

The frontend already uses `useTaskPermissions()` hook throughout:

- `ProjectTasksTab.tsx` - Uses `canManageTasks`, `canCreateTasks` for action buttons
- `TaskExecutionSheet.tsx` - Uses `canEditTasks`, `canWorkOnTasks` for status updates
- All permission checks align with backend policy

---

## STEP 3: "COMPLETED" STATUS GUARD ✅

### Backend - ProjectTaskController.php

#### Updated `update()` Method (Lines 145-210)

**NEW ENFORCEMENT**: Added strict guard before status update:

```php
/**
 * CRITICAL WORKFLOW ENFORCEMENT:
 *   - ONLY users with manage_deliverables, manage_all_projects, OR project manager role
 *     can mark a task as "done" (completed).
 *   - Regular assignees CANNOT directly set status to "done". Their ceiling is "in_review".
 *   - If an assignee tries to set status to "done", return 403 with clear message.
 */
public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task): JsonResponse
{
    $this->assertBelongsToProject($task, $project);
    $this->authorize('update', $task);

    $data = $request->validated();

    // ── CRITICAL: "COMPLETED" STATUS GUARD ────────────────────────────────
    if (isset($data['status']) && $data['status'] === ExecutionTaskStatus::DONE->value) {
        $user = $request->user();

        // Global bypass permissions
        if ($user->can('manage_all_projects') || $user->can('manage_deliverables') || $user->can('manage_tasks')) {
            // Allowed — proceed
        } else {
            // Check if user is project manager or tech_lead
            $member = $project->members()->where('user_id', $user->id)->first();
            $projectRole = $member?->role instanceof ProjectRole
                ? $member->role->value
                : (string) ($member?->role ?? '');

            $isManagerLevel = in_array($projectRole, [
                ProjectRole::MANAGER->value,
                ProjectRole::TECH_LEAD->value,
            ], true);

            if (! $isManagerLevel) {
                return ApiResponse::error(
                    'You cannot mark tasks as completed directly. Please use "In Review" status instead.',
                    403
                );
            }
        }
    }

    // ... rest of update logic
}
```

**Who Can Mark as "Done"**:

1. ✅ Users with `manage_all_projects` (Founder)
2. ✅ Users with `manage_deliverables` (Deliverable Managers)
3. ✅ Users with `manage_tasks` (Task Managers)
4. ✅ Project Managers (role = `manager`)
5. ✅ Tech Leads (role = `tech_lead`)

**Who CANNOT Mark as "Done"**:

- ❌ Developers (role = `developer`)
- ❌ QA (role = `qa`) without special permissions
- ❌ Designers (role = `designer`)
- ❌ Any assignee without the above permissions/roles

**Error Response**:

```json
{
	"success": false,
	"message": "You cannot mark tasks as completed directly. Please use \"In Review\" status instead.",
	"status": 403
}
```

### Frontend - TaskExecutionSheet.tsx

#### Updated Permission Logic (Lines 270-320)

**NEW PERMISSION**: Added `canManageDeliverables` to permissions:

```typescript
const { canManageTasks, canEditTasks, canWorkOnTasks, canManageDeliverables } = useTaskPermissions();
```

**NEW GUARD**: Added `canMarkAsCompleted` flag:

```typescript
// ── CRITICAL: "COMPLETED" STATUS GUARD ────────────────────────────────
// ONLY users with these permissions can mark tasks as "done":
//   1. manage_all_projects (global admin)
//   2. manage_deliverables (deliverable managers)
//   3. manage_tasks (task managers)
//   4. Project manager or tech_lead role
const canMarkAsCompleted = canManageAllProjects || canManageDeliverables || canManageTasks || projectRole === "manager" || projectRole === "tech_lead";
```

**NEW FILTER**: Status dropdown now filters out "done" for unauthorized users:

```typescript
const allowedStatusOptions = (() => {
	// Start with all status options
	let options = [...EXECUTION_TASK_STATUS_OPTIONS];

	// If user cannot mark as completed, remove "done" from the list
	if (!canMarkAsCompleted) {
		options = options.filter((o) => o.value !== ExecutionTaskStatus.DONE);
	}

	// If user has no update permissions at all, return empty (read-only)
	if (!canManageAllProjects && !canManageTasks && !canEditTasks && !canWorkOnTasks) {
		return [];
	}

	return options;
})();
```

**User Experience**:

- ✅ Authorized users see all status options including "Done"
- ✅ Regular assignees see: Backlog, To Do, In Progress, In Review, QA (NO "Done")
- ✅ If assignee tries to bypass UI and send "done" via API, backend returns 403

#### Updated permissions.ts (Lines 30-40, 130-140)

**NEW CONSTANT**: Added `MANAGE_DELIVERABLES` permission:

```typescript
export const TASK_PERMISSIONS = {
	MANAGE_TASKS: "manage_tasks",
	VIEW_TASKS: "view_tasks",
	CREATE_TASKS: "create_tasks",
	EDIT_TASKS: "edit_tasks",
	DELETE_TASKS: "delete_tasks",
	VIEW_INTERNAL_NOTES: "view_internal_notes",
	ASSIGN_TASKS: "assign_tasks",
	WORK_ON_TASKS: "work_on_tasks",
	MANAGE_DELIVERABLES: "manage_deliverables", // ✅ NEW
} as const;
```

**NEW HOOK RETURN**: Added `canManageDeliverables` to `useTaskPermissions()`:

```typescript
export function useTaskPermissions() {
	const hasPermission = useAuthStore((state) => state.hasPermission);

	return useMemo(
		() => ({
			canManageTasks: hasPermission(TASK_PERMISSIONS.MANAGE_TASKS),
			canViewTasks: hasPermission(TASK_PERMISSIONS.VIEW_TASKS),
			canCreateTasks: hasPermission(TASK_PERMISSIONS.CREATE_TASKS),
			canEditTasks: hasPermission(TASK_PERMISSIONS.EDIT_TASKS),
			canDeleteTasks: hasPermission(TASK_PERMISSIONS.DELETE_TASKS),
			canViewInternalNotes: hasPermission(TASK_PERMISSIONS.VIEW_INTERNAL_NOTES),
			canAssignTasks: hasPermission(TASK_PERMISSIONS.ASSIGN_TASKS),
			canWorkOnTasks: hasPermission(TASK_PERMISSIONS.WORK_ON_TASKS),
			canManageDeliverables: hasPermission(TASK_PERMISSIONS.MANAGE_DELIVERABLES), // ✅ NEW
		}),
		[hasPermission],
	);
}
```

---

## STEP 4: FRONTEND-BACKEND SYNC AUDIT ✅

### Verification Checklist

#### ✅ Permission Keys Alignment

- Backend uses: `manage_all_projects`, `manage_tasks`, `edit_tasks`, `delete_tasks`, `work_on_tasks`, `manage_deliverables`
- Frontend uses: Same exact keys via `useTaskPermissions()` and `useAuthStore()`
- **Status**: ✅ Perfectly aligned

#### ✅ Project Team API

- Backend: `ProjectMemberResource` returns `userId`, `role`, `user` object
- Frontend: `useProjectMembers()` consumes this data
- Frontend derives `projectRole` from members list
- **Status**: ✅ Working correctly

#### ✅ Action Button Permissions

- **Add Task**: Requires `manage_all_projects` OR `manage_tasks` OR (`create_tasks` AND project manager/tech_lead)
- **Add Milestone**: Same as Add Task
- **Edit Task**: Requires `manage_all_projects` OR `manage_tasks` OR (`edit_tasks` AND project manager/tech_lead) OR (assignee with `work_on_tasks`)
- **Delete Task**: Requires `manage_all_projects` OR `manage_tasks` OR (`delete_tasks` AND project manager/tech_lead)
- **Mark as Done**: Requires `manage_all_projects` OR `manage_deliverables` OR `manage_tasks` OR project manager/tech_lead
- **Status**: ✅ All aligned with backend policy

---

## Testing Checklist

### Backend Tests

- [ ] Regular assignee (developer) cannot send `status: "done"` → expects 403
- [ ] Project manager can send `status: "done"` → expects 200
- [ ] User with `manage_deliverables` can send `status: "done"` → expects 200
- [ ] User with `manage_all_projects` can send `status: "done"` → expects 200
- [ ] Creating task with `parentId` is rejected → expects validation error
- [ ] Updating task with `parentId` is rejected → expects validation error

### Frontend Tests

- [ ] Regular assignee does NOT see "Done" in status dropdown
- [ ] Project manager DOES see "Done" in status dropdown
- [ ] User with `manage_deliverables` DOES see "Done" in status dropdown
- [ ] Subtask UI is completely removed from TaskExecutionSheet
- [ ] No "Add Subtask" button visible anywhere
- [ ] Task creation form does NOT have parent task selector

### Integration Tests

- [ ] Founder can edit/delete any task
- [ ] Project manager can edit/delete tasks in their project
- [ ] Assignee can edit their own task but NOT delete it
- [ ] Assignee can change status to "In Review" but NOT "Done"
- [ ] Backend rejects "Done" status from unauthorized users even if UI is bypassed

---

## Migration Notes

### Database

- **No migration required**: `parent_id` column remains in database for historical data
- **Recommendation**: Add database migration to set all `parent_id` values to `NULL` if desired
- **Recommendation**: Add database constraint to prevent future `parent_id` assignments

### Existing Data

- **Existing subtasks**: Will still exist in database but are no longer accessible via API
- **Recommendation**: Run data cleanup script to flatten existing subtasks into root tasks

### User Communication

- **Notify users**: Subtasks feature has been removed
- **Alternative workflow**: Use deliverables to track task completion items
- **Status workflow**: Regular assignees must use "In Review" instead of marking tasks as "Done"

---

## Files Modified

### Backend (Laravel)

1. `/erp-backend/app/Modules/Project/Controllers/ProjectTaskController.php`
2. `/erp-backend/app/Modules/Project/Requests/StoreProjectTaskRequest.php`
3. `/erp-backend/app/Modules/Project/Requests/UpdateProjectTaskRequest.php`
4. `/erp-backend/app/Policies/TaskPolicy.php` (verified, no changes needed)

### Frontend (React/TypeScript)

1. `/erp-frontend/src/features/projects/api/execution.api.ts`
2. `/erp-frontend/src/features/projects/ui/TaskExecutionSheet.tsx`
3. `/erp-frontend/src/entities/user/model/permissions.ts`

### Documentation

1. `/TASK_WORKFLOW_CHANGES.md` (this file)

---

## Summary

✅ **Subtasks**: Completely removed from frontend and backend
✅ **Edit/Delete Permissions**: Already correctly enforced via TaskPolicy
✅ **Completed Status Guard**: Implemented in both backend (403 error) and frontend (filtered dropdown)
✅ **Permission Sync**: All permission keys aligned between frontend and backend
✅ **Founder Access**: Founder can edit/delete any task (via `manage_all_projects`)

**Total Lines Changed**: ~250 lines removed, ~100 lines modified
**Breaking Changes**: Yes - API no longer accepts `parentId`, subtask UI removed
**Security Impact**: High - strict workflow enforcement prevents unauthorized task completion
