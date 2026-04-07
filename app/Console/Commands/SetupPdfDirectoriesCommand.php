<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SetupPdfDirectoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pdf:setup-directories';

    /**
     * The console command description.
     */
    protected $description = 'Setup required directories for PDF generation with proper permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directories = [
            storage_path('app/fonts'),
            storage_path('fonts'),
            storage_path('app/public/pdfs'),
            storage_path('app/temp'),
        ];

        $this->info('Setting up PDF directories...');

        foreach ($directories as $directory) {
            try {
                if (! File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                    $this->info("✓ Created directory: {$directory}");
                } else {
                    $this->info("✓ Directory already exists: {$directory}");
                }

                // Ensure proper permissions
                chmod($directory, 0755);

                // Create .gitkeep file
                $gitkeepPath = $directory.'/.gitkeep';
                if (! File::exists($gitkeepPath)) {
                    File::put($gitkeepPath, '');
                }

            } catch (\Exception $e) {
                $this->error("✗ Failed to create directory {$directory}: ".$e->getMessage());
                Log::error('PDF directory creation failed', [
                    'directory' => $directory,
                    'error' => $e->getMessage(),
                ]);

                return 1;
            }
        }

        $this->info('PDF directories setup completed successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->info('1. Download Arabic fonts (Noto Sans Arabic)');
        $this->info('2. Run: php artisan pdf:install-fonts');
        $this->info('3. Test PDF generation with Arabic text');

        return 0;
    }
}
