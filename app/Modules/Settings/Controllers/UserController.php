<?php

namespace App\Modules\Settings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\UserService;
use App\Modules\Settings\Models\User;
use App\Modules\Settings\Requests\StoreUserRequest;
use App\Modules\Settings\Requests\UpdateUserRequest;
use App\Modules\Settings\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UserController — canonical CRUD for the Users domain.
 *
 * All business logic (hashing, role sync, audit logging) lives in UserService.
 * This controller is the HTTP adapter only: authorize → delegate → respond.
 *
 * Permission guard: manage_users (PBAC — never role-based checks).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /v1/users
     * Paginated list of all users.
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_users'), 403, 'Unauthorized');

        $users = $this->userService->getUsers([
            'perPage' => $request->integer('perPage', 15),
        ]);

        return ApiResponse::success(
            UserResource::collection($users)->response()->getData(true),
            'Users retrieved successfully'
        );
    }

    /**
     * POST /v1/users
     * Create a new user and optionally assign Spatie roles.
     *
     * The StoreUserRequest validates the `roles` array; UserService calls
     * $user->syncRoles($data['roles']) after creation.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_users'), 403, 'Unauthorized');

        return DB::transaction(function () use ($request): JsonResponse {
            $user = $this->userService->createUser($request->validated());

            return ApiResponse::success(
                ['user' => new UserResource($user)],
                'User created successfully',
                201
            );
        });
    }

    /**
     * GET /v1/users/{user}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        abort_if(! $request->user()->can('manage_users'), 403, 'Unauthorized');

        $user = $this->userService->getUserById($id);

        if ($user === null) {
            return ApiResponse::notFound('User not found');
        }

        return ApiResponse::success(
            ['user' => new UserResource($user)],
            'User retrieved successfully'
        );
    }

    /**
     * PUT /v1/users/{user}
     * Update user fields and sync Spatie roles when `roles` is present.
     *
     * UserService calls $user->syncRoles($data['roles']) when the key exists,
     * so passing an empty array will strip all roles.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        abort_if(! $request->user()->can('manage_users'), 403, 'Unauthorized');

        $user = $this->userService->getUserById($id);

        if ($user === null) {
            return ApiResponse::notFound('User not found');
        }

        return DB::transaction(function () use ($request, $user): JsonResponse {
            $updated = $this->userService->updateUser($user, $request->validated());

            return ApiResponse::success(
                ['user' => new UserResource($updated)],
                'User updated successfully'
            );
        });
    }

    /**
     * DELETE /v1/users/{user}
     * Soft-deletes the user. Cannot delete yourself.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_if(! $request->user()->can('manage_users'), 403, 'Unauthorized');

        $user = $this->userService->getUserById($id);

        if ($user === null) {
            return ApiResponse::notFound('User not found');
        }

        if ($user->id === $request->user()->id) {
            return ApiResponse::error('You cannot delete your own account', 400);
        }

        return DB::transaction(function () use ($user): JsonResponse {
            $this->userService->deleteUser($user);

            return ApiResponse::success(null, 'User deleted successfully');
        });
    }
}
