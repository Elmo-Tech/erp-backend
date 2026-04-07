<?php

namespace App\Events;

use App\Http\Resources\V1\TimeEntries\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimerUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action; // 'started', 'stopped'

    public ?array $timeEntry;

    public int $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, string $action, ?TimeEntry $timeEntry = null)
    {
        $this->userId = $userId;
        $this->action = $action;
        $this->timeEntry = $timeEntry
            ? (new TimeEntryResource($timeEntry->load('trackable')))->resolve()
            : null;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'timer.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'timeEntry' => $this->timeEntry,
        ];
    }
}
