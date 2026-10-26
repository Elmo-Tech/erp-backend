<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Devices\DeviceEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceBridgeController extends Controller
{
    public function __invoke(Request $request, int $id, DeviceEventService $events): JsonResponse
    {
        $device = Device::whereKey($id)->where('is_active', true)->first();
        $expected = $device?->meta['bridge_token_hash'] ?? null;
        abort_unless($expected && $request->bearerToken() && hash_equals($expected, hash('sha256', $request->bearerToken())), 401);
        abort_if(strlen($request->getContent()) > 256 * 1024, 413);
        $data = $request->validate(['events' => ['present', 'array', 'max:100'], 'events.*' => ['required', 'array']]);
        $normalized = [];
        foreach ($data['events'] as $index => $payload) {
            $event = $events->normalize($payload);
            abort_unless($event, 422, 'Invalid event at batch position '.$index.'; the batch was not imported.');
            $normalized[] = $event;
        }
        $active = ($device->meta['bridge_mode'] ?? 'shadow') === 'attendance';
        DB::transaction(function () use ($device, $normalized, $active, $events): void {
            Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($active) {
                usort($normalized, fn ($a, $b) => $a['occurred_at']->timestamp <=> $b['occurred_at']->timestamp);
                foreach ($normalized as $event) {
                    $events->record($device, $event);
                }
            }
            $device->update(['last_seen_at' => now(), 'last_status' => $active ? 'ok' : 'shadow', 'last_error' => null]);
        });

        return ApiResponse::success(['applied' => $active, 'validatedEvents' => count($normalized), 'mode' => $active ? 'attendance' : 'shadow']);
    }
}
