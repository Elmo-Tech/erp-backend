<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public ?string $endpointHash = null;

    public function __construct(public int $userId, public array $payload, public ?int $timerId = null, ?string $endpointHash = null)
    {
        $this->endpointHash = $endpointHash;
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        if (! config('webpush.private_key') || ! User::whereKey($this->userId)->where('is_active', true)->exists()) {
            if ($this->endpointHash) throw new \RuntimeException('Push delivery is unavailable.');
            return;
        }
        if ($this->timerId && ! TimeEntry::whereKey($this->timerId)->where('user_id', $this->userId)->whereNull('end_time')->exists()) {
            return;
        }
        if (isset($this->payload['project_id']) && ! Project::withoutGlobalScopes()->whereKey($this->payload['project_id'])->whereNull('deleted_at')->exists()) {
            return;
        }
        $push = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'), 'publicKey' => config('webpush.public_key'), 'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => $this->timerId ? 300 : 3600], 15);
        $retry = false;
        $found = false;
        foreach (WebPushSubscription::where('user_id', $this->userId)->when($this->endpointHash, fn ($query) => $query->where('endpoint_hash', $this->endpointHash))->get() as $record) {
            $found = true;
            $report = $push->sendOneNotification(Subscription::create([
                'endpoint' => $record->endpoint, 'publicKey' => $record->public_key,
                'authToken' => $record->auth_token, 'contentEncoding' => 'aes128gcm',
            ]), json_encode($this->payload, JSON_THROW_ON_ERROR));
            if ($report->isSubscriptionExpired()) {
                $record->delete();
                if ($this->endpointHash) throw new \RuntimeException('The browser subscription expired. Enable notifications again.');
            } elseif (! $report->isSuccess()) {
                $retry = true;
            }
        }
        if ($this->endpointHash && ! $found) throw new \RuntimeException('The browser subscription is unavailable. Enable notifications again.');
        if ($retry) {
            throw new \RuntimeException('Push delivery temporarily failed.');
        }
    }
}
