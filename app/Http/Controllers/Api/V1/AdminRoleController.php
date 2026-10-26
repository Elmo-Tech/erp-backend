<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\StoreRoleRequest;
use App\Http\Requests\V1\Admin\UpdateRoleRequest;
use App\Policies\UserAdministrationPolicy;
use App\Services\Client\AuditLogService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminRoleController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private UserAdministrationPolicy $administrationPolicy,
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
     *                     @OA\Property(property="name", type="string", example="admin"),
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
        if (! auth()->user()->hasAnyPermission(['manage_roles', 'view_roles', 'manage_employees', 'assign_user_roles'])) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roles = Role::with('permissions')->get();

            $rolesData = $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guardName' => $role->guard_name,
                    'isProtected' => in_array($role->name, ['admin', 'client', 'super-admin']),
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'createdAt' => $role->created_at?->toISOString(),
                    'updatedAt' => $role->updated_at?->toISOString(),
                ];
            });

            return ApiResponse::success(
                $rolesData,
                'Roles retrieved successfully'
            );
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve roles: '.$e->getMessage(),
                500
            );
        }
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
     *                 @OA\Property(property="name", type="string", example="admin"),
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
        if (! auth()->user()->hasAnyPermission(['manage_roles', 'view_roles'])) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roleModel = Role::with('permissions')->findOrFail($role);

            $roleData = [
                'id' => $roleModel->id,
                'name' => $roleModel->name,
                'guardName' => $roleModel->guard_name,
                'isProtected' => in_array($roleModel->name, ['admin', 'client', 'super-admin']),
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
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve role: '.$e->getMessage(),
                500
            );
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
        if (! auth()->user()->hasAnyPermission(['manage_roles', 'create_roles'])) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $validatedData = $request->validated();
            $this->administrationPolicy->authorizeRoleCreation(
                $request->user(),
                $validatedData['permissions'] ?? []
            );

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
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create role: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/roles/{id}",
     *     operationId="updateAdminRole",
     *     tags={"Admin Roles"},
     *     summary="Update role permissions",
     *     description="Update role name and permissions. Cannot update protected roles (admin, client, super-admin). Requires manage_roles permission.",
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
        if (! auth()->user()->hasAnyPermission(['manage_roles', 'edit_roles'])) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roleModel = Role::findOrFail($role);

            $validatedData = $request->validated();
            $this->administrationPolicy->authorizeRoleMutation(
                $request->user(),
                $roleModel,
                $validatedData['permissions'] ?? $roleModel->permissions->pluck('name')->all()
            );

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
                'isProtected' => in_array($roleModel->name, ['admin', 'client', 'super-admin']),
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
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update role: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/roles/{id}",
     *     operationId="deleteAdminRole",
     *     tags={"Admin Roles"},
     *     summary="Delete role",
     *     description="Delete a role. Cannot delete protected roles (admin, client, super-admin). Requires manage_roles permission.",
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
        if (! auth()->user()->hasAnyPermission(['manage_roles', 'delete_roles'])) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $roleModel = Role::findOrFail($role);

            $this->administrationPolicy->authorizeRoleDeletion(request()->user(), $roleModel);

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
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete role: '.$e->getMessage(),
                500
            );
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
        if (! auth()->user()->hasAnyPermission(['manage_roles', 'view_roles', 'create_roles', 'edit_roles'])) {
            return ApiResponse::forbidden('You do not have permission to manage roles');
        }

        try {
            $permissions = Permission::all();

            $permissionsData = $permissions->map(function ($permission) {
                // Group permissions by their prefix for better organization
                $group = PermissionCatalog::group($permission->name);

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
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve permissions: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * Get permission group based on permission name prefix
     */
    private function getPermissionGroup(string $permissionName): string
    {
        $groups = [
            // User Management
            'manage_users' => 'User Management',
            'view_users' => 'User Management',
            'create_users' => 'User Management',
            'edit_users' => 'User Management',
            'delete_users' => 'User Management',

            // Role Management
            'manage_roles' => 'Role Management',
            'view_roles' => 'Role Management',
            'create_roles' => 'Role Management',
            'edit_roles' => 'Role Management',
            'delete_roles' => 'Role Management',

            // Client Management
            'manage_clients' => 'Client Management',
            'view_clients' => 'Client Management',
            'create_clients' => 'Client Management',
            'edit_clients' => 'Client Management',
            'delete_clients' => 'Client Management',

            // Project Management
            'manage_projects' => 'Project Management',
            'view_projects' => 'Project Management',
            'create_projects' => 'Project Management',
            'edit_projects' => 'Project Management',
            'delete_projects' => 'Project Management',
            'approve_project_ui' => 'Project Management',
            'view_all_projects' => 'Project Management',
            'manage_all_projects' => 'Project Management',
            'manage_project_workflows' => 'Project Management',
            'manage_workflow_templates' => 'Project Management',

            // Project Members
            'manage_project_members' => 'Project Members',
            'view_project_members' => 'Project Members',
            'add_project_members' => 'Project Members',
            'remove_project_members' => 'Project Members',

            // Project Files
            'manage_project_files' => 'Project Files',
            'view_project_files' => 'Project Files',
            'upload_project_files' => 'Project Files',
            'delete_project_files' => 'Project Files',

            // Project History
            'view_project_history' => 'Project History',

            // Task Management
            'manage_tasks' => 'Task Management',
            'view_tasks' => 'Task Management',
            'create_tasks' => 'Task Management',
            'edit_tasks' => 'Task Management',
            'delete_tasks' => 'Task Management',
            'view_internal_notes' => 'Task Management',
            'assign_tasks' => 'Task Management',

            // Test Scenarios
            'manage_test_scenarios' => 'Test Scenarios',
            'view_test_scenarios' => 'Test Scenarios',
            'create_test_scenarios' => 'Test Scenarios',
            'complete_test_scenarios' => 'Test Scenarios',
            'verify_test_scenarios' => 'Test Scenarios',

            // Sales - Leads
            'manage_sales' => 'Sales Management',
            'view_leads' => 'Sales Management',
            'create_leads' => 'Sales Management',
            'edit_leads' => 'Sales Management',
            'delete_leads' => 'Sales Management',

            // Sales - Quotations
            'view_quotations' => 'Quotations',
            'create_quotations' => 'Quotations',
            'edit_quotations' => 'Quotations',
            'delete_quotations' => 'Quotations',
            'calculate_quotations' => 'Quotations',

            // Invoice Management
            'manage_invoices' => 'Invoice Management',
            'view_invoices' => 'Invoice Management',
            'create_invoices' => 'Invoice Management',
            'edit_invoices' => 'Invoice Management',
            'delete_invoices' => 'Invoice Management',

            // Wiki Management
            'manage_wiki' => 'Wiki Management',
            'view_wiki' => 'Wiki Management',
            'create_wiki' => 'Wiki Management',
            'edit_wiki' => 'Wiki Management',
            'delete_wiki' => 'Wiki Management',
            'view_encrypted_content' => 'Wiki Management',

            // Time Tracking
            'manage_time_entries' => 'Time Tracking',
            'view_time_entries' => 'Time Tracking',
            'start_timer' => 'Time Tracking',
            'stop_timer' => 'Time Tracking',
            'view_all_time_entries' => 'Time Tracking',

            // Attendance
            'manage_attendance' => 'Attendance',
            'view_attendance' => 'Attendance',
            'view_all_attendance' => 'Attendance',
            'clock_out' => 'Attendance',

            // Dashboard
            'view_dashboard' => 'Dashboard',
            'view_dashboard_overview' => 'Dashboard',

            // Reports & Analytics
            'view_reports' => 'Reports & Analytics',
            'view_profitability' => 'Reports & Analytics',
            'view_cost' => 'Reports & Analytics',
            'view_quality_metrics' => 'Reports & Analytics',
            'view_user_performance' => 'Reports & Analytics',
            'view_attendance_analytics' => 'Reports & Analytics',

            // Admin Statistics
            'view_admin_stats' => 'Admin Statistics',

            // Financial Data
            'view_internal_costs' => 'Financial Data',
            'manage_internal_costs' => 'Financial Data',

            // Security & Audit
            'view_audit_logs' => 'Security & Audit',

            // Tags
            'manage_tags' => 'Tags',
            'view_tags' => 'Tags',
        ];

        return $groups[$permissionName] ?? 'Other';
    }

    /**
     * Get permission description based on permission name
     */
    private function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            // User Management
            'manage_users' => 'Full user management capabilities',
            'view_users' => 'View user information',
            'create_users' => 'Create new user accounts',
            'edit_users' => 'Edit existing user accounts',
            'delete_users' => 'Delete user accounts',

            // Role Management
            'manage_roles' => 'Full role and permission management',
            'view_roles' => 'View roles and permissions',
            'create_roles' => 'Create new roles',
            'edit_roles' => 'Edit existing roles',
            'delete_roles' => 'Delete roles',

            // Client Management
            'manage_clients' => 'Full client management capabilities',
            'view_clients' => 'View client information',
            'create_clients' => 'Create new clients',
            'edit_clients' => 'Edit existing clients',
            'delete_clients' => 'Delete clients',

            // Project Management
            'manage_projects' => 'Full project management capabilities',
            'view_projects' => 'View project information',
            'create_projects' => 'Create new projects',
            'edit_projects' => 'Edit existing projects',
            'delete_projects' => 'Delete projects',
            'approve_project_ui' => 'Approve UI/UX deliverables',
            'view_all_projects' => 'View every project without being assigned',
            'manage_all_projects' => 'Manage every project without being assigned',
            'manage_project_workflows' => 'Configure workflow stages for accessible projects',
            'manage_workflow_templates' => 'Create reusable project workflow templates',

            // Project Members
            'manage_project_members' => 'Full project member management',
            'view_project_members' => 'View project team members',
            'add_project_members' => 'Add members to projects',
            'remove_project_members' => 'Remove members from projects',

            // Project Files
            'manage_project_files' => 'Full project file management',
            'view_project_files' => 'View project files',
            'upload_project_files' => 'Upload files to projects',
            'delete_project_files' => 'Delete project files',

            // Project History
            'view_project_history' => 'View project change history',

            // Task Management
            'manage_tasks' => 'Full task management capabilities',
            'view_tasks' => 'View task information',
            'create_tasks' => 'Create new tasks',
            'edit_tasks' => 'Edit existing tasks',
            'delete_tasks' => 'Delete tasks',
            'view_internal_notes' => 'View internal task notes',
            'assign_tasks' => 'Assign tasks to team members',

            // Test Scenarios
            'manage_test_scenarios' => 'Full test scenario management',
            'view_test_scenarios' => 'View test scenarios',
            'create_test_scenarios' => 'Create test scenarios',
            'complete_test_scenarios' => 'Mark test scenarios as complete',
            'verify_test_scenarios' => 'Verify completed test scenarios',

            // Sales - Leads
            'manage_sales' => 'Full sales management capabilities',
            'view_leads' => 'View lead information',
            'create_leads' => 'Create new leads',
            'edit_leads' => 'Edit existing leads',
            'delete_leads' => 'Delete leads',

            // Sales - Quotations
            'view_quotations' => 'View quotation information',
            'create_quotations' => 'Create new quotations',
            'edit_quotations' => 'Edit existing quotations',
            'delete_quotations' => 'Delete quotations',
            'calculate_quotations' => 'Calculate quotation pricing',

            // Invoice Management
            'manage_invoices' => 'Full invoice management capabilities',
            'view_invoices' => 'View invoice information',
            'create_invoices' => 'Create new invoices',
            'edit_invoices' => 'Edit existing invoices',
            'delete_invoices' => 'Delete invoices',

            // Wiki Management
            'manage_wiki' => 'Full wiki management capabilities',
            'view_wiki' => 'View wiki content',
            'create_wiki' => 'Create new wiki entries',
            'edit_wiki' => 'Edit existing wiki entries',
            'delete_wiki' => 'Delete wiki entries',
            'view_encrypted_content' => 'View encrypted wiki content',

            // Time Tracking
            'manage_time_entries' => 'Full time entry management',
            'view_time_entries' => 'View own time entries',
            'start_timer' => 'Start time tracking',
            'stop_timer' => 'Stop time tracking',
            'view_all_time_entries' => 'View all users time entries',

            // Attendance
            'manage_attendance' => 'Full attendance management capabilities',
            'view_attendance' => 'View own attendance records',
            'view_all_attendance' => 'View all users attendance records',
            'clock_out' => 'Clock out from work',

            // Dashboard
            'view_dashboard' => 'Access dashboard',
            'view_dashboard_overview' => 'View dashboard overview statistics',

            // Reports & Analytics
            'view_reports' => 'View system reports',
            'view_profitability' => 'View profitability reports',
            'view_cost' => 'View cost information',
            'view_quality_metrics' => 'View quality metrics and bounce rates',
            'view_user_performance' => 'View user performance reports',
            'view_attendance_analytics' => 'View attendance analytics',

            // Admin Statistics
            'view_admin_stats' => 'View administrative statistics',

            // Financial Data
            'view_internal_costs' => 'View internal hourly costs',
            'manage_internal_costs' => 'Manage user internal costs',

            // Security & Audit
            'view_audit_logs' => 'View security audit logs',

            // Tags
            'manage_tags' => 'Full tag management',
            'view_tags' => 'View available tags',
        ];

        return $descriptions[$permissionName] ?? 'Permission for '.str_replace('_', ' ', $permissionName);
    }
}
