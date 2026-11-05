<?php

namespace App\Services\TimeTracking;

use App\Enums\TimerStopReason;
use App\Events\NotificationCreated;
use App\Jobs\SendWebPush;
use App\Models\TimeEntry;
use App\Models\HrPolicy;
use App\Models\User;
use App\Services\Sales\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AfterHoursConfirmation
{
    public function process(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $timer = TimeEntry::query()->whereKey($id)->lockForUpdate()->first();
            if (! $timer || $timer->end_time) return;
            $user = User::find($timer->user_id);
            if (! $user?->is_active || ! $user->work_start_time || ! $user->work_hours_per_day) return;

            $now = Carbon::now();
            if ($timer->after_hours_prompt_at) {
                if ($timer->after_hours_prompt_at->copy()->addMinutes($timer->after_hours_grace_minutes ?? $this->grace())->lte($now)) {
                    app(TimeTrackingService::class)->closeTimer($timer, TimerStopReason::AFTER_HOURS_TIMEOUT, $timer->after_hours_prompt_at->copy());
                }
                return;
            }

            $start = $timer->startedAt();
            $shiftStart = $start->copy()->startOfDay()->setTimeFromTimeString($user->work_start_time);
            // A timer begun after midnight can belong to an overnight shift.
            if ($start->lt($shiftStart) && $shiftStart->copy()->subDay()->addMinutes((int) round($user->work_hours_per_day * 60))->gt($start)) {
                $shiftStart->subDay();
            }
            $shiftEnd = $shiftStart->addMinutes((int) round($user->work_hours_per_day * 60));
            $due = $timer->after_hours_confirmed_at
                ? $timer->after_hours_confirmed_at->copy()->addMinutes($this->interval())
                : ($start->gt($shiftEnd) ? $start->copy()->addMinutes($this->interval()) : $shiftEnd);
            if ($due->gt($now)) return;

            $grace = $this->grace();
            $timer->update(['after_hours_prompt_at' => $now, 'after_hours_grace_minutes' => $grace]);
            $payload = [
                'type' => 'after_hours_confirmation', 'message' => 'Are you still working?',
                'body' => 'Your timer will pause if you do not respond within '.$grace.' minutes.',
                'url' => '/dashboard/my-tasks', 'timer_id' => $timer->id,
                'tag' => 'after-hours-'.$timer->id, 'promptAt' => $now->toIso8601String(),
            ];
            SendWebPush::dispatch($user->id, $payload, $timer->id)->afterCommit();
            DB::afterCommit(fn () => event(new NotificationCreated($user->id, [
                'id' => 'after-hours-'.$timer->id.'-'.$now->timestamp, 'data' => $payload,
            ])));
        });
    }

    public function confirm(User $user, int $id, string $promptAt, bool $stop = false): TimeEntry
    {
        return DB::transaction(function () use ($user, $id, $promptAt, $stop): TimeEntry {
            $timer = TimeEntry::query()->whereKey($id)->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $timer || $timer->end_time || ! $timer->after_hours_prompt_at
                || $timer->after_hours_prompt_at->timestamp !== Carbon::parse($promptAt)->timestamp
                || $timer->after_hours_prompt_at->copy()->addMinutes($timer->after_hours_grace_minutes ?? $this->grace())->lte(now())) {
                throw new \RuntimeException('This confirmation has expired or the timer has stopped.');
            }
            if ($stop) return app(TimeTrackingService::class)->closeTimer($timer, TimerStopReason::PAUSE, Carbon::now());
            $timer->update(['after_hours_prompt_at' => null, 'after_hours_grace_minutes' => null, 'after_hours_confirmed_at' => now()]);
            return $timer->fresh();
        });
    }

    private function policyValue(string $key, string $configKey, int $default): int
    {
        $policy = HrPolicy::query()->effectiveOn(today()->toDateString())->orderByDesc('effective_from')->orderByDesc('id')->first();
        return max(1, (int) ($policy?->settings[$key] ?? config($configKey, $default)));
    }

    private function interval(): int { return $this->policyValue('afterHoursIntervalMinutes', 'time_tracking.after_hours_interval_minutes', 60); }
    private function grace(): int { return $this->policyValue('afterHoursGraceMinutes', 'time_tracking.after_hours_grace_minutes', 10); }
}
