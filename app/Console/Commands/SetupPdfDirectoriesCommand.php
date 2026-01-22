<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupPdfDirectoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pdf:setup-directories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create required directories for PDF generation with proper permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Setting up PDF directories...');

        $directories = [
            storage_path('app/fonts'),
            storage_path('fonts'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('app/public'),
        ];

        $created = 0;
        $existing = 0;
        $failed = 0;

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $this->line("✓ Directory already exists: {$directory}");
                $existing++;
                continue;
            }

            try {
                if (mkdir($directory, 0755, true)) {
                    $this->info("✓ Created directory: {$directory}");
                    
                    // Create .gitkeep file
                    $gitkeepFile = $directory . '/.gitkeep';
                    if (!file_exists($gitkeepFile)) {
                        file_put_contents($gitkeepFile, '# Keep this directory in version control');
                        $this->line("  → Added .gitkeep file");
                    }
                    
                    $created++;
                } else {
                    $this->error("✗ Failed to create directory: {$directory}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error creating directory {$directory}: " . $e->getMessage());
                $failed++;
            }
        }

        // Summary
        $this->newLine();
        $this->info("PDF Directory Setup Summary:");
        $this->line("• Created: {$created} directories");
        $this->line("• Already existed: {$existing} directories");
        
        if ($failed > 0) {
            $this->line("• Failed: {$failed} directories");
            $this->error("Some directories could not be created. Check permissions.");
            return self::FAILURE;
        }

        $this->info("✅ All PDF directories are ready!");
        
        // Test directory permissions
        $this->newLine();
        $this->info("Testing directory permissions...");
        
        $testFile = storage_path('app/fonts/test_write.tmp');
        try {
            file_put_contents($testFile, 'test');
            unlink($testFile);
            $this->info("✓ Write permissions verified");
        } catch (\Exception $e) {
            $this->error("✗ Write permission test failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}