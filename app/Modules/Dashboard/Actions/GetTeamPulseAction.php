<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Settings\Models\User;
use App\Modules\SOP\Models\DailyCheckin;

class GetTeamPulseAction
{
    public function execute(): array
    {
        return DailyCheckin::query()
            ->with(['user:id,name,email,avatar,total_points', 'directive:id,title'])
            ->whereDate('submitted_at', today())
            ->latest('submitted_at')
            ->get()
            ->map(fn (DailyCheckin $checkin): array => [
                'id' => $checkin->id,
                'submitted_at' => $checkin->submitted_at?->toISOString(),
                'focus_items' => array_values($checkin->focus_items ?? []),
                'user' => $checkin->user ? $this->formatUser($checkin->user) : null,
                'directive' => $checkin->directive ? [
                    'id' => $checkin->directive->id,
                    'title' => $checkin->directive->title,
                ] : null,
            ])
            ->all();
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar ?: null,
            'points' => (int) ($user->total_points ?? 0),
        ];
    }
}
