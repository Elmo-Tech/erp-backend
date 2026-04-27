<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerLegacyMorphAliases();

        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {
            $this->loadModuleRoutes($modulePath);
            $this->loadModuleMigrations($modulePath);
            $this->loadModuleViews($modulePath);
        }
    }

    private function registerLegacyMorphAliases(): void
    {
        Relation::morphMap([
            'App\Models\Invoice' => \App\Modules\Invoice\Models\Invoice::class,
            'App\Models\Project' => \App\Modules\Project\Models\Project::class,
            'App\Models\RecurringTaskConfig' => \App\Modules\Task\Models\RecurringTaskConfig::class,
            'App\Models\Task' => \App\Modules\Task\Models\Task::class,
            'App\Models\TaskActivity' => \App\Modules\Task\Models\TaskActivity::class,
            'App\Models\TaskAssignee' => \App\Modules\Task\Models\TaskAssignee::class,
            'App\Models\TaskChecklist' => \App\Modules\Task\Models\TaskChecklist::class,
            'App\Models\TaskChecklistItem' => \App\Modules\Task\Models\TaskChecklistItem::class,
            'App\Models\TaskDeliverable' => \App\Modules\Task\Models\TaskDeliverable::class,
            'App\Models\TaskDependency' => \App\Modules\Task\Models\TaskDependency::class,
            'App\Models\TaskLabel' => \App\Modules\Task\Models\TaskLabel::class,
            'App\Models\TaskTemplate' => \App\Modules\Task\Models\TaskTemplate::class,
            'App\Models\TaskTestScenario' => \App\Modules\Task\Models\TaskTestScenario::class,
            'App\Models\TaskTimeEstimate' => \App\Modules\Task\Models\TaskTimeEstimate::class,
        ]);
    }

    private function loadModuleRoutes(string $modulePath): void
    {
        $routeFile = $modulePath.'/Routes/api.php';

        if (! is_file($routeFile)) {
            return;
        }

        Route::prefix('api')
            ->middleware('api')
            ->group($routeFile);
    }

    private function loadModuleMigrations(string $modulePath): void
    {
        $migrationPath = $modulePath.'/Database/Migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
    }

    private function loadModuleViews(string $modulePath): void
    {
        $viewPath = $modulePath.'/Resources/views';

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, strtolower(basename($modulePath)));
        }
    }
}
