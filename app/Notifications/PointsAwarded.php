<?php

namespace App\Notifications;

use App\Models\PointTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PointsAwarded extends Notification
{
    use Queueable;

    public function __construct(
        public PointTransaction $transaction
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $isPositive = $this->transaction->points > 0;
        
        return [
            'type' => $isPositive ? 'points_awarded' : 'points_deducted',
            'transaction_id' => $this->transaction->id,
            'points' => abs($this->transaction->points),
            'transaction_type' => $this->transaction->type->value,
            'reason' => $this->transaction->reason,
            'new_total' => $notifiable->total_points,
            'message' => $isPositive
                ? __('You earned :points points: :reason', [
                    'points' => $this->transaction->points,
                    'reason' => $this->transaction->reason,
                ])
                : __(':points points deducted: :reason', [
                    'points' => abs($this->transaction->points),
                    'reason' => $this->transaction->reason,
                ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
