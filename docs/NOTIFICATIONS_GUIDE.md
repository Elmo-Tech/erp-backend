# 📬 Notification System Guide - Business OS ERP

## Overview

This document describes the comprehensive notification system implemented in the Business OS ERP application.

## Architecture

### Channels

All notifications use three channels:

- **Database**: Stored in `notifications` table for in-app display
- **Broadcast**: Real-time via Laravel Echo/Pusher
- **Mail**: Email notifications via configured mail driver

### Queue Processing

All notifications implement `ShouldQueue` for asynchronous processing to improve performance.

---

## Available Notifications

### 📋 Task Management

#### 1. TaskAssigned

**Trigger**: When a user is assigned to a task  
**Recipients**: Assigned user(s)  
**Data**:

- Task details (title, priority, status, due date)
- Project information
- Assigner information
- Task description preview

**Usage**:

```php
use App\Notifications\TaskAssigned;

$assignee->notify(new TaskAssigned(
    task: $task,
    assignerName: $assigner->name,
    assignerId: $assigner->id
));
```

#### 2. TaskStatusChanged

**Trigger**: When task status is updated  
**Recipients**: Task assignees and watchers  
**Data**:

- Old and new status
- Task and project details
- Changer information
- Optional comment

**Usage**:

```php
use App\Notifications\TaskStatusChanged;

$task->assignees()->each(fn($user) =>
    $user->notify(new TaskStatusChanged(
        task: $task,
        oldStatus: 'in_progress',
        newStatus: 'testing',
        changedByName: $changer->name,
        changedById: $changer->id,
        comment: 'Ready for QA'
    ))
);
```

#### 3. TaskCommentAdded

**Trigger**: When someone adds a comment to a task  
**Recipients**: Task assignees (except commenter)  
**Data**:

- Task and project details
- Commenter information
- Comment text with preview

**Usage**:

```php
use App\Notifications\TaskCommentAdded;

$task->assignees()
    ->where('id', '!=', $commenter->id)
    ->each(fn($user) =>
        $user->notify(new TaskCommentAdded(
            task: $task,
            commenterName: $commenter->name,
            comment: $commentText,
            commenterId: $commenter->id
        ))
    );
```

---

### 📁 Project Management

#### 4. ProjectStatusChanged

**Trigger**: When project status is updated  
**Recipients**: Project team members  
**Data**:

- Old and new status
- Project and client details
- Changer information
- Optional reason

**Usage**:

```php
use App\Notifications\ProjectStatusChanged;

$project->members()->each(fn($member) =>
    $member->notify(new ProjectStatusChanged(
        project: $project,
        oldStatus: 'in_progress',
        newStatus: 'completed',
        changedByName: $changer->name,
        changedById: $changer->id,
        reason: 'All deliverables approved'
    ))
);
```

#### 5. UserAddedToProject

**Trigger**: When a user is added to a project team  
**Recipients**: Added user  
**Data**:

- Project details (title, client, status, deadline)
- Adder information
- User's role in project
- Project description preview

**Usage**:

```php
use App\Notifications\UserAddedToProject;

$user->notify(new UserAddedToProject(
    project: $project,
    addedByName: $adder->name,
    addedById: $adder->id,
    role: 'Developer'
));
```

#### 6. UserRemovedFromProject

**Trigger**: When a user is removed from a project team  
**Recipients**: Removed user  
**Data**:

- Project title and ID
- Remover information
- Optional reason

**Usage**:

```php
use App\Notifications\UserRemovedFromProject;

$user->notify(new UserRemovedFromProject(
    projectTitle: $project->title,
    projectId: $project->id,
    removedByName: $remover->name,
    removedById: $remover->id,
    reason: 'Project scope changed'
));
```

---

### 📦 Deliverables

#### 7. DeliverableSubmitted

**Trigger**: When a deliverable is submitted for review  
**Recipients**: Reviewers  
**Data**:

- Deliverable details
- Task and project information
- Submitter information
- File attachment indicator

**Usage**:

```php
use App\Notifications\DeliverableSubmitted;

$reviewers->each(fn($reviewer) =>
    $reviewer->notify(new DeliverableSubmitted(
        deliverable: $deliverable,
        submitterName: $submitter->name,
        submitterId: $submitter->id
    ))
);
```

#### 8. DeliverableReviewed

**Trigger**: When a deliverable is approved or rejected  
**Recipients**: Submitter  
**Data**:

- Deliverable details
- Reviewer information
- Status (approved/rejected)
- Review notes

**Usage**:

```php
use App\Notifications\DeliverableReviewed;

$submitter->notify(new DeliverableReviewed(
    deliverable: $deliverable,
    reviewerName: $reviewer->name,
    status: 'approved', // or 'rejected'
    reviewerId: $reviewer->id,
    reviewNotes: 'Excellent work!'
));
```

---

### 👥 User Management

#### 9. UserRoleChanged

**Trigger**: When a user's role or permissions are updated  
**Recipients**: Affected user  
**Data**:

- Old and new role
- Changer information
- Added permissions list
- Removed permissions list

**Usage**:

```php
use App\Notifications\UserRoleChanged;

$user->notify(new UserRoleChanged(
    oldRole: 'developer',
    newRole: 'senior_developer',
    changedByName: $admin->name,
    changedById: $admin->id,
    addedPermissions: ['approve_code', 'manage_juniors'],
    removedPermissions: []
));
```

---

### 💼 Sales & Leads

#### 10. LeadAssigned

**Trigger**: When a lead is assigned to a user  
**Recipients**: Assigned user  
**Data**:

- Lead details (company, contact, email, phone)
- Priority, temperature, source
- Assigner information
- Notes preview

**Usage**:

```php
use App\Notifications\LeadAssigned;

$assignee->notify(new LeadAssigned(
    lead: $lead,
    assignerName: $assigner->name,
    assignerId: $assigner->id
));
```

#### 11. LeadStatusChanged

**Trigger**: When lead status is updated  
**Recipients**: Lead owner  
**Data**:

- Old and new status
- Lead details
- Changer information
- Optional reason

**Usage**:

```php
use App\Notifications\LeadStatusChanged;

$lead->assignedTo->notify(new LeadStatusChanged(
    lead: $lead,
    oldStatus: 'new',
    newStatus: 'qualified',
    changedByName: $changer->name,
    changedById: $changer->id,
    reason: 'Budget confirmed'
));
```

---

### 📊 Performance & Innovation

#### 12. InnovationLogSubmitted

**Trigger**: When an innovation log is submitted  
**Recipients**: Reviewers/Admins  
**Data**: Innovation details, category, impact level

#### 13. InnovationLogVerified

**Trigger**: When an innovation log is verified  
**Recipients**: Submitter  
**Data**: Innovation details, verification status

---

### 📄 Proposals & Contracts

#### 14. ProposalSentNotification

**Trigger**: When a proposal is sent to a lead  
**Recipients**: Sales team members  
**Data**: Proposal details, lead information

#### 15. ProposalApprovedNotification

**Trigger**: When a proposal is approved  
**Recipients**: Project team  
**Data**: Proposal details, approval information

#### 16. ProposalRejectedNotification

**Trigger**: When a proposal is rejected  
**Recipients**: Sales team  
**Data**: Proposal details, rejection reason

#### 17. ProposalExpiringNotification

**Trigger**: When a proposal is about to expire  
**Recipients**: Sales team  
**Data**: Proposal details, expiry date

---

### 💰 Invoices

#### 18. InvoiceDue

**Trigger**: When an invoice is due  
**Recipients**: Client and finance team  
**Data**: Invoice details, due date

#### 19. InvoiceOverdue

**Trigger**: When an invoice becomes overdue  
**Recipients**: Client and finance team  
**Data**: Invoice details, overdue amount

---

### 📅 Reminders

#### 20. ActivityReminderNotification

**Trigger**: Scheduled reminder for activities  
**Recipients**: Activity owner  
**Data**: Activity details, reminder time

#### 21. MeetingReminderNotification

**Trigger**: Before a scheduled meeting  
**Recipients**: Meeting participants  
**Data**: Meeting details, time, location

#### 22. LeadFollowUpReminder

**Trigger**: Scheduled follow-up reminder  
**Recipients**: Lead owner  
**Data**: Lead details, follow-up notes

#### 23. ProjectDeadlineApproachingNotification

**Trigger**: When project deadline is near  
**Recipients**: Project team  
**Data**: Project details, deadline, remaining time

---

### ⚠️ Warnings

#### 24. ExpiryWarningNotification

**Trigger**: When contracts/quotations are expiring  
**Recipients**: Relevant team members  
**Data**: Entity details, expiry date

#### 25. QuotationExpiring

**Trigger**: When a quotation is about to expire  
**Recipients**: Sales team  
**Data**: Quotation details, expiry date

---

### 🔐 Client Portal

#### 26. ClientPortalAccessCreated

**Trigger**: When client portal access is created  
**Recipients**: Client  
**Data**: Login credentials, portal URL

---

## Notification Structure

### Standard Constructor Pattern

```php
public function __construct(
    public Model $entity,           // Main entity
    public string $actorName,       // Person who triggered action
    public ?int $actorId = null,    // Actor's ID (optional)
    public ?string $additionalInfo = null  // Extra context (optional)
) {}
```

### Standard toArray() Pattern

```php
public function toArray(object $notifiable): array
{
    return [
        'type' => 'notification_type',
        'entity_id' => $this->entity->id,
        'entity_name' => $this->entity->name,
        'actor_id' => $this->actorId,
        'actor_name' => $this->actorName,
        'timestamp' => now()->toISOString(),
        'message' => __(':actor did something to :entity', [
            'actor' => $this->actorName,
            'entity' => $this->entity->name,
        ]),
    ];
}
```

### Standard toMail() Pattern

```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject(__('Subject with :entity', ['entity' => $this->entity->name]))
        ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
        ->line(__(':actor did something.', ['actor' => $this->actorName]))
        ->line('**' . __('Details:') . '**')
        ->line(__('Field: :value', ['value' => $value]))
        ->action(__('View Entity'), url("/path/{$this->entity->id}"))
        ->line(__('Closing message.'));
}
```

---

## Frontend Integration

### Fetching Notifications

```php
// Get unread notifications
$notifications = auth()->user()->unreadNotifications;

// Get all notifications
$notifications = auth()->user()->notifications()->paginate(20);

// Mark as read
$notification->markAsRead();

// Mark all as read
auth()->user()->unreadNotifications->markAsRead();
```

### Real-time with Laravel Echo

```javascript
Echo.private(`App.Models.User.${userId}`).notification((notification) => {
    // Add to notification dropdown
    addNotificationToUI(notification);

    // Show toast
    toast.info(notification.message);

    // Update badge count
    updateNotificationBadge();
});
```

---

## Best Practices

### 1. Always Include Actor Information

```php
// ✅ Good
new TaskAssigned($task, $assigner->name, $assigner->id)

// ❌ Bad
new TaskAssigned($task, 'Someone')
```

### 2. Use Translations

```php
// ✅ Good
__(':actor assigned you to :task', ['actor' => $name, 'task' => $title])

// ❌ Bad
"{$name} assigned you to {$title}"
```

### 3. Truncate Long Text

```php
// ✅ Good
substr($description, 0, 150) . '...'

// ❌ Bad
$description // Could be 10,000 characters
```

### 4. Include Context

```php
// ✅ Good - Include project name for task
'task_title' => $task->title,
'project_name' => $task->project?->title,

// ❌ Bad - Only task info
'task_title' => $task->title,
```

### 5. Use ISO Dates

```php
// ✅ Good
'created_at' => $entity->created_at->toISOString()

// ❌ Bad
'created_at' => $entity->created_at->format('Y-m-d')
```

---

## Testing Notifications

### Manual Testing

```php
// In Tinker or test route
$user = User::find(1);
$task = Task::find(1);

$user->notify(new TaskAssigned(
    task: $task,
    assignerName: 'Test User',
    assignerId: 1
));
```

### Unit Testing

```php
use Illuminate\Support\Facades\Notification;

public function test_user_receives_task_assigned_notification()
{
    Notification::fake();

    $user = User::factory()->create();
    $task = Task::factory()->create();

    $user->notify(new TaskAssigned($task, 'Admin', 1));

    Notification::assertSentTo($user, TaskAssigned::class);
}
```

---

## Troubleshooting

### Notifications Not Sending

1. Check queue is running: `php artisan queue:work`
2. Check mail configuration in `.env`
3. Check notification channels in `via()` method
4. Check user has valid email address

### Real-time Not Working

1. Check Laravel Echo configuration
2. Check Pusher credentials
3. Check broadcasting is enabled
4. Check user is authenticated

### Database Notifications Not Storing

1. Check `notifications` table exists
2. Check `notifiable_type` and `notifiable_id` are set
3. Check `toArray()` returns valid data

---

## Summary

✅ **Removed**: Points notifications (silent tracking)  
✅ **Enhanced**: All existing notifications with detailed templates  
✅ **Created**: 11 new comprehensive notifications  
✅ **Standardized**: Consistent structure across all notifications  
✅ **Documented**: Complete usage guide with examples

All notifications now provide rich, detailed information to users about important events in the system.
