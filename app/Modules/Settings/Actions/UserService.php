<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Client\Actions\LogClientAuditAction;
use App\Modules\Settings\Models\User;
use App\Modules\Settings\Policies\UserAdministrationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserService
{
    public function __construct(
        private LogClientAuditAction $auditLogService,
        private CreateUserAction $createUserAction,
        private UserAdministrationPolicy $administrationPolicy,
    ) {}

    /**
     * Get paginated users with filters
     */
    public function getUsers(array $filters = []): LengthAwarePaginator
    {
        $query = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('role', 'roles.name'),
                AllowedFilter::exact('isActive', 'is_active'),
            ])
            ->allowedSorts(['name', 'email', 'created_at'])
            ->with('roles')
            ->defaultSort('-created_at');

        $perPage = $filters['perPage'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get a single user by ID
     */
    public function getUserById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    /**
     * Create a new user
     */
    public function createUser(User $actor, array $data): User
    {
        if (! empty($data['roles'])) {
            $this->administrationPolicy->authorizeRoleAssignment($actor, null, $data['roles']);
        }

        return $this->createUserAction->execute($data);
    }

    /**
     * Update an existing user
     */
    public function updateUser(User $actor, User $user, array $data): User
    {
        $this->administrationPolicy->authorizeManageTarget($actor, $user);

        if (isset($data['password'])) {
            $this->administrationPolicy->authorizePasswordReset($actor, $user);
        }

        if (isset($data['roles'])) {
            $this->administrationPolicy->authorizeRoleAssignment($actor, $user, $data['roles']);
        }

        $shouldRevokeTokens = isset($data['password'])
            || (array_key_exists('isActive', $data) && ! $data['isActive']);

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'skills' => $user->skills,
            'roles' => $user->roles->pluck('name')->toArray(),
            'is_active' => $user->is_active,
        ];

        // Update basic fields
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (array_key_exists('skills', $data)) {
            $user->skills = $data['skills'];
        }

        if (isset($data['isActive'])) {
            $user->is_active = $data['isActive'];
        }

        $user->save();

        if ($shouldRevokeTokens) {
            $user->tokens()->delete();
        }

        // Sync roles if provided
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Log the action
        $this->auditLogService->log(
            'update_user',
            $user,
            [
                'old_data' => $oldData,
                'new_data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'skills' => $user->skills,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'is_active' => $user->is_active,
                ],
                'updated_by' => auth()->user()->name,
                'updated_by_id' => auth()->id(),
            ]
        );

        return $user->load('roles');
    }

    /**
     * Delete a user
     */
    public function deleteUser(User $actor, User $user): bool
    {
        $this->administrationPolicy->authorizeDelete($actor, $user);

        // Log before deletion
        $this->auditLogService->log(
            'delete_user',
            $user,
            [
                'name' => $user->name,
                'email' => $user->email,
                'deleted_by' => auth()->user()->name,
                'deleted_by_id' => auth()->id(),
            ]
        );

        // Remove roles and delete
        $user->tokens()->delete();
        $user->syncRoles([]);
        $user->syncPermissions([]);

        return $user->delete();
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(User $actor, User $user): User
    {
        $this->administrationPolicy->authorizeManageTarget($actor, $user);
        $oldStatus = $user->is_active;
        $user->is_active = ! $user->is_active;
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        $this->auditLogService->log(
            $user->is_active ? 'activate_user' : 'deactivate_user',
            $user,
            [
                'old_status' => $oldStatus,
                'new_status' => $user->is_active,
                'changed_by' => auth()->user()->name,
                'changed_by_id' => auth()->id(),
            ]
        );

        return $user;
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(User $actor, User $user, array $roles): User
    {
        $this->administrationPolicy->authorizeRoleAssignment($actor, $user, $roles);
        $oldRoles = $user->roles->pluck('name')->toArray();
        $user->syncRoles($roles);

        $this->auditLogService->log(
            'assign_roles',
            $user,
            [
                'old_roles' => $oldRoles,
                'new_roles' => $roles,
                'changed_by' => auth()->user()->name,
                'changed_by_id' => auth()->id(),
            ]
        );

        return $user->load('roles');
    }
}
