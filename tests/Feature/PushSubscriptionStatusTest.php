<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_checks_the_current_account_and_browser_subscription(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/example';
        WebPushSubscription::create(['user_id' => $owner->id, 'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => 'key', 'auth_token' => 'auth']);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/notifications/push-subscriptions/status', ['endpoint' => $endpoint])->assertOk()->assertJsonPath('data.registered', true);
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/notifications/push-subscriptions/status', ['endpoint' => $endpoint])->assertOk()->assertJsonPath('data.registered', false);
        $this->postJson('/api/v1/notifications/push-subscriptions/status', [])->assertUnprocessable();
    }
}
