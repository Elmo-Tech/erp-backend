<?php

namespace App\Modules\Report\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PdfService
{
    /**
     * Load font configuration for Arabic support
     */
    private function loadFontConfig(): array
    {
        $configPath = storage_path('app/fonts/font_config.php');

        if (File::exists($configPath)) {
            return include $configPath;
        }

        // Default fallback configuration
        return [
            'arabic_fonts' => [
                'regular' => 'DejaVu Sans',
                'bold' => 'DejaVu Sans',
                'fallback' => 'DejaVu Sans',
            ],
            'font_stack' => [
                'ar' => "'DejaVu Sans', 'Arial Unicode MS', sans-serif",
                'en' => "'DejaVu Sans', 'Arial', sans-serif",
            ],
        ];
    }

    /**
     * Get default font based on locale and available fonts
     */
    private function getDefaultFont(array $fontConfig): string
    {
        $locale = app()->getLocale();

        if ($locale === 'ar') {
            // Check if Arabic fonts are available
            $arabicFont = $fontConfig['arabic_fonts']['regular'] ?? 'DejaVu Sans';
            $fontPath = storage_path('app/fonts');

            // Verify font files exist
            if (File::exists($fontPath.'/NotoSansArabic-Regular.ttf')) {
                return 'Noto Sans Arabic';
            }

            return 'DejaVu Sans'; // Fallback for Arabic
        }

        return 'DejaVu Sans'; // Default for other languages
    }

    /**
     * Generate PDF with robust error handling and directory management
     *
     * @param  string  $view  Blade view name
     * @param  array  $data  Data to pass to the view
     * @param  array  $options  PDF generation options
     *
     * @throws \Exception
     */
    public function generatePdf(string $view, array $data = [], array $options = []): \Barryvdh\DomPDF\PDF
    {
        // Ensure required directories exist
        $this->ensureDirectoriesExist();

        // Store current locale to restore later
        $currentLocale = app()->getLocale();

        // Set locale if provided in data
        if (isset($data['locale'])) {
            app()->setLocale($data['locale']);
            Log::info('PDF generation: Locale set to '.$data['locale']);
        }

        // Load font configuration
        $fontConfig = $this->loadFontConfig();

        // Default options for safe PDF generation with Arabic support
        $defaultOptions = [
            'defaultFont' => 'DejaVu Sans', // Always use DejaVu Sans - it has excellent Arabic support
            'isRemoteEnabled' => false, // Disable remote for security and reliability
            'isPhpEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'enableJavascript' => false,
            'enableFontSubsetting' => false, // CRITICAL: Must be disabled for Arabic characters
            'pdfBackend' => 'CPDF',
            'dpi' => 96,
            'fontHeightRatio' => 1.1,
            'enableHtml5Parser' => true,
            'defaultPaperSize' => 'A4',
            'defaultPaperOrientation' => 'portrait',
            'fontDir' => storage_path('app/fonts'),
            'fontCache' => storage_path('app/fonts'),
            'tempDir' => storage_path('app/temp'),
            'chroot' => storage_path('app'), // Restrict to storage directory
        ];

        // Merge with provided options (but prioritize critical Arabic settings)
        $pdfOptions = array_merge($defaultOptions, $options);

        // Force critical settings for Arabic support
        $pdfOptions['enableFontSubsetting'] = false; // Never enable subsetting for Arabic
        $pdfOptions['defaultFont'] = 'DejaVu Sans'; // Always use DejaVu Sans

        try {
            Log::info('PDF generation attempt', [
                'view' => $view,
                'locale' => app()->getLocale(),
                'options' => $pdfOptions,
            ]);

            // Generate PDF with Arabic-safe options
            $pdf = Pdf::loadView($view, $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions($pdfOptions);

            Log::info('PDF generation successful with Arabic-safe options');

            // Restore original locale
            app()->setLocale($currentLocale);

            return $pdf;

        } catch (\Exception $e) {
            Log::warning('PDF generation failed with provided options', [
                'error' => $e->getMessage(),
                'view' => $view,
                'options' => $pdfOptions,
                'locale' => app()->getLocale(),
            ]);

            // Fallback: Try with absolute minimal options
            try {
                Log::info('Attempting PDF generation with minimal fallback options');

                $pdf = Pdf::loadView($view, $data);
                $pdf->setPaper('A4', 'portrait');
                $pdf->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isRemoteEnabled' => false,
                    'enablePhp' => false,
                    'isHtml5ParserEnabled' => true,
                    'enableFontSubsetting' => false, // Critical: Never enable for Arabic
                    'fontDir' => storage_path('app/fonts'),
                    'fontCache' => storage_path('app/fonts'),
                ]);

                // Restore original locale
                app()->setLocale($currentLocale);

                Log::info('PDF generation succeeded with minimal fallback options', ['view' => $view]);

                return $pdf;

            } catch (\Exception $fallbackException) {
                // Restore original locale
                app()->setLocale($currentLocale);

                Log::error('PDF generation completely failed', [
                    'original_error' => $e->getMessage(),
                    'fallback_error' => $fallbackException->getMessage(),
                    'view' => $view,
                    'data_keys' => array_keys($data),
                    'locale' => $currentLocale,
                ]);

                throw new \Exception(
                    'PDF generation failed: '.$e->getMessage().
                    ' (Fallback also failed: '.$fallbackException->getMessage().')'
                );
            }
        }
    }

    /**
     * Generate and download PDF
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadPdf(string $view, array $data, string $filename, array $options = [])
    {
        $pdf = $this->generatePdf($view, $data, $options);

        return $pdf->download($filename);
    }

    /**
     * Generate and stream PDF (display in browser)
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function streamPdf(string $view, array $data, string $filename, array $options = [])
    {
        $pdf = $this->generatePdf($view, $data, $options);

        return $pdf->stream($filename);
    }

    /**
     * Ensure required directories exist for PDF generation
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/fonts'),
            storage_path('app/temp'),
            storage_path('fonts'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                try {
                    mkdir($directory, 0755, true);

                    // Create .gitkeep file
                    $gitkeepFile = $directory.'/.gitkeep';
                    if (! file_exists($gitkeepFile)) {
                        file_put_contents($gitkeepFile, '# Keep this directory in version control');
                    }

                    Log::info("Created PDF directory: {$directory}");
                } catch (\Exception $e) {
                    Log::warning("Failed to create PDF directory {$directory}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Test PDF generation capabilities
     *
     * @return array Test results
     */
    public function testPdfGeneration(): array
    {
        $results = [
            'directories' => [],
            'permissions' => [],
            'generation' => null,
        ];

        // Test directories
        $directories = [
            'app/fonts' => storage_path('app/fonts'),
            'fonts' => storage_path('fonts'),
            'framework/cache' => storage_path('framework/cache'),
        ];

        foreach ($directories as $name => $path) {
            $results['directories'][$name] = [
                'exists' => is_dir($path),
                'writable' => is_writable($path),
                'path' => $path,
            ];
        }

        // Test write permissions
        $testFile = storage_path('app/fonts/test_write.tmp');
        try {
            file_put_contents($testFile, 'test');
            $results['permissions']['write'] = true;
            unlink($testFile);
        } catch (\Exception $e) {
            $results['permissions']['write'] = false;
            $results['permissions']['error'] = $e->getMessage();
        }

        // Test PDF generation
        try {
            $testData = ['title' => 'Test PDF', 'content' => 'This is a test PDF generation.'];
            $pdf = $this->generatePdf('test.pdf-test', $testData);
            $results['generation'] = [
                'success' => true,
                'message' => 'PDF generation test successful',
            ];
        } catch (\Exception $e) {
            $results['generation'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }
}
