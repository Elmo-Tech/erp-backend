<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskHelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskHelpRequest>
 */
class TaskHelpRequestFactory extends Factory
{
    protected $model = TaskHelpRequest::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'requested_by' => User::factory(),
            'message' => $this->faker->sentence(),
            'status' => TaskHelpRequest::STATUS_OPEN,
        ];
    }
}
