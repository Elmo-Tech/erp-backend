<?php

namespace App\Modules\Settings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Actions\LogClientAuditAction;
use App\Modules\Settings\Requests\StoreRoleRequest;
use App\Modules\Settings\Requests\UpdateRoleRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    private const PROTECTED_ROLE_NAMES = ['founder', 'admin', 'pmo', 'techdev', 'dev', 'client'];

    public function __construct(
        private LogClientAuditAction $auditLogService
    ) {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/roles",
     *     operationId="getAdminRoles",
     *     tags={"Admin Roles"},
     *     summary="List all roles with permissions",
     *     description="Retrieve all roles with their assigned permissions. Requires manage_roles permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Roles retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Roles retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="founder"),
     *                     @OA\Property(property="guardName", type="string", example="web"),
     *                     @OA\Property(property="isProtected", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="permissions",
     *                         type="array",
     *
     *                         @OA\Items(type="string", example="manage_users")
     *                     ),
     *
     *                     @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                     @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_roles permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage roles")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_roles')) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        $roles = Role::with('permissions')->get();

        $rolesData = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guardName' => $role->guard_name,
                'isProtected' => in_array($role->name, self::PROTECTED_ROLE_NAMES),
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'createdAt' => $role->created_at?->toISOString(),
                'updatedAt' => $role->updated_at?->toISOString(),
            ];
        });

        return ApiResponse::success(
            $rolesData,
            'Roles retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/roles/{id}",
     *     operationId="getAdminRole",
     *     tags={"Admin Roles"},
     *     summary="Get single role with permissions",
     *     description="Retrieve a specific role with its assigned permissions. Requires manage_roles permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Role retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Role retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="founder"),
     *                 @OA\Property(property="guardName", type="string", example="web"),
     *                 @OA\Property(property="isProtected", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="manage_users")
     *                 ),
     *
     *                 @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_roles permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage roles")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function show(int $role): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_roles')) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roleModel = Role::with('permissions')->findOrFail($role);

            $roleData = [
                'id' => $roleModel->id,
                'name' => $roleModel->name,
                'guardName' => $roleModel->guard_name,
                'isProtected' => in_array($roleModel->name, self::PROTECTED_ROLE_NAMES),
                'permissions' => $roleModel->permissions->pluck('name')->toArray(),
                'createdAt' => $roleModel->created_at?->toISOString(),
                'updatedAt' => $roleModel->updated_at?->toISOString(),
            ];

            return ApiResponse::success(
                $roleData,
                'Role retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Role not found');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/roles",
     *     operationId="createAdminRole",
     *     tags={"Admin Roles"},
     *     summary="Create new role",
     *     description="Create a new role with specified permissions. Requires manage_roles permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="project-manager", description="Role name (must be unique)"),
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *
     *                 @OA\Items(type="string", example="manage_projects"),
     *                 description="Array of permission names to assign to this role"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Role created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="project-manager"),
     *                 @OA\Property(property="guardName", type="string", example="web"),
     *                 @OA\Property(property="isProtected", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="manage_projects")
     *                 ),
     *
     *                 @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The permissions field must be an array.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_roles permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage roles")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_roles')) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        $validatedData = $request->validated();

        // Create the role
        $role = Role::create([
            'name' => $validatedData['name'],
            'guard_name' => 'web',
        ]);

        // Assign permissions if provided
        if (! empty($validatedData['permissions'])) {
            $role->syncPermissions($validatedData['permissions']);
        }

        // Log the action
        $this->auditLogService->log(
            'create_role',
            $role,
            ['role_name' => $role->name, 'permissions' => $validatedData['permissions'] ?? []]
        );

        // Load permissions for response
        $role->load('permissions');

        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'guardName' => $role->guard_name,
            'isProtected' => false, // New roles are never protected
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'createdAt' => $role->created_at?->toISOString(),
            'updatedAt' => $role->updated_at?->toISOString(),
        ];

        return ApiResponse::success(
            $roleData,
            'Role created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/roles/{id}",
     *     operationId="updateAdminRole",
     *     tags={"Admin Roles"},
     *     summary="Update role permissions",
     *     description="Update role name and permissions. Cannot update protected roles (founder, admin, pmo, techdev, dev, client). Requires manage_roles permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="senior-developer", description="Role name (must be unique)"),
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *
     *                 @OA\Items(type="string", example="manage_projects"),
     *                 description="Array of permission names to assign to this role"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Role updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="senior-developer"),
     *                 @OA\Property(property="guardName", type="string", example="web"),
     *                 @OA\Property(property="isProtected", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="manage_projects")
     *                 ),
     *
     *                 @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or protected role",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot update protected role"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="role",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="This role is protected and cannot be modified")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_roles permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage roles")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function update(UpdateRoleRequest $request, int $role): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_roles')) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roleModel = Role::findOrFail($role);

            // Protected roles can only be modified by users with manage_roles permission.
            // The manage_roles check above already gates this endpoint, so any user
            // who reaches here is authorised to edit ALL roles — including system ones.
            // (The old hard-block has been removed so the Founder can manage every role.)

            $validatedData = $request->validated();

            // Store old data for audit log
            $oldData = [
                'name' => $roleModel->name,
                'permissions' => $roleModel->permissions->pluck('name')->toArray(),
            ];

            // Update the role
            if (isset($validatedData['name'])) {
                $roleModel->update([
                    'name' => $validatedData['name'],
                ]);
            }

            // Update permissions
            if (isset($validatedData['permissions'])) {
                $roleModel->syncPermissions($validatedData['permissions']);
            }

            // Log the action
            $this->auditLogService->log(
                'update_role_permissions',
                $roleModel,
                [
                    'old_data' => $oldData,
                    'new_data' => [
                        'name' => $roleModel->name,
                        'permissions' => $validatedData['permissions'] ?? [],
                    ],
                ]
            );

            // Load permissions for response
            $roleModel->load('permissions');

            $roleData = [
                'id' => $roleModel->id,
                'name' => $roleModel->name,
                'guardName' => $roleModel->guard_name,
                'isProtected' => in_array($roleModel->name, self::PROTECTED_ROLE_NAMES),
                'permissions' => $roleModel->permissions->pluck('name')->toArray(),
                'createdAt' => $roleModel->created_at?->toISOString(),
                'updatedAt' => $roleModel->updated_at?->toISOString(),
            ];

            return ApiResponse::success(
                $roleData,
                'Role updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Role not found');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/roles/{id}",
     *     operationId="deleteAdminRole",
     *     tags={"Admin Roles"},
     *     summary="Delete role",
     *     description="Delete a role. Cannot delete protected roles (founder, admin, pmo, techdev, dev, client). Requires manage_roles permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Role deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Role deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete protected role",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete protected role"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="role",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="This role is protected and cannot be deleted")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_roles permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage roles")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function destroy(int $role): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_roles')) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roleModel = Role::findOrFail($role);

            // Check if role is protected
            if (in_array($roleModel->name, self::PROTECTED_ROLE_NAMES)) {
                return ApiResponse::validationError(
                    ['role' => ['This role is protected and cannot be deleted']],
                    'Cannot delete protected role'
                );
            }

            // Store role data for audit log
            $roleData = [
                'name' => $roleModel->name,
                'permissions' => $roleModel->permissions->pluck('name')->toArray(),
            ];

            // Delete the role
            $roleModel->delete();

            // Log the action
            $this->auditLogService->log(
                'delete_role',
                null, // Role is deleted, so no model to reference
                ['deleted_role' => $roleData]
            );

            return ApiResponse::success(
                null,
                'Role deleted successfully'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Role not found');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/permissions",
     *     operationId="getAdminPermissions",
     *     tags={"Admin Roles"},
     *     summary="List all permissions",
     *     description="Retrieve all available permissions in the system. Requires manage_roles permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permissions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="manage_users"),
     *                     @OA\Property(property="guardName", type="string", example="web"),
     *                     @OA\Property(property="group", type="string", example="User Management"),
     *                     @OA\Property(property="description", type="string", example="Allows managing user accounts"),
     *                     @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                     @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_roles permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage roles")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function permissions(): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_roles')) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        $permissions = Permission::all();

        $permissionsData = $permissions->map(function ($permission) {
            // Group permissions by their prefix for better organization
            $group = $this->getPermissionGroup($permission->name);

            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guardName' => $permission->guard_name,
                'group' => $group,
                'description' => $this->getPermissionDescription($permission->name),
                'createdAt' => $permission->created_at?->toISOString(),
                'updatedAt' => $permission->updated_at?->toISOString(),
            ];
        });

        return ApiResponse::success(
            $permissionsData,
            'Permissions retrieved successfully'
        );
    }

    /**
     * Get permission group — mirrors the seeder's comment sections exactly.
     */
    private function getPermissionGroup(string $permissionName): string
    {
        $groups = [
            // ── Dashboard ────────────────────────────────────────────────────
            'view_my_workspace' => 'Dashboard',
            'view_global_stats' => 'Dashboard',
            'view_dashboard' => 'Dashboard',
            'view_dashboard_overview' => 'Dashboard',
            'view_financial_stats' => 'Dashboard',
            'view_project_timeline' => 'Dashboard',
            'view_team_performance' => 'Dashboard',
            'view_personal_tasks' => 'Dashboard',
            'view_all_blockers' => 'Dashboard',
            'view_escalated_blockers' => 'Dashboard',
            'view_team_pulse' => 'Dashboard',

            // ── User Management ──────────────────────────────────────────────
            'manage_users' => 'User Management',
            'view_users' => 'User Management',
            'create_users' => 'User Management',
            'edit_users' => 'User Management',
            'delete_users' => 'User Management',

            // ── Role Management ──────────────────────────────────────────────
            'manage_roles' => 'Role Management',
            'view_roles' => 'Role Management',
            'create_roles' => 'Role Management',
            'edit_roles' => 'Role Management',
            'delete_roles' => 'Role Management',

            // ── Client Management ────────────────────────────────────────────
            'manage_clients' => 'Client Management',
            'view_clients' => 'Client Management',
            'create_clients' => 'Client Management',
            'edit_clients' => 'Client Management',
            'delete_clients' => 'Client Management',

            // ── Project Management ───────────────────────────────────────────
            'manage_projects' => 'Project Management',
            'view_projects' => 'Project Management',
            'view_all_projects' => 'Project Management',
            'view_assigned_projects' => 'Project Management',
            'create_projects' => 'Project Management',
            'edit_projects' => 'Project Management',
            'delete_projects' => 'Project Management',
            'approve_project_ui' => 'Project Management',
            'view_project_budget' => 'Project Management',
            'manage_project_settings' => 'Project Management',
            'manage_change_requests' => 'Project Management',
            'delete_change_requests' => 'Project Management',

            // ── Project Members ──────────────────────────────────────────────
            'manage_project_members' => 'Project Members',
            'view_project_members' => 'Project Members',
            'add_project_members' => 'Project Members',
            'remove_project_members' => 'Project Members',

            // ── Project Files ────────────────────────────────────────────────
            'manage_project_files' => 'Project Files',
            'view_project_files' => 'Project Files',
            'upload_project_files' => 'Project Files',
            'delete_project_files' => 'Project Files',
            'view_project_history' => 'Project Files',

            // ── Task Management ──────────────────────────────────────────────
            'manage_tasks' => 'Task Management',
            'view_tasks' => 'Task Management',
            'create_tasks' => 'Task Management',
            'edit_tasks' => 'Task Management',
            'delete_tasks' => 'Task Management',
            'assign_tasks' => 'Task Management',
            'view_internal_notes' => 'Task Management',
            'work_on_tasks' => 'Task Management',

            // ── Sales — Leads ────────────────────────────────────────────────
            'manage_leads' => 'Sales — Leads',
            'view_leads' => 'Sales — Leads',
            'create_leads' => 'Sales — Leads',
            'edit_leads' => 'Sales — Leads',
            'delete_leads' => 'Sales — Leads',
            'convert_leads' => 'Sales — Leads',

            // ── Sales — Quotations ───────────────────────────────────────────
            'view_quotations' => 'Sales — Quotations',
            'create_quotations' => 'Sales — Quotations',
            'edit_quotations' => 'Sales — Quotations',
            'delete_quotations' => 'Sales — Quotations',
            'calculate_quotations' => 'Sales — Quotations',

            // ── Sales — Contracts ────────────────────────────────────────────
            'manage_contracts' => 'Sales — Contracts',
            'view_contracts' => 'Sales — Contracts',
            'create_contracts' => 'Sales — Contracts',
            'edit_contracts' => 'Sales — Contracts',
            'delete_contracts' => 'Sales — Contracts',
            'sign_contracts' => 'Sales — Contracts',

            // ── Invoice Management ───────────────────────────────────────────
            'manage_invoices' => 'Invoice Management',
            'view_invoices' => 'Invoice Management',
            'create_invoices' => 'Invoice Management',
            'edit_invoices' => 'Invoice Management',
            'delete_invoices' => 'Invoice Management',
            'send_invoices' => 'Invoice Management',
            'cancel_invoices' => 'Invoice Management',

            // ── Wiki Management ──────────────────────────────────────────────
            'manage_wiki' => 'Wiki Management',
            'view_wiki' => 'Wiki Management',
            'create_wiki' => 'Wiki Management',
            'edit_wiki' => 'Wiki Management',
            'delete_wiki' => 'Wiki Management',
            'view_encrypted_content' => 'Wiki Management',

            // ── Time Tracking ────────────────────────────────────────────────
            'manage_time_entries' => 'Time Tracking',
            'view_time_entries' => 'Time Tracking',
            'start_timer' => 'Time Tracking',
            'stop_timer' => 'Time Tracking',
            'view_all_time_entries' => 'Time Tracking',
            'edit_time_entries' => 'Time Tracking',
            'delete_time_entries' => 'Time Tracking',
            'log_time' => 'Time Tracking',

            // ── Attendance ───────────────────────────────────────────────────
            'manage_attendance' => 'Attendance',
            'view_attendance' => 'Attendance',
            'view_all_attendance' => 'Attendance',
            'clock_out' => 'Attendance',

            // ── Employee Management ──────────────────────────────────────────
            'manage_employees' => 'Employee Management',
            'view_employees' => 'Employee Management',
            'create_employees' => 'Employee Management',
            'edit_employees' => 'Employee Management',
            'view_employee_salary' => 'Employee Management',
            'view_employee_performance' => 'Employee Management',

            // ── SOP Management ───────────────────────────────────────────────
            'manage_sops' => 'SOP Management',
            'view_morning_sop' => 'SOP Management',
            'submit_morning_sop' => 'SOP Management',

            // ── Meetings ─────────────────────────────────────────────────────
            'schedule_meetings' => 'Meetings',
            'delete_meetings' => 'Meetings',

            // ── Reports & Analytics ──────────────────────────────────────────
            'view_reports' => 'Reports & Analytics',
            'view_profitability' => 'Reports & Analytics',
            'view_cost' => 'Reports & Analytics',
            'view_quality_metrics' => 'Reports & Analytics',
            'view_user_performance' => 'Reports & Analytics',
            'view_attendance_analytics' => 'Reports & Analytics',
            'view_admin_stats' => 'Reports & Analytics',

            // ── Financial Data ───────────────────────────────────────────────
            'view_internal_costs' => 'Financial Data',
            'manage_internal_costs' => 'Financial Data',

            // ── Settings ─────────────────────────────────────────────────────
            'view_settings' => 'Settings',
            'manage_settings' => 'Settings',
            'delete_settings' => 'Settings',

            // ── Security & Audit ─────────────────────────────────────────────
            'view_audit_logs' => 'Security & Audit',

            // ── Tags ─────────────────────────────────────────────────────────
            'manage_tags' => 'Tags',
            'view_tags' => 'Tags',
        ];

        return $groups[$permissionName] ?? 'Other';
    }

    /**
     * Get permission description — mirrors the seeder's permission catalogue.
     */
    private function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            // ── Dashboard ────────────────────────────────────────────────────
            'view_my_workspace' => 'Access personal workspace',
            'view_global_stats' => 'Access company analytics dashboard',
            'view_dashboard' => 'Access the main dashboard',
            'view_dashboard_overview' => 'View dashboard overview statistics',
            'view_financial_stats' => 'View financial KPIs and revenue stats',
            'view_project_timeline' => 'View project timeline and Gantt charts',
            'view_team_performance' => 'View team performance metrics',
            'view_personal_tasks' => 'View own assigned tasks widget',
            'view_all_blockers' => 'View all project blockers across teams',
            'view_escalated_blockers' => 'View escalated blockers requiring attention',
            'view_team_pulse' => 'View team mood and pulse data',

            // ── User Management ──────────────────────────────────────────────
            'manage_users' => 'Full user management (create, edit, delete, toggle status)',
            'view_users' => 'View user list and profiles',
            'create_users' => 'Create new user accounts',
            'edit_users' => 'Edit existing user accounts',
            'delete_users' => 'Delete user accounts',

            // ── Role Management ──────────────────────────────────────────────
            'manage_roles' => 'Full role and permission management',
            'view_roles' => 'View roles and their assigned permissions',
            'create_roles' => 'Create new roles',
            'edit_roles' => 'Edit existing roles',
            'delete_roles' => 'Delete roles',

            // ── Client Management ────────────────────────────────────────────
            'manage_clients' => 'Full client management capabilities',
            'view_clients' => 'View client list and profiles',
            'create_clients' => 'Create new clients',
            'edit_clients' => 'Edit existing client information',
            'delete_clients' => 'Delete clients',

            // ── Project Management ───────────────────────────────────────────
            'manage_projects' => 'Full project management capabilities',
            'view_projects' => 'View projects page',
            'view_all_projects' => 'View every project regardless of membership',
            'view_assigned_projects' => 'View only projects the user is a member of',
            'create_projects' => 'Create new projects',
            'edit_projects' => 'Edit existing project details',
            'delete_projects' => 'Delete projects',
            'approve_project_ui' => 'Approve UI/UX deliverables',
            'view_project_budget' => 'View project budget and financial data',
            'manage_project_settings' => 'Manage project-level settings',
            'manage_change_requests' => 'Create and manage change requests',
            'delete_change_requests' => 'Delete change requests',

            // ── Project Members ──────────────────────────────────────────────
            'manage_project_members' => 'Full project member management',
            'view_project_members' => 'View project team members',
            'add_project_members' => 'Add members to a project',
            'remove_project_members' => 'Remove members from a project',

            // ── Project Files ────────────────────────────────────────────────
            'manage_project_files' => 'Full project file management',
            'view_project_files' => 'View project files and attachments',
            'upload_project_files' => 'Upload files to a project',
            'delete_project_files' => 'Delete project files',
            'view_project_history' => 'View project change history and audit trail',

            // ── Task Management ──────────────────────────────────────────────
            'manage_tasks' => 'Full task management capabilities',
            'view_tasks' => 'View task list and details',
            'create_tasks' => 'Create new tasks',
            'edit_tasks' => 'Edit existing tasks',
            'delete_tasks' => 'Delete tasks',
            'assign_tasks' => 'Assign tasks to team members',
            'view_internal_notes' => 'View internal (private) task notes',
            'work_on_tasks' => 'Work on and update assigned tasks',

            // ── Sales — Leads ────────────────────────────────────────────────
            'manage_leads' => 'Full lead management capabilities',
            'view_leads' => 'View lead list and details',
            'create_leads' => 'Create new leads',
            'edit_leads' => 'Edit existing leads',
            'delete_leads' => 'Delete leads',
            'convert_leads' => 'Convert a lead to a client or project',

            // ── Sales — Quotations ───────────────────────────────────────────
            'view_quotations' => 'View quotations',
            'create_quotations' => 'Create new quotations',
            'edit_quotations' => 'Edit existing quotations',
            'delete_quotations' => 'Delete quotations',
            'calculate_quotations' => 'Calculate and adjust quotation pricing',

            // ── Sales — Contracts ────────────────────────────────────────────
            'manage_contracts' => 'Full contract management capabilities',
            'view_contracts' => 'View contracts',
            'create_contracts' => 'Create new contracts',
            'edit_contracts' => 'Edit existing contracts',
            'delete_contracts' => 'Delete contracts',
            'sign_contracts' => 'Sign and finalise contracts',

            // ── Invoice Management ───────────────────────────────────────────
            'manage_invoices' => 'Full invoice management capabilities',
            'view_invoices' => 'View invoices',
            'create_invoices' => 'Create new invoices',
            'edit_invoices' => 'Edit existing invoices',
            'delete_invoices' => 'Delete invoices',
            'send_invoices' => 'Send invoices to clients',
            'cancel_invoices' => 'Cancel issued invoices',

            // ── Wiki Management ──────────────────────────────────────────────
            'manage_wiki' => 'Full wiki management capabilities',
            'view_wiki' => 'View wiki articles',
            'create_wiki' => 'Create new wiki articles',
            'edit_wiki' => 'Edit existing wiki articles',
            'delete_wiki' => 'Delete wiki articles',
            'view_encrypted_content' => 'View encrypted / sensitive wiki content',

            // ── Time Tracking ────────────────────────────────────────────────
            'manage_time_entries' => 'Full time entry management for all users',
            'view_time_entries' => 'View own time entries',
            'start_timer' => 'Start a time tracking timer',
            'stop_timer' => 'Stop a running timer',
            'view_all_time_entries' => 'View time entries for all users',
            'edit_time_entries' => 'Edit existing time entries',
            'delete_time_entries' => 'Delete time entries',
            'log_time' => 'Manually log time against a task',

            // ── Attendance ───────────────────────────────────────────────────
            'manage_attendance' => 'Full attendance management for all users',
            'view_attendance' => 'View own attendance records',
            'view_all_attendance' => 'View attendance records for all users',
            'clock_out' => 'Clock out from work',

            // ── Employee Management ──────────────────────────────────────────
            'manage_employees' => 'Full employee management capabilities',
            'view_employees' => 'View employee list and profiles',
            'create_employees' => 'Create new employee records',
            'edit_employees' => 'Edit employee records',
            'view_employee_salary' => 'View employee salary information',
            'view_employee_performance' => 'View employee performance evaluations',

            // ── SOP Management ───────────────────────────────────────────────
            'manage_sops' => 'Create, edit and manage all SOPs',
            'view_morning_sop' => 'View the daily morning SOP checklist',
            'submit_morning_sop' => 'Submit the morning SOP completion',

            // ── Meetings ─────────────────────────────────────────────────────
            'schedule_meetings' => 'Schedule and manage meetings',
            'delete_meetings' => 'Delete meetings',

            // ── Reports & Analytics ──────────────────────────────────────────
            'view_reports' => 'View system-wide reports',
            'view_profitability' => 'View project profitability reports',
            'view_cost' => 'View cost and expense information',
            'view_quality_metrics' => 'View quality metrics and bounce rates',
            'view_user_performance' => 'View individual user performance reports',
            'view_attendance_analytics' => 'View attendance analytics and trends',
            'view_admin_stats' => 'View administrative statistics overview',

            // ── Financial Data ───────────────────────────────────────────────
            'view_internal_costs' => 'View internal hourly cost rates',
            'manage_internal_costs' => 'Manage and update internal cost rates',

            // ── Settings ─────────────────────────────────────────────────────
            'view_settings' => 'View system settings',
            'manage_settings' => 'Manage and update system settings',
            'delete_settings' => 'Delete system settings records',

            // ── Security & Audit ─────────────────────────────────────────────
            'view_audit_logs' => 'View security and activity audit logs',

            // ── Tags ─────────────────────────────────────────────────────────
            'manage_tags' => 'Create, edit and delete tags',
            'view_tags' => 'View available tags',
        ];

        return $descriptions[$permissionName] ?? 'Permission for '.str_replace('_', ' ', $permissionName);
    }
}
