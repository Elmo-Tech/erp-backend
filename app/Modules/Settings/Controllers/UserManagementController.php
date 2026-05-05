<?php

namespace App\Modules\Settings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Requests\StoreUserRequest;
use App\Modules\Settings\Requests\UpdateUserRequest;
use App\Modules\Settings\Resources\UserCollection;
use App\Modules\Settings\Resources\UserResource;
use App\Modules\Settings\Actions\UserService;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class UserManagementController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/users/manage",
     *     operationId="getUsers",
     *     tags={"User Management"},
     *     summary="Get paginated list of users",
     *     description="Retrieve all users with pagination, filtering, and sorting. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by name",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[role]",
     *         in="query",
     *         description="Filter by role name",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[isActive]",
     *         in="query",
     *         description="Filter by active status",
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field (name, email, created_at). Prefix with - for descending",
     *
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/UserCollection")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if (! auth()->user()->can('manage_users')) {
            return ApiResponse::forbidden(__('messages.no_permission'));
        }

        try {
            $users = $this->userService->getUsers([
                'perPage' => $request->input('perPage', 15),
            ]);

            return ApiResponse::success(
                new UserCollection($users),
                __('messages.users_retrieved')
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Return active internal users for CRM dropdowns.
     */
    public function internalUsers(): JsonResponse
    {
        $users = User::query()
            ->employees()
            ->where('is_active', true)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return ApiResponse::success([
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->first(),
            ])->values(),
        ], __('messages.users_retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/users/manage",
     *     operationId="createUser",
     *     tags={"User Management"},
     *     summary="Create a new user",
     *     description="Create a new user with optional role assignment. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="skills", type="array", @OA\Items(type="string"), example={"PHP", "Laravel"}),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"dev"}),
     *             @OA\Property(property="isActive", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $user = $this->userService->createUser($request->validated());

                return ApiResponse::success(
                    ['user' => new UserResource($user)],
                    __('messages.user_created'),
                    201
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/users/manage/{id}",
     *     operationId="getUser",
     *     tags={"User Management"},
     *     summary="Get a specific user",
     *     description="Retrieve detailed information about a specific user. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if (! auth()->user()->can('manage_users')) {
            return ApiResponse::forbidden(__('messages.no_permission'));
        }

        $user = $this->userService->getUserById($id);

        if (! $user) {
            return ApiResponse::notFound(__('messages.user_not_found'));
        }

        return ApiResponse::success(
            ['user' => new UserResource($user)],
            __('messages.user_retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/users/manage/{id}",
     *     operationId="updateUser",
     *     tags={"User Management"},
     *     summary="Update a user",
     *     description="Update user information. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="skills", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="isActive", type="boolean")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return ApiResponse::notFound(__('messages.user_not_found'));
        }

        try {
            return DB::transaction(function () use ($request, $user) {
                $user = $this->userService->updateUser($user, $request->validated());

                return ApiResponse::success(
                    ['user' => new UserResource($user)],
                    __('messages.user_updated')
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/users/manage/{id}",
     *     operationId="deleteUser",
     *     tags={"User Management"},
     *     summary="Delete a user",
     *     description="Delete a user. Cannot delete yourself. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Cannot delete yourself"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if (! auth()->user()->can('manage_users')) {
            return ApiResponse::forbidden(__('messages.no_permission'));
        }

        $user = $this->userService->getUserById($id);

        if (! $user) {
            return ApiResponse::notFound(__('messages.user_not_found'));
        }

        if ($user->id === auth()->id()) {
            return ApiResponse::error(__('messages.cannot_delete_self'), 400);
        }

        try {
            return DB::transaction(function () use ($user) {
                $this->userService->deleteUser($user);

                return ApiResponse::success(null, __('messages.user_deleted'));
            });
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/users/manage/{id}/toggle-status",
     *     operationId="toggleUserStatus",
     *     tags={"User Management"},
     *     summary="Toggle user active status",
     *     description="Activate or deactivate a user. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User status toggled successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function toggleStatus(int $id): JsonResponse
    {
        if (! auth()->user()->can('manage_users')) {
            return ApiResponse::forbidden(__('messages.no_permission'));
        }

        $user = $this->userService->getUserById($id);

        if (! $user) {
            return ApiResponse::notFound(__('messages.user_not_found'));
        }

        try {
            return DB::transaction(function () use ($user) {
                $user = $this->userService->toggleUserStatus($user);

                $message = $user->is_active
                    ? __('messages.user_activated')
                    : __('messages.user_deactivated');

                return ApiResponse::success(
                    ['user' => new UserResource($user)],
                    $message
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/users/manage/{id}/roles",
     *     operationId="assignUserRoles",
     *     tags={"User Management"},
     *     summary="Assign roles to user",
     *     description="Replace all user roles with the provided roles. Requires manage_users permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"roles"},
     *
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"dev", "techdev"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Roles assigned successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function assignRoles(Request $request, int $id): JsonResponse
    {
        if (! auth()->user()->can('manage_users')) {
            return ApiResponse::forbidden(__('messages.no_permission'));
        }

        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = $this->userService->getUserById($id);

        if (! $user) {
            return ApiResponse::notFound(__('messages.user_not_found'));
        }

        try {
            return DB::transaction(function () use ($request, $user) {
                $user = $this->userService->assignRoles($user, $request->input('roles'));

                return ApiResponse::success(
                    ['user' => new UserResource($user)],
                    __('messages.roles_assigned')
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
