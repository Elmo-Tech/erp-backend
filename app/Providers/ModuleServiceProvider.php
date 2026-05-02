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
        $this->registerMorphAliases();

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

    private function registerMorphAliases(): void
    {
        Relation::morphMap([
            'App\Models\Project' => \App\Modules\Project\Models\Project::class,
            'App\Models\ProjectFile' => \App\Modules\Project\Models\ProjectFile::class,
            'App\Models\ProjectHistory' => \App\Modules\Project\Models\ProjectHistory::class,
            'App\Models\ProjectMember' => \App\Modules\Project\Models\ProjectMember::class,
            'App\Models\ProjectWiki' => \App\Modules\Project\Models\ProjectWiki::class,
            'App\Models\Client' => \App\Modules\Client\Models\Client::class,
            'App\Models\ClientActivity' => \App\Modules\Client\Models\ClientActivity::class,
            'App\Models\Meeting' => \App\Modules\Meetings\Models\Meeting::class,
            'App\Models\User' => \App\Modules\Settings\Models\User::class,
            // Lead pipeline
            'App\Models\Lead' => \App\Modules\Lead\Models\Lead::class,
            'App\Models\Proposal' => \App\Modules\Lead\Models\Proposal::class,
            'App\Models\Quotation' => \App\Modules\Lead\Models\Quotation::class,
            'App\Models\Contract' => \App\Modules\Lead\Models\Contract::class,
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
