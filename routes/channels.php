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
           $user->hasRole(['admin', 'super-admin']);
});

Broadcast::channel('admin', function ($user) {
    return $user->hasRole(['admin', 'super-admin']);
});
