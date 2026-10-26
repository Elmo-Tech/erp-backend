<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/** A cache-invalidation signal, never employee/session/project payloads. */
class WorkActivityChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public string $changeId;

    public function __construct(public string $reason)
    {
        $this->changeId = (string) Str::uuid();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('work-monitoring')];
    }

    public function broadcastAs(): string
    {
        return 'work.activity.changed';
    }

    public function broadcastWith(): array
    {
        return ['changeId' => $this->changeId, 'reason' => $this->reason];
    }
}
