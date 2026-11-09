<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Jobs\SendWebPush;
use App\Http\Controllers\Controller;
use App\Models\WebPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function settings(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'publicKey' => config('webpush.public_key'),
            'enabled' => (bool) (config('webpush.public_key') && config('webpush.private_key')),
            'reminderMinutes' => $request->user()->timer_reminder_minutes ?? 15,
            'intervals' => config('webpush.reminder_intervals'),
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        if (! config('webpush.public_key') || ! config('webpush.private_key')) {
            return ApiResponse::error('Desktop notifications are not configured on the server.', 503);
        }
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $endpointHash = hash('sha256', $data['endpoint']);
        if (! WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', $endpointHash)->exists()) {
            return ApiResponse::error('Enable desktop notifications on this browser first.', 422);
        }
        SendWebPush::dispatch($request->user()->id, [
            'message' => 'The Tables ERP',
            'body' => 'Desktop notifications are working.',
            'tag' => 'test-notification',
            'url' => '/dashboard',
        ], null, $endpointHash)->afterCommit();

        return ApiResponse::success(null, 'Test notification queued. Check your device notifications.');
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate(['reminderMinutes' => ['required', 'integer', Rule::in(config('webpush.reminder_intervals'))]]);
        $request->user()->forceFill(['timer_reminder_minutes' => $data['reminderMinutes']])->save();

        return ApiResponse::success(null, 'Reminder interval saved');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{80,100}={0,2}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{20,30}={0,2}$/'],
        ]);
        $host = strtolower((string) parse_url($data['endpoint'], PHP_URL_HOST));
        abort_unless(collect(config('webpush.allowed_hosts'))->contains(fn ($allowed) => $host === $allowed || str_ends_with($host, '.'.$allowed)), 422, 'Unsupported push service');
        WebPushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id, 'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
        ]);

        return ApiResponse::success(null, 'Desktop notifications enabled');
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);
        WebPushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return ApiResponse::success(null, 'Desktop notifications disabled on this browser');
    }
}
