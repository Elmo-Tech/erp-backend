<?php

namespace App\Services\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfService
{
    /**
     * Generate PDF with robust error handling and directory management
     *
     * @param string $view Blade view name
     * @param array $data Data to pass to the view
     * @param array $options PDF generation options
     * @return \Barryvdh\DomPDF\PDF
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
            Log::info('PDF generation: Locale set to ' . $data['locale']);
        }
        
        // Default options for safe PDF generation with Arabic support
        $defaultOptions = [
            'defaultFont' => app()->getLocale() == 'ar' ? 'DejaVu Sans' : 'serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true, // Enable remote for Google Fonts
            'enablePhp' => false,
            'enableJavascript' => false,
            'enableFontSubsetting' => true, // Enable font subsetting for Arabic
            'pdfBackend' => 'CPDF',
            'fontHeightRatio' => 1.1,
            'enableHtml5Parser' => true,
            'dpi' => 96,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'portrait',
            'chroot' => public_path(), // Allow access to public directory for fonts
        ];

        // Merge with provided options
        $pdfOptions = array_merge($defaultOptions, $options);

        try {
            Log::info('PDF generation attempt', [
                'view' => $view,
                'locale' => app()->getLocale(),
                'options' => $pdfOptions
            ]);
            
            // First attempt: Try with provided options
            $pdf = Pdf::loadView($view, $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions($pdfOptions);

            Log::info('PDF generation successful with primary options');
            
            // Restore original locale
            app()->setLocale($currentLocale);
            
            return $pdf;

        } catch (\Exception $e) {
            Log::warning('PDF generation failed with provided options', [
                'error' => $e->getMessage(),
                'view' => $view,
                'options' => $pdfOptions,
                'locale' => app()->getLocale()
            ]);

            // Fallback: Try with minimal options and basic font
            try {
                $pdf = Pdf::loadView($view, $data);
                $pdf->setPaper('A4', 'portrait');
                $pdf->setOptions([
                    'defaultFont' => 'DejaVu Sans', // Use DejaVu Sans for better Unicode support
                    'isRemoteEnabled' => false,
                    'enablePhp' => false,
                    'isHtml5ParserEnabled' => true,
                    'enableFontSubsetting' => true,
                ]);

                // Restore original locale
                app()->setLocale($currentLocale);
                
                Log::info('PDF generation succeeded with fallback options', ['view' => $view]);
                return $pdf;

            } catch (\Exception $fallbackException) {
                // Restore original locale
                app()->setLocale($currentLocale);
                
                Log::error('PDF generation completely failed', [
                    'original_error' => $e->getMessage(),
                    'fallback_error' => $fallbackException->getMessage(),
                    'view' => $view,
                    'data_keys' => array_keys($data),
                    'locale' => $currentLocale
                ]);

                throw new \Exception(
                    'PDF generation failed: ' . $e->getMessage() . 
                    ' (Fallback also failed: ' . $fallbackException->getMessage() . ')'
                );
            }
        }
    }

    /**
     * Generate and download PDF
     *
     * @param string $view
     * @param array $data
     * @param string $filename
     * @param array $options
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
     * @param string $view
     * @param array $data
     * @param string $filename
     * @param array $options
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function streamPdf(string $view, array $data, string $filename, array $options = [])
    {
        $pdf = $this->generatePdf($view, $data, $options);
        return $pdf->stream($filename);
    }

    /**
     * Ensure required directories exist for PDF generation
     *
     * @return void
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/fonts'),
            storage_path('fonts'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                try {
                    mkdir($directory, 0755, true);
                    
                    // Create .gitkeep file
                    $gitkeepFile = $directory . '/.gitkeep';
                    if (!file_exists($gitkeepFile)) {
                        file_put_contents($gitkeepFile, '# Keep this directory in version control');
                    }
                    
                    Log::info("Created PDF directory: {$directory}");
                } catch (\Exception $e) {
                    Log::warning("Failed to create PDF directory {$directory}: " . $e->getMessage());
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
                'message' => 'PDF generation test successful'
            ];
        } catch (\Exception $e) {
            $results['generation'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        return $results;
    }
}