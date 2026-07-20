<?php

namespace App\Providers;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientActivity;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\Shift;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use App\Modules\Project\Models\ProjectHistory;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Models\ProjectWiki;
use App\Modules\Settings\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

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
            $this->loadModuleCommands($modulePath);
        }
    }

    private function registerMorphAliases(): void
    {
        Relation::morphMap([
            'App\Models\Project' => Project::class,
            'App\Models\ProjectFile' => ProjectFile::class,
            'App\Models\ProjectHistory' => ProjectHistory::class,
            'App\Models\ProjectMember' => ProjectMember::class,
            'App\Models\ProjectWiki' => ProjectWiki::class,
            'App\Models\Client' => Client::class,
            'App\Models\ClientActivity' => ClientActivity::class,
            'App\Models\Meeting' => Meeting::class,
            'App\Models\User' => User::class,
            // Lead pipeline
            'App\Models\Lead' => Lead::class,
            'App\Models\Proposal' => Proposal::class,
            'App\Models\Quotation' => Quotation::class,
            'App\Models\Contract' => Contract::class,
            // HR
            'App\Models\Shift' => Shift::class,
            'App\Models\EmployeeProfile' => EmployeeProfile::class,
            'App\Models\Attendance' => Attendance::class,
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

    /**
     * Auto-discover and register Artisan commands from a module's Commands directory.
     *
     * Scans <ModulePath>/Commands/*.php and loads any class that extends
     * Illuminate\Console\Command. This mirrors the default auto-discovery
     * behaviour for app/Console/Commands but applied to each module.
     */
    private function loadModuleCommands(string $modulePath): void
    {
        $commandsPath = $modulePath.'/Commands';

        if (! is_dir($commandsPath)) {
            return;
        }

        // Resolve the PSR-4 namespace from the path relative to app_path().
        // e.g. app_path('Modules/HR/Commands') → App\Modules\HR\Commands
        $appPath = app_path();
        $namespace = 'App\\'.str_replace('/', '\\', ltrim(
            str_replace($appPath, '', $commandsPath),
            DIRECTORY_SEPARATOR
        ));

        $commands = [];

        foreach ((new Finder)->files()->name('*.php')->in($commandsPath) as $file) {
            $class = $namespace.'\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (
                class_exists($class)
                && is_subclass_of($class, Command::class)
                && ! (new \ReflectionClass($class))->isAbstract()
            ) {
                $commands[] = $class;
            }
        }

        if (! empty($commands)) {
            $this->commands($commands);
        }
    }
}
