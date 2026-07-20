<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\GenerateTokenAction;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\RegisterUserAction;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Resources\LoginProfileResource;
use App\Modules\Settings\Services\UserPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Authentication', description: 'User authentication endpoints')]
class AuthController extends Controller
{
    public function __construct(
        private UserPermissionService $userPermissionService,
        private LoginUserAction $loginUserAction,
        private RegisterUserAction $registerUserAction,
        private GenerateTokenAction $generateTokenAction,
    ) {
        // Apply locale middleware to all methods
        $this->middleware('locale');

        // Apply auth middleware only to logout
        $this->middleware('auth:sanctum')->only(['logout', 'refresh', 'profile', 'updateProfile', 'updateAvatar']);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'User login',
        description: 'Authenticate user and return access token with profile information',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'tokenDetails',
                                    properties: [
                                        new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                                        new OA\Property(property: 'expiresIn', type: 'integer', example: 525600),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'profile', ref: '#/components/schemas/LoginProfileResource'),
                                new OA\Property(property: 'role', type: 'string', example: 'founder'),
                                new OA\Property(
                                    property: 'permissions',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'permissionName', type: 'string', example: 'view_users'),
                                            new OA\Property(property: 'access', type: 'boolean', example: true),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->loginUserAction->execute($request->validated());
        } catch (InvalidArgumentException) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $tokenDetails = $this->generateTokenAction->execute($user);

        return $this->withAuthCookies(ApiResponse::success([
            'tokenDetails' => $this->publicTokenDetails($tokenDetails),
            'profile' => new LoginProfileResource($user),
            'role' => $user->roles->first()->name ?? 'guest',
            'permissions' => $this->userPermissionService->getUserPermissions($user) ?? [],
        ], 'Login successful'), $request, $tokenDetails);
    }

    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'User registration',
        description: 'Register a new user account',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'passwordConfirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'passwordConfirmation', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'tokenDetails',
                                    properties: [
                                        new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                                        new OA\Property(property: 'expiresIn', type: 'integer', example: 525600),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'profile', ref: '#/components/schemas/LoginProfileResource'),
                                new OA\Property(property: 'role', type: 'string', example: 'user'),
                                new OA\Property(
                                    property: 'permissions',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'permissionName', type: 'string', example: 'view_profile'),
                                            new OA\Property(property: 'access', type: 'boolean', example: true),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $user = $this->registerUserAction->execute($data);

        $tokenDetails = $this->generateTokenAction->execute($user);

        return $this->withAuthCookies(ApiResponse::success([
            'tokenDetails' => $this->publicTokenDetails($tokenDetails),
            'profile' => new LoginProfileResource($user),
            'role' => $user->roles->first()->name ?? 'user',
            'permissions' => $this->userPermissionService->getUserPermissions($user) ?? [],
        ], 'Registration successful'), $request, $tokenDetails);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'User logout',
        description: 'Logout user and revoke access token',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logout successful'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ]
                )
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return $this->withoutAuthCookies(ApiResponse::success([], 'Logout successful'));
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $tokenDetails = $this->generateTokenAction->execute($user, 'refresh-token');

        if ($currentToken && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }

        return $this->withAuthCookies(ApiResponse::success([
            'tokenDetails' => $this->publicTokenDetails($tokenDetails),
            'profile' => new LoginProfileResource($user),
            'role' => $user->roles->first()->name ?? 'guest',
            'permissions' => $this->userPermissionService->getUserPermissions($user) ?? [],
        ], 'Token refreshed successfully'), $request, $tokenDetails);
    }

    #[OA\Get(
        path: '/api/v1/auth/profile',
        summary: 'Get user profile',
        description: 'Get authenticated user profile information',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'profile', ref: '#/components/schemas/LoginProfileResource'),
                                new OA\Property(property: 'role', type: 'string', example: 'founder'),
                                new OA\Property(
                                    property: 'permissions',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'permissionName', type: 'string', example: 'view_users'),
                                            new OA\Property(property: 'access', type: 'boolean', example: true),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ]
                )
            ),
        ]
    )]
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'profile' => new LoginProfileResource($user),
            'role' => $user->roles->first()->name ?? 'guest',
            'permissions' => $this->userPermissionService->getUserPermissions($user) ?? [],
        ], 'Profile retrieved successfully');
    }

    #[OA\Put(
        path: '/api/v1/auth/profile',
        summary: 'Update user profile',
        description: 'Update authenticated user profile information',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile updated successfully'),
                    ]
                )
            ),
        ]
    )]
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $user->update([
            'name' => $request->name,
        ]);

        return ApiResponse::success([
            'profile' => new LoginProfileResource($user),
        ], 'Profile updated successfully');
    }

    #[OA\Post(
        path: '/api/v1/auth/profile/avatar',
        summary: 'Update user avatar',
        description: 'Upload a new avatar image for the authenticated user',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Avatar updated successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'avatar', type: 'string', example: '/storage/avatars/user-1.jpg'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
            \Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar' => $path,
        ]);

        return ApiResponse::success([
            'avatar' => $user->avatar,
        ], 'Avatar updated successfully');
    }

    #[OA\Post(
        path: '/api/v1/auth/change-password',
        summary: 'Change user password',
        description: 'Change the authenticated user password. Requires current password verification.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'current_password123'),
                    new OA\Property(property: 'new_password', type: 'string', format: 'password', example: 'NewSecureP@ss123'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password', example: 'NewSecureP@ss123'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Password changed successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error or incorrect current password',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Current password is incorrect'),
                    ]
                )
            ),
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'new_password.confirmed' => 'Password confirmation does not match.',
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect', [], 422);
        }

        $tokenDetails = DB::transaction(function () use ($request, $user): array {
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            $user->tokens()->delete();

            return $this->generateTokenAction->execute($user, 'password-change');
        });

        return $this->withAuthCookies(
            ApiResponse::success([], 'Password changed successfully'),
            $request,
            $tokenDetails
        );
    }

    /**
     * @param  array{token: string, expiresIn: int}  $tokenDetails
     */
    private function withAuthCookies(JsonResponse $response, Request $request, array $tokenDetails): JsonResponse
    {
        $minutes = $tokenDetails['expiresIn'];
        $secure = app()->isProduction() || $request->isSecure();
        $csrfToken = Str::random(40);

        return $response
            ->withCookie(Cookie::make(
                name: 'auth_token',
                value: $tokenDetails['token'],
                minutes: $minutes,
                path: '/',
                domain: config('app.auth_cookie_domain'),
                secure: $secure,
                httpOnly: true,
                raw: false,
                sameSite: 'lax'
            ))
            ->withCookie(Cookie::make(
                name: 'csrf_token',
                value: $csrfToken,
                minutes: $minutes,
                path: '/',
                domain: config('app.auth_cookie_domain'),
                secure: $secure,
                httpOnly: false,
                raw: false,
                sameSite: 'lax'
            ));
    }

    private function withoutAuthCookies(JsonResponse $response): JsonResponse
    {
        return $response
            ->withCookie(Cookie::forget('auth_token', '/', config('app.auth_cookie_domain')))
            ->withCookie(Cookie::forget('csrf_token', '/', config('app.auth_cookie_domain')));
    }

    /**
     * @param  array{token: string, expiresIn: int}  $tokenDetails
     * @return array{expiresIn: int}
     */
    private function publicTokenDetails(array $tokenDetails): array
    {
        return [
            'expiresIn' => $tokenDetails['expiresIn'],
        ];
    }
}
