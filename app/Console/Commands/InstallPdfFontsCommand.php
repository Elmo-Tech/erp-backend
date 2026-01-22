<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstallPdfFontsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pdf:install-fonts {--download : Download fonts from Google Fonts}';

    /**
     * The console command description.
     */
    protected $description = 'Install Arabic fonts for PDF generation';

    /**
     * Font URLs for download
     */
    protected array $fontUrls = [
        'NotoSansArabic-Regular.ttf' => 'https://fonts.gstatic.com/s/notosansarabic/v18/nwpxtLGrOAZMl5nJ_wfgRg3DrWFZWsnVBJ_sS6tlqHHFlhQ5l3sQWIHPqzCfyGyvu3CBFQLaig.ttf',
        'NotoSansArabic-Bold.ttf' => 'https://fonts.gstatic.com/s/notosansarabic/v18/nwpxtLGrOAZMl5nJ_wfgRg3DrWFZWsnVBJ_sS6tlqHHFlhQ5l3sQWIHPqzCfyGyvuyhBFQLaig.ttf',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fontsDir = storage_path('app/fonts');
        
        $this->info('Installing Arabic fonts for PDF generation...');

        // Ensure fonts directory exists
        if (!File::exists($fontsDir)) {
            File::makeDirectory($fontsDir, 0755, true);
        }

        // Download fonts if requested
        if ($this->option('download')) {
            $this->downloadFonts($fontsDir);
        }

        // Install existing fonts
        $this->installFonts($fontsDir);

        $this->info('Font installation completed!');
        return 0;
    }

    /**
     * Download fonts from Google Fonts
     */
    protected function downloadFonts(string $fontsDir): void
    {
        $this->info('Downloading Arabic fonts...');

        foreach ($this->fontUrls as $filename => $url) {
            $fontPath = $fontsDir . '/' . $filename;
            
            if (File::exists($fontPath)) {
                $this->info("✓ Font already exists: {$filename}");
                continue;
            }

            try {
                $this->info("Downloading {$filename}...");
                
                $response = Http::timeout(60)->get($url);
                
                if ($response->successful()) {
                    File::put($fontPath, $response->body());
                    $this->info("✓ Downloaded: {$filename}");
                } else {
                    $this->error("✗ Failed to download {$filename}: HTTP {$response->status()}");
                }
                
            } catch (\Exception $e) {
                $this->error("✗ Error downloading {$filename}: " . $e->getMessage());
                Log::error("Font download failed", [
                    'font' => $filename,
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Install fonts using dompdf font loader
     */
    protected function installFonts(string $fontsDir): void
    {
        $this->info('Installing fonts for dompdf...');

        $fontFiles = [
            'NotoSansArabic-Regular.ttf' => 'Noto Sans Arabic',
            'NotoSansArabic-Bold.ttf' => 'Noto Sans Arabic Bold',
        ];

        foreach ($fontFiles as $filename => $fontName) {
            $fontPath = $fontsDir . '/' . $filename;
            
            if (!File::exists($fontPath)) {
                $this->warn("Font file not found: {$fontPath}");
                $this->info("Run with --download option to download fonts automatically");
                continue;
            }

            try {
                // Load font using dompdf's font loader
                $this->info("Installing font: {$fontName}");
                
                // Create font metrics
                $this->call('dompdf:install-font', [
                    'font_file' => $fontPath,
                    'font_name' => $fontName,
                ]);
                
                $this->info("✓ Installed: {$fontName}");
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to install {$fontName}: " . $e->getMessage());
                Log::error("Font installation failed", [
                    'font' => $fontName,
                    'path' => $fontPath,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Create font configuration file
        $this->createFontConfig($fontsDir);
    }

    /**
     * Create font configuration file
     */
    protected function createFontConfig(string $fontsDir): void
    {
        $configContent = <<<'PHP'
<?php
/**
 * Font configuration for dompdf Arabic support
 * Generated automatically by pdf:install-fonts command
 */

return [
    'arabic_fonts' => [
        'regular' => 'Noto Sans Arabic',
        'bold' => 'Noto Sans Arabic Bold',
        'fallback' => 'DejaVu Sans',
    ],
    
    'font_stack' => [
        'ar' => "'Noto Sans Arabic', 'DejaVu Sans', 'Arial Unicode MS', sans-serif",
        'en' => "'DejaVu Sans', 'Arial', sans-serif",
    ],
];
PHP;

        $configPath = $fontsDir . '/font_config.php';
        File::put($configPath, $configContent);
        
        $this->info("✓ Created font configuration: {$configPath}");
    }
}