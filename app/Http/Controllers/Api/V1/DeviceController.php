<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceAccessEvent;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\Devices\DevicePollingService;
use App\Services\Devices\DeviceSyncService;
use App\Services\Devices\HikvisionIsapiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Administration of the access control terminals.
 *
 * Every route requires the `manage_devices` permission.
 */
class DeviceController extends Controller
{
    public function __construct(
        private readonly HikvisionIsapiService $isapi,
        private readonly DeviceSyncService $sync,
        private readonly DevicePollingService $polling,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * List every configured device.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $devices = Device::query()
            ->withCount(['deviceUsers', 'accessEvents'])
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => $this->present($device))
            ->all();

        return ApiResponse::success($devices, __('messages.devices_retrieved'));
    }

    /**
     * Show a single device with its mapped employees.
     */
    public function show(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $device->load([
            'deviceUsers' => fn ($query) => $query->with('user:id,name,email,avatar')->orderBy('device_employee_no'),
        ]);

        return ApiResponse::success($this->present($device), __('messages.device_retrieved'));
    }

    /**
     * Register a new terminal.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $data = $this->validated($request);

        $device = Device::query()->create([
            ...$data,
            'vendor' => $data['vendor'] ?? 'hikvision',
        ]);

        return ApiResponse::success($this->present($device), __('messages.device_created'), 201);
    }

    /**
     * Update a terminal.
     */
    public function update(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $data = $this->validated($request, true);

        $device->update($data);

        return ApiResponse::success($this->present($device->fresh()), __('messages.device_updated'));
    }

    /**
     * Remove a terminal and everything mapped to it.
     */
    public function destroy(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $device->delete();

        return ApiResponse::success(null, __('messages.device_deleted'));
    }

    /**
     * Check that the terminal is reachable and that the credentials work.
     */
    public function test(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        try {
            $result = $this->isapi->testConnection($device);
        } catch (RuntimeException $exception) {
            $device->update([
                'last_status' => 'error',
                'last_error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            return ApiResponse::error($exception->getMessage(), 502);
        }

        $device->update([
            'last_seen_at' => now(),
            'last_status' => 'ok',
            'last_error' => null,
        ]);

        return ApiResponse::success($result, __('messages.device_connection_ok'));
    }

    /**
     * Push every employee onto the terminal.
     */
    public function pushUsers(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        try {
            $result = $this->sync->pushAll($device);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        return ApiResponse::success($result, __('messages.device_users_pushed'));
    }

    /**
     * Read the people currently stored on the terminal.
     */
    public function pullUsers(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        try {
            $users = $this->sync->pullUsers($device);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        return ApiResponse::success(['users' => $users], __('messages.device_users_pulled'));
    }

    /**
     * Link an employee to the terminal and provision them on it.
     */
    public function linkUser(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $data = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        $user = User::query()->findOrFail($data['userId']);

        try {
            $mapping = $this->sync->pushUser($device, $user);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        return ApiResponse::success([
            'deviceUserId' => $mapping->id,
            'userId' => $mapping->user_id,
            'deviceEmployeeNo' => $mapping->device_employee_no,
            'fingerprintEnrolled' => $mapping->fingerprint_enrolled,
        ], __('messages.device_user_linked'), 201);
    }

    /**
     * Unlink an employee from the terminal.
     */
    public function unlinkUser(Request $request, Device $device, DeviceUser $deviceUser): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        abort_unless($deviceUser->device_id === $device->id, 404);

        $deviceUser->delete();

        return ApiResponse::success(null, __('messages.device_user_unlinked'));
    }

    /**
     * Trigger a poll of the terminal right now.
     */
    public function poll(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        try {
            $result = $this->polling->poll($device);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        return ApiResponse::success($result, __('messages.device_polled'));
    }

    /**
     * List the events recorded for a terminal.
     */
    public function events(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $perPage = min(100, max(1, (int) $request->integer('perPage', 25)));

        $events = DeviceAccessEvent::query()
            ->where('device_id', $device->id)
            ->with('user:id,name,email,avatar')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success([
            'events' => collect($events->items())->map(fn (DeviceAccessEvent $event) => [
                'eventId' => $event->id,
                'externalId' => $event->external_id,
                'userId' => $event->user_id,
                'deviceEmployeeNo' => $event->device_employee_no,
                'eventType' => $event->event_type,
                'verifyMode' => $event->verify_mode,
                'cardNo' => $event->card_no,
                'doorNo' => $event->door_no,
                'isAuthorized' => $event->is_authorized,
                'occurredAt' => $event->occurred_at->format('Y-m-d\TH:i:s'),
                'user' => $event->user ? [
                    'userId' => $event->user->id,
                    'name' => $event->user->name,
                    'avatar' => $event->user->avatar,
                ] : null,
            ])->all(),
            'total' => $events->total(),
            'currentPage' => $events->currentPage(),
            'lastPage' => $events->lastPage(),
        ], __('messages.device_events_retrieved'));
    }

    /**
     * Configure the terminal to push events to the ERP webhook.
     */
    public function enableWebhook(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        $url = rtrim((string) config('app.url'), '/')."/api/v1/device-events/{$device->webhook_token}";

        try {
            $this->isapi->configureWebhook($device, $url);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        $device->update(['meta' => [...($device->meta ?? []), 'webhook_url' => $url]]);

        return ApiResponse::success(['url' => $url], __('messages.device_webhook_enabled'));
    }

    /**
     * Remove the push configuration from the terminal.
     */
    public function disableWebhook(Request $request, Device $device): JsonResponse
    {
        if (! $this->canManage($request)) {
            return ApiResponse::forbidden(__('messages.unauthorized'));
        }

        try {
            $this->isapi->clearWebhook($device);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 502);
        }

        $meta = $device->meta ?? [];
        unset($meta['webhook_url']);
        $device->update(['meta' => $meta]);

        return ApiResponse::success(null, __('messages.device_webhook_disabled'));
    }

    /**
     * Validate and normalise the payload of a device.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => ($partial ? 'sometimes' : 'required').'|string|max:150',
            'vendor' => 'sometimes|string|max:50',
            'model' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:150',
            'ipAddress' => ($partial ? 'sometimes' : 'required').'|string|max:191',
            'port' => 'sometimes|integer|min:1|max:65535',
            'username' => 'nullable|string|max:191',
            'password' => 'nullable|string|max:191',
            'verifyTls' => 'sometimes|boolean',
            'isActive' => 'sometimes|boolean',
            'autoSync' => 'sometimes|boolean',
            'syncIntervalMinutes' => 'sometimes|integer|min:1|max:1440',
        ];

        $data = $request->validate($rules);

        $mapped = [
            'name' => $data['name'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'model' => $data['model'] ?? null,
            'location' => $data['location'] ?? null,
            'ip_address' => $data['ipAddress'] ?? null,
            'port' => $data['port'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
            'verify_tls' => $data['verifyTls'] ?? null,
            'is_active' => $data['isActive'] ?? null,
            'auto_sync' => $data['autoSync'] ?? null,
            'sync_interval_minutes' => $data['syncIntervalMinutes'] ?? null,
        ];

        return array_filter($mapped, fn ($value) => $value !== null);
    }

    /**
     * Shape a device for the API.
     *
     * @return array<string, mixed>
     */
    private function present(Device $device): array
    {
        return [
            'deviceId' => $device->id,
            'name' => $device->name,
            'vendor' => $device->vendor,
            'model' => $device->model,
            'location' => $device->location,
            'ipAddress' => $device->ip_address,
            'port' => $device->port,
            'username' => $device->username,
            'hasPassword' => filled($device->password),
            'verifyTls' => $device->verify_tls,
            'isActive' => $device->is_active,
            'autoSync' => $device->auto_sync,
            'syncIntervalMinutes' => $device->sync_interval_minutes,
            'webhookUrl' => $device->meta['webhook_url'] ?? null,
            'isOnline' => $device->isOnline(),
            'lastEventAt' => $device->last_event_at?->format('Y-m-d\TH:i:s'),
            'lastSeenAt' => $device->last_seen_at?->format('Y-m-d\TH:i:s'),
            'lastSyncedAt' => $device->last_synced_at?->format('Y-m-d\TH:i:s'),
            'lastStatus' => $device->last_status,
            'lastError' => $device->last_error,
            'linkedUsersCount' => $device->device_users_count ?? $device->deviceUsers()->count(),
            'deviceUsers' => $device->relationLoaded('deviceUsers')
                ? $device->deviceUsers
                    ->map(fn (DeviceUser $mapping): array => [
                        'deviceUserId' => $mapping->id,
                        'userId' => $mapping->user_id,
                        'deviceEmployeeNo' => $mapping->device_employee_no,
                        'cardNo' => $mapping->card_no,
                        'fingerprintEnrolled' => $mapping->fingerprint_enrolled,
                        'lastSyncedAt' => $mapping->last_synced_at?->format('Y-m-d\TH:i:s'),
                        'user' => $mapping->user ? [
                            'userId' => $mapping->user->id,
                            'name' => $mapping->user->name,
                            'email' => $mapping->user->email,
                            'avatar' => $mapping->user->avatar,
                        ] : null,
                    ])
                    ->values()
                    ->all()
                : null,
        ];
    }

    /**
     * Check whether the current user may manage the devices.
     */
    private function canManage(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $user->can('manage_devices');
    }
}
