<?php

namespace App\Modules\User\Actions;

use App\Modules\Client\Actions\LogClientAuditAction;
use App\Modules\User\Models\User;

class CreateUserAction
{
    public function __construct(
        private LogClientAuditAction $auditLogService
    ) {}

    public function execute(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'skills' => $data['skills'] ?? [],
            'is_active' => $data['isActive'] ?? true,
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $this->auditLogService->log(
            'create_user',
            $user,
            [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $data['roles'] ?? [],
                'created_by' => auth()->user()->name,
                'created_by_id' => auth()->id(),
            ]
        );

        return $user->load('roles');
    }
}
