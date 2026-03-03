<?php

namespace App\Jobs;

use App\Models\RecurringTaskConfig;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRecurringTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = Carbon::today();
        $generatedCount = 0;
        $failedCount = 0;

        Log::info('Starting recurring tasks generation', [
            'date' => $today->toDateString(),
        ]);

        // Get all active recurring task configurations
        $configs = RecurringTaskConfig::active()->get();

        foreach ($configs as $config) {
            try {
                // Check if we should generate a task for today
                if ($config->shouldGenerateForDate($today)) {
                    // Check if task already exists for today
                    $existsToday = $config->generatedTasks()
                        ->whereDate('due_date', $today)
                        ->exists();

                    if (!$existsToday) {
                        $task = $config->generateNextTask();

                        if ($task) {
                            $generatedCount++;
                            Log::info('Generated recurring task', [
                                'config_id' => $config->id,
                                'task_id' => $task->id,
                                'task_title' => $task->title,
                                'due_date' => $task->due_date,
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Failed to generate recurring task', [
                    'config_id' => $config->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Completed recurring tasks generation', [
            'date' => $today->toDateString(),
            'total_configs' => $configs->count(),
            'generated' => $generatedCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Recurring tasks generation job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
