<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskSupervisionSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskSupervisionSession>
 */
class TaskSupervisionSessionFactory extends Factory
{
    protected $model = TaskSupervisionSession::class;

    public function definition(): array
    {
        $startedAt = now()->subMinutes(30);

        return [
            'task_id' => Task::factory(),
            'supervisor_id' => User::factory(),
            'session_id' => (string) Str::uuid(),
            'started_at' => $startedAt,
            'last_heartbeat_at' => $startedAt,
        ];
    }
}
