<?php

namespace App\Console\Commands;

use App\Models\RecurringTaskConfig;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:generate-recurring {--date= : The date to generate tasks for (Y-m-d)} {--dry-run : Show what would be generated without creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring task instances based on configured schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date')) 
            : Carbon::today();

        $dryRun = $this->option('dry-run');

        $this->info("Checking recurring tasks for: {$date->toDateString()}");

        $configs = RecurringTaskConfig::active()
            ->with('templateTask')
            ->get();

        $generated = 0;
        $skipped = 0;

        foreach ($configs as $config) {
            if (!$config->templateTask) {
                $this->warn("Config {$config->id} has no template task. Skipping.");
                $skipped++;
                continue;
            }

            if ($config->shouldGenerateForDate($date)) {
                if ($dryRun) {
                    $this->line("Would generate task from template: {$config->templateTask->title}");
                    $generated++;
                } else {
                    try {
                        $task = $config->generateNextTask();
                        if ($task) {
                            $this->line("Generated task: {$task->title} (ID: {$task->id})");
                            Log::info("Generated recurring task", [
                                'task_id' => $task->id,
                                'config_id' => $config->id,
                                'template_id' => $config->template_task_id,
                            ]);
                            $generated++;
                        }
                    } catch (\Exception $e) {
                        $this->error("Failed to generate task for config {$config->id}: {$e->getMessage()}");
                        Log::error("Failed to generate recurring task", [
                            'config_id' => $config->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->info("Summary: {$generated} tasks generated, {$skipped} configs skipped.");

        return self::SUCCESS;
    }
}
