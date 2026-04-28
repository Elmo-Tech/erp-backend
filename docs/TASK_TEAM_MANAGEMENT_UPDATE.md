# Task Team Management Update - Documentation

## تاريخ التحديث: 2025-01-XX

## التغييرات المطبقة

### 1. إمكانية حذف الأعضاء من أي نوع تاسك

**قبل التحديث:**

- Main Tasks: يمكن حذف الأعضاء ويتم الحذف من جميع الـ children recursively
- Weekly/Subtasks: لا يمكن حذف الأعضاء (view-only)

**بعد التحديث:**

- Main Tasks: يمكن حذف الأعضاء ويتم الحذف من Weekly Tasks فقط (ليس Subtasks)
- Weekly Tasks: يمكن حذف الأعضاء بشكل مستقل
- Subtasks: يمكن حذف الأعضاء بشكل مستقل

**الملفات المعدلة:**

- `app/Services/Tasks/TaskService.php` - تعديل `removeAssignee()` و `removeAssigneeFromWeeklyTasks()`
- `app/Policies/TaskPolicy.php` - تعديل `manageTeam()` للسماح بإدارة الفريق في جميع أنواع التاسكات

---

### 2. إضافة عضو للـ Main Task يضيفه للـ Weekly Tasks فقط

**قبل التحديث:**

- عند إضافة عضو لـ Main Task، يتم إضافته لجميع الـ children recursively (Weekly + Subtasks)

**بعد التحديث:**

- عند إضافة عضو لـ Main Task، يتم إضافته للـ Weekly Tasks فقط
- Subtasks لا تحصل على الأعضاء تلقائياً
- يمكن إضافة/حذف الأعضاء في Weekly/Subtasks بشكل مستقل

**الملفات المعدلة:**

- `app/Services/Tasks/TaskService.php` - تعديل `propagateAssigneeToChildren()` لإضافة الأعضاء للـ Weekly Tasks فقط

---

### 3. حل مشكلة تكرار التاسكات في صفحة Tasks العامة

**المشكلة:**

- عند استخدام `whereHas('assignees')` في الفلترة، كانت التاسكات تظهر مكررة

**الحل:**

- إضافة `distinct()` في `getAllTasks()` لمنع التكرار

**الملفات المعدلة:**

- `app/Services/Tasks/TaskService.php` - إضافة `$query->distinct()` في `getAllTasks()`

---

## API Endpoints المحدثة

### إضافة عضو للتاسك

```http
POST /api/v1/tasks/{task}/assignees
```

**Request Body:**

```json
{
    "userId": 2,
    "role": "developer",
    "isPrimary": true
}
```

**السلوك:**

- Main Task: يضيف العضو للـ Main Task + جميع Weekly Tasks (ليس Subtasks)
- Weekly Task: يضيف العضو للـ Weekly Task فقط
- Subtask: يضيف العضو للـ Subtask فقط

**الصلاحيات المطلوبة:**

- Admin/Super Admin أو
- `manage_task_team` permission

---

### حذف عضو من التاسك

```http
DELETE /api/v1/tasks/{task}/assignees/{userId}
```

**السلوك:**

- Main Task: يحذف العضو من Main Task + جميع Weekly Tasks (ليس Subtasks)
- Weekly Task: يحذف العضو من Weekly Task فقط
- Subtask: يحذف العضو من Subtask فقط

**الصلاحيات المطلوبة:**

- Admin/Super Admin أو
- `manage_task_team` permission

---

## أمثلة الاستخدام

### مثال 1: إضافة عضو لـ Main Task

```
Main Task (ID: 1)
├── Weekly Task 1 (ID: 2)
│   ├── Subtask 1 (ID: 3)
│   └── Subtask 2 (ID: 4)
└── Weekly Task 2 (ID: 5)
    └── Subtask 3 (ID: 6)
```

**عند إضافة User ID: 10 للـ Main Task:**

```http
POST /api/v1/tasks/1/assignees
{
  "userId": 10,
  "role": "developer",
  "isPrimary": true
}
```

**النتيجة:**

- ✅ User 10 يضاف للـ Main Task (ID: 1)
- ✅ User 10 يضاف للـ Weekly Task 1 (ID: 2)
- ✅ User 10 يضاف للـ Weekly Task 2 (ID: 5)
- ❌ User 10 لا يضاف للـ Subtasks (3, 4, 6)

---

### مثال 2: حذف عضو من Main Task

**عند حذف User ID: 10 من Main Task:**

```http
DELETE /api/v1/tasks/1/assignees/10
```

**النتيجة:**

- ✅ User 10 يحذف من Main Task (ID: 1)
- ✅ User 10 يحذف من Weekly Task 1 (ID: 2)
- ✅ User 10 يحذف من Weekly Task 2 (ID: 5)
- ❌ User 10 لا يحذف من Subtasks (3, 4, 6) - يبقى إذا كان موجود

---

### مثال 3: إدارة الأعضاء في Weekly Task بشكل مستقل

**إضافة عضو جديد للـ Weekly Task فقط:**

```http
POST /api/v1/tasks/2/assignees
{
  "userId": 15,
  "role": "reviewer",
  "isPrimary": false
}
```

**النتيجة:**

- ✅ User 15 يضاف للـ Weekly Task 1 (ID: 2) فقط
- ❌ لا يضاف للـ Main Task أو Subtasks

**حذف عضو من Weekly Task:**

```http
DELETE /api/v1/tasks/2/assignees/15
```

**النتيجة:**

- ✅ User 15 يحذف من Weekly Task 1 (ID: 2) فقط
- ❌ لا يؤثر على Main Task أو Subtasks

---

### مثال 4: إدارة الأعضاء في Subtask بشكل مستقل

**إضافة عضو للـ Subtask:**

```http
POST /api/v1/tasks/3/assignees
{
  "userId": 20,
  "role": "developer",
  "isPrimary": true
}
```

**النتيجة:**

- ✅ User 20 يضاف للـ Subtask 1 (ID: 3) فقط
- ❌ لا يضاف للـ Main Task أو Weekly Task

**حذف عضو من Subtask:**

```http
DELETE /api/v1/tasks/3/assignees/20
```

**النتيجة:**

- ✅ User 20 يحذف من Subtask 1 (ID: 3) فقط
- ❌ لا يؤثر على Main Task أو Weekly Task

---

## ملاحظات مهمة

1. **الصلاحيات:**
    - يجب أن يكون المستخدم Admin/Super Admin أو لديه صلاحية `manage_task_team`
    - يمكن التحقق من الصلاحيات عبر `TaskPolicy::manageTeam()`

2. **التحقق من الأعضاء:**
    - عند إضافة عضو لـ Weekly/Subtask، يتم التحقق من أنه موجود في فريق الـ Main Task
    - إذا لم يكن موجود، يظهر خطأ: "This user is not part of the project team. Add them to the Main Task first."

3. **منع التكرار:**
    - لا يمكن إضافة نفس المستخدم مرتين لنفس التاسك
    - يظهر خطأ: "This user is already assigned to this task."

4. **Activity Logging:**
    - جميع عمليات الإضافة والحذف يتم تسجيلها في `task_activities`
    - يمكن تتبع من قام بالإضافة/الحذف ومتى

---

## Testing Checklist

- [ ] إضافة عضو لـ Main Task ويظهر في Weekly Tasks فقط
- [ ] إضافة عضو لـ Weekly Task بشكل مستقل
- [ ] إضافة عضو لـ Subtask بشكل مستقل
- [ ] حذف عضو من Main Task ويحذف من Weekly Tasks فقط
- [ ] حذف عضو من Weekly Task بشكل مستقل
- [ ] حذف عضو من Subtask بشكل مستقل
- [ ] التحقق من عدم تكرار التاسكات في صفحة Tasks العامة
- [ ] التحقق من ظهور التاسكات المكتملة في القائمة
- [ ] التحقق من الصلاحيات (manage_task_team)
- [ ] التحقق من Activity Logging

---

## الخطوات التالية (Frontend)

يجب تحديث الـ Frontend لدعم التغييرات الجديدة:

1. **صفحة Task Details:**
    - إضافة زر "Remove" لكل عضو في الفريق
    - السماح بحذف الأعضاء من أي نوع تاسك

2. **صفحة Tasks List:**
    - التأكد من عدم ظهور التاسكات مكررة
    - التأكد من ظهور التاسكات المكتملة

3. **Team Management UI:**
    - توضيح أن إضافة عضو للـ Main Task يضيفه للـ Weekly Tasks فقط
    - إظهار رسالة توضيحية عند إضافة/حذف الأعضاء

---

## الكود المعدل

### TaskService.php

```php
// إضافة عضو - يضاف للـ Weekly Tasks فقط
private function propagateAssigneeToChildren(Task $task, int $userId, string $role, bool $isPrimary, User $performer): void
{
    // Only propagate if this is a Main Task
    if ($task->type !== TaskType::MAIN) {
        return;
    }

    // Get only Weekly Tasks (direct children of Main Task)
    $weeklyTasks = $task->children()->where('type', TaskType::WEEKLY)->get();

    foreach ($weeklyTasks as $weeklyTask) {
        // Check if assignee already exists on weekly task
        $existingAssignee = $weeklyTask->assignees()->where('user_id', $userId)->first();

        if (!$existingAssignee) {
            TaskAssignee::create([
                'task_id' => $weeklyTask->id,
                'user_id' => $userId,
                'role' => $role,
                'is_primary' => $isPrimary,
                'created_by' => $performer->id,
                'updated_by' => $performer->id,
            ]);
        }
    }
}

// حذف عضو - يحذف من Weekly Tasks فقط
private function removeAssigneeFromWeeklyTasks(Task $task, int $userId): void
{
    $weeklyTasks = $task->children()->where('type', TaskType::WEEKLY)->get();

    foreach ($weeklyTasks as $weeklyTask) {
        $weeklyTask->assignees()->where('user_id', $userId)->delete();
    }
}

// منع التكرار في القائمة
public function getAllTasks(array $filters = [], int $perPage = 15): LengthAwarePaginator
{
    $query = Task::query()->with(['assignee', 'project', 'tags', 'assignees.user']);

    // ... filters ...

    // Use distinct to prevent duplicate results
    $query->distinct();

    return $query->orderBy('created_at', 'desc')->paginate($perPage);
}
```

### TaskPolicy.php

```php
// السماح بإدارة الفريق في جميع أنواع التاسكات
public function manageTeam(User $user, Task $task): bool
{
    if ($user->hasRole('client')) {
        return false;
    }

    if ($user->hasAnyRole(['admin', 'super-admin'])) {
        return true;
    }

    // For all task types, require manage_task_team permission
    return $user->can('manage_task_team');
}
```
