<?php

namespace Tests\Feature;

use App\Jobs\SendWebPush;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PushTestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_notification_only_targets_the_current_users_subscription(): void
    {
        Bus::fake();
        config()->set('webpush.public_key', 'public');
        config()->set('webpush.private_key', 'private');
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test';
        WebPushSubscription::create(['user_id' => $user->id, 'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => 'key', 'auth_token' => 'auth']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/test-push', ['endpoint' => $endpoint])->assertOk();
        Bus::assertDispatched(SendWebPush::class, fn ($job) => $job->userId === $user->id && $job->endpointHash === hash('sha256', $endpoint));
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/notifications/test-push', ['endpoint' => $endpoint])->assertUnprocessable();
    }

    public function test_test_notification_reports_missing_server_configuration(): void
    {
        config()->set('webpush.public_key', null);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/notifications/test-push', ['endpoint' => 'https://fcm.googleapis.com/test'])->assertStatus(503);
    }
}
