# Task Workflow Quick Reference Guide

## 🎯 What Changed?

### 1. Subtasks Removed ❌

- No more parent-child task relationships
- All tasks are now flat (single level)
- Use **Deliverables** instead to track completion items

### 2. "Completed" Status Restricted 🔒

- Regular assignees **CANNOT** mark tasks as "Done"
- Must use **"In Review"** status instead
- Only managers and authorized users can mark as "Done"

---

## 👥 Who Can Do What?

### Mark Task as "Done" (Completed)

✅ **Allowed:**

- Founder (has `manage_all_projects`)
- Deliverable Managers (has `manage_deliverables`)
- Task Managers (has `manage_tasks`)
- Project Managers (role = `manager`)
- Tech Leads (role = `tech_lead`)

❌ **NOT Allowed:**

- Developers (role = `developer`)
- QA (role = `qa`)
- Designers (role = `designer`)
- Any assignee without special permissions

### Edit Tasks

✅ **Allowed:**

- Founder (has `manage_all_projects`)
- Task Managers (has `manage_tasks`)
- Project Managers/Tech Leads (has `edit_tasks`)
- Task Assignee (has `work_on_tasks`, own task only)

### Delete Tasks

✅ **Allowed:**

- Founder (has `manage_all_projects`)
- Task Managers (has `manage_tasks`)
- Project Managers/Tech Leads (has `delete_tasks`)

---

## 📊 Status Workflow

### For Regular Assignees (Developers, QA, Designers)

```
Backlog → To Do → In Progress → In Review → [STOP]
                                    ↑
                              Your ceiling
```

**Available Statuses:**

- Backlog
- To Do
- In Progress
- **In Review** ← Use this instead of "Done"
- QA (if applicable)

**NOT Available:**

- ~~Done~~ ← Hidden from dropdown

### For Managers & Authorized Users

```
Backlog → To Do → In Progress → In Review → QA → Done
                                                    ↑
                                            You can complete
```

**Available Statuses:**

- All statuses including **Done**

---

## 🔄 New Workflow

### Before (With Subtasks)

```
Main Task
├── Subtask 1
├── Subtask 2
└── Subtask 3
```

### After (With Deliverables)

```
Task
├── Deliverable 1 ☐
├── Deliverable 2 ☑
└── Deliverable 3 ☐
```

**How to Use Deliverables:**

1. Open task in TaskExecutionSheet
2. Add deliverables (completion items)
3. Check off deliverables as you complete them
4. When all deliverables are done, set status to "In Review"
5. Manager/authorized user marks task as "Done"

---

## 🚨 Common Errors

### Error: "You cannot mark tasks as completed directly"

**Cause:** You tried to set status to "Done" but don't have permission

**Solution:**

1. Set status to **"In Review"** instead
2. Notify your project manager
3. Manager will mark it as "Done" after review

### Error: "Validation failed for task creation"

**Cause:** You tried to create a subtask (no longer supported)

**Solution:**

1. Create a regular task instead
2. Add deliverables to track completion items

---

## 💡 Best Practices

### For Developers/QA

1. ✅ Break down work into deliverables
2. ✅ Update status to "In Progress" when starting
3. ✅ Check off deliverables as you complete them
4. ✅ Set status to "In Review" when done
5. ✅ Add notes/comments for reviewers

### For Project Managers

1. ✅ Review tasks in "In Review" status
2. ✅ Verify all deliverables are complete
3. ✅ Test/validate the work
4. ✅ Mark as "Done" if approved
5. ✅ Or move back to "In Progress" with feedback

---

## 🔍 Troubleshooting

### "I can't see the Done option in status dropdown"

**This is expected!** Regular assignees cannot mark tasks as "Done". Use "In Review" instead.

### "I need to create a subtask"

**Subtasks are removed.** Use deliverables instead:

1. Open the task
2. Scroll to "Deliverables" section
3. Click "Add a deliverable"
4. Enter the deliverable title

### "I accidentally marked a task as Done"

**If you're a manager:** You can change it back to any status.
**If you're an assignee:** You shouldn't be able to do this. Contact your manager.

---

## 📞 Support

If you encounter issues or have questions:

1. Check this guide first
2. Contact your project manager
3. Reach out to the development team

---

## 🎓 Training Resources

### Video Tutorials (Coming Soon)

- [ ] New Task Workflow Overview
- [ ] Using Deliverables Instead of Subtasks
- [ ] Status Workflow for Assignees
- [ ] Status Workflow for Managers

### Documentation

- ✅ [TASK_WORKFLOW_CHANGES.md](./TASK_WORKFLOW_CHANGES.md) - Detailed technical changelog
- ✅ [IMPLEMENTATION_SUMMARY.md](./IMPLEMENTATION_SUMMARY.md) - Implementation summary
- ✅ [QUICK_REFERENCE.md](./QUICK_REFERENCE.md) - This guide

---

**Last Updated:** May 12, 2026
**Version:** 1.0
