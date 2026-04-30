<?php

namespace App\Modules\Client\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Requests\CreatePortalAccessRequest;
use App\Modules\Client\Resources\ClientResource;
use App\Modules\Settings\Models\User;
use App\Modules\Notification\Notifications\ClientPortalAccessCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Client Portal Access",
 *     description="Manage client portal access"
 * )
 */
class ClientPortalAccessController extends Controller
{
    public function __construct() {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware('permission:create_client_portal_access')->only(['create']);
        $this->middleware('permission:revoke_client_portal_access')->only(['revoke']);
        $this->middleware('permission:reset_client_portal_password')->only(['resetPassword']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clients/{client}/portal-access",
     *     summary="Create portal access for a client",
     *     tags={"Client Portal Access"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="password", type="string", description="Optional. If not provided, a random password will be generated"),
     *             @OA\Property(property="sendWelcomeEmail", type="boolean", default=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Portal access created"),
     *     @OA\Response(response=400, description="Client already has portal access"),
     *     @OA\Response(response=404, description="Client not found")
     * )
     */
    public function create(CreatePortalAccessRequest $request, Client $client): JsonResponse
    {
        if ($client->hasPortalAccess()) {
            return ApiResponse::error(__('Client already has portal access'), 400);
        }

        // Check if email is already used by another user
        $existingUser = User::where('email', $client->email)->first();
        if ($existingUser) {
            return ApiResponse::error(__('Email is already registered in the system'), 400);
        }

        $password = $request->input('password') ?? Str::random(12);
        $sendEmail = $request->input('sendWelcomeEmail', true);

        $user = DB::transaction(function () use ($client, $password) {
            $user = User::create([
                'name' => $client->display_name,
                'email' => $client->email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);
            $user->assignRole('client');
            $client->update(['user_id' => $user->id]);
            return $user;
        });

        // Send welcome email with password setup link (NOT the actual password)
        if ($sendEmail) {
            // Generate password reset link instead of sending password
            $user->notify(new ClientPortalAccessCreated($client, null));
        }

        // Security: Do NOT return password in API response
        return ApiResponse::success([
            'client' => new ClientResource($client->fresh()),
            'portalUrl' => config('app.frontend_url').'/client',
            'credentials' => [
                'email' => $client->email,
                'passwordSet' => $request->has('password'),
                'passwordGenerated' => ! $request->has('password'),
                // Password is NOT returned for security - user must use reset link or provided password
            ],
        ], __('Portal access created successfully'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/clients/{client}/portal-access",
     *     summary="Revoke portal access for a client",
     *     tags={"Client Portal Access"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(response=200, description="Portal access revoked"),
     *     @OA\Response(response=400, description="Client has no portal access"),
     *     @OA\Response(response=404, description="Client not found")
     * )
     */
    public function revoke(Client $client): JsonResponse
    {
        if (! $client->hasPortalAccess()) {
            return ApiResponse::error(__('Client has no portal access'), 400);
        }

        DB::transaction(function () use ($client) {
            $user = $client->user;

            // Revoke all tokens
            $user->tokens()->delete();

            // Deactivate user
            $user->update(['is_active' => false]);

            // Unlink from client
            $client->update(['user_id' => null]);
        });

        return ApiResponse::success(
            new ClientResource($client->fresh()),
            __('Portal access revoked successfully')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clients/{client}/portal-access/reset-password",
     *     summary="Reset portal password for a client",
     *     tags={"Client Portal Access"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="sendEmail", type="boolean", default=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Password reset successfully"),
     *     @OA\Response(response=400, description="Client has no portal access")
     * )
     */
    public function resetPassword(CreatePortalAccessRequest $request, Client $client): JsonResponse
    {
        if (! $client->hasPortalAccess()) {
            return ApiResponse::error(__('Client has no portal access'), 400);
        }

        $password = $request->input('password') ?? Str::random(12);
        $sendEmail = $request->input('sendEmail', true);

        $client->user->update(['password' => bcrypt($password)]);

        // Revoke existing tokens
        $client->user->tokens()->delete();

        if ($sendEmail) {
            $client->user->notify(new ClientPortalAccessCreated($client, $password));
        }

        return ApiResponse::success([
            'credentials' => [
                'email' => $client->email,
                'password' => $password,
                'passwordGenerated' => ! $request->has('password'),
            ],
        ], __('Password reset successfully'));
    }
}
