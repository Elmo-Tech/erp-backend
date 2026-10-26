<?php

use App\Models\User;
use App\Policies\WorkMonitoringPolicy;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('work-monitoring', fn (User $user) => app(WorkMonitoringPolicy::class)->view($user));

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for user-specific events (timer updates, notifications, etc.)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Project-specific channels for project updates
Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    // Check if user has access to this project
    return $user->projects()->where('projects.id', $projectId)->exists() ||
           $user->hasRole(['admin', 'super-admin']);
});

// Task-specific channels for task updates
Broadcast::channel('task.{taskId}', function ($user, $taskId) {
    // Check if user is assigned to this task or has admin role
    return $user->assignedTasks()->where('tasks.id', $taskId)->exists() ||
           $user->hasRole(['admin', 'super-admin']);
});

// Admin-only channel for system-wide notifications
Broadcast::channel('admin', function ($user) {
    return $user->hasRole(['admin', 'super-admin']);
});

// Team-specific channels (if you have teams)
Broadcast::channel('team.{teamId}', function ($user, $teamId) {
    // Implement team membership check if you have teams
    return $user->hasRole(['admin', 'super-admin']);
});
