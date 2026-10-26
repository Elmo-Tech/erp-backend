<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Devices\DeviceEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint used by the terminal in push mode.
 *
 * The device authenticates itself with the per-device webhook token that is
 * part of the URL, so no Sanctum token is required. The route is rate
 * limited and every payload is validated before it reaches the pipeline.
 */
class DeviceWebhookController extends Controller
{
    public function __construct(
        private readonly DeviceEventService $events,
    ) {
        // No auth:sanctum here on purpose - the device is not a user.
        $this->middleware(['locale']);
    }

    /**
     * Receive a pushed access control event.
     */
    public function handle(Request $request, string $token): JsonResponse
    {
        $device = Device::query()
            ->where('webhook_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            Log::warning('Webhook received with an unknown device token', [
                'ip' => $request->ip(),
            ]);

            return ApiResponse::notFound(__('messages.device_not_found'));
        }

        $payload = $request->all();

        if ($payload === []) {
            return ApiResponse::error(__('messages.device_invalid_payload'), 422);
        }

        $event = $this->events->normalize($payload);

        if (! $event) {
            return ApiResponse::error(__('messages.device_invalid_payload'), 422);
        }

        $this->events->record($device, $event);

        $device->update([
            'last_seen_at' => now(),
            'last_status' => 'ok',
            'last_error' => null,
            'last_event_at' => $event['occurred_at'],
        ]);

        return ApiResponse::success(['accepted' => true], __('messages.device_event_accepted'));
    }
}
