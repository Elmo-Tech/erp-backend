<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    return $user->projects()->where('projects.id', $projectId)->exists() ||
           $user->can('manage_projects');
});

Broadcast::channel('admin', function ($user) {
    return $user->can('view_admin_stats') || $user->can('manage_users');
});
