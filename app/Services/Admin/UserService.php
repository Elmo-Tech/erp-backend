<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Modules\Client\Actions\LogClientAuditAction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserService
{
    public function __construct(
        private LogClientAuditAction $auditLogService
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
    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'skills' => $data['skills'] ?? [],
            'is_active' => $data['isActive'] ?? true,
        ]);

        // Assign roles if provided
        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Log the action
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

    /**
     * Update an existing user
     */
    public function updateUser(User $user, array $data): User
    {
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
    public function deleteUser(User $user): bool
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return false;
        }

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
        $user->syncRoles([]);

        return $user->delete();
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(User $user): User
    {
        $oldStatus = $user->is_active;
        $user->is_active = ! $user->is_active;
        $user->save();

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
    public function assignRoles(User $user, array $roles): User
    {
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
