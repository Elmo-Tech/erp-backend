<?php

namespace App\Console\Commands;

use App\Services\Reporting\PdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class TestArabicPdfCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pdf:test-arabic {--save : Save the generated PDF to storage}';

    /**
     * The console command description.
     */
    protected $description = 'Test Arabic text rendering in PDF generation';

    /**
     * Execute the console command.
     */
    public function handle(PdfService $pdfService): int
    {
        $this->info('Testing Arabic PDF generation...');

        // Test data with Arabic text
        $testData = [
            'title' => 'تقرير الأداء الوظيفي',
            'employee' => [
                'name' => 'أحمد محمد علي',
                'email' => 'ahmed@example.com',
                'specialization' => 'frontend_developer', // Use string instead of enum object
                'totalPoints' => 150
            ],
            'period' => [
                'dateFrom' => '2024-01-01',
                'dateTo' => '2024-12-31'
            ],
            'overview' => [
                'totalTrackedHours' => 160.5,
                'totalIdleHours' => 12.3,
                'taskCompletionRate' => 95.2,
                'averageBounceRate' => 0.15,
                'attendanceDays' => 22,
                'productivityScore' => 8.7,
                'pointsEarned' => 180,
                'pointsDeducted' => 30,
                'netPointsChange' => 150
            ],
            'tasks' => [
                'total' => 45,
                'completed' => 42,
                'inProgress' => 3,
                'totalBounces' => 2,
                'tasksWithBounces' => [] // Add empty array to prevent errors
            ],
            'attendance' => [
                'totalDays' => 22,
                'lateDays' => 2,
                'totalLateMinutes' => 15,
                'averageIdlePerDay' => 25.5
            ],
            'points' => [
                'transactions' => [
                    [
                        'createdAt' => '2024-01-15',
                        'points' => 50,
                        'type' => 'task_completion',
                        'reason' => 'إنجاز مهمة بتقدير ممتاز',
                        'createdBy' => 'مدير المشروع'
                    ],
                    [
                        'createdAt' => '2024-01-10',
                        'points' => -10,
                        'type' => 'late_attendance',
                        'reason' => 'تأخير في الحضور',
                        'createdBy' => 'النظام'
                    ]
                ]
            ],
            // Add missing optional sections to prevent template errors
            'gracePeriod' => null,
            'deductions' => [
                'totalDeductions' => 0,
                'dailyDeductions' => []
            ],
            'locale' => 'ar'
        ];

        // Test both Arabic and English
        $locales = ['ar', 'en'];
        $results = [];

        foreach ($locales as $locale) {
            $this->info("Testing locale: {$locale}");
            
            App::setLocale($locale);
            $testData['locale'] = $locale;

            try {
                $pdf = $pdfService->generatePdf('reports.employee-review', $testData);
                
                if ($this->option('save')) {
                    $filename = "test-arabic-pdf-{$locale}-" . date('Y-m-d-H-i-s') . '.pdf';
                    $filepath = storage_path("app/public/{$filename}");
                    
                    File::put($filepath, $pdf->output());
                    $this->info("✓ PDF saved: {$filepath}");
                }

                $results[$locale] = [
                    'success' => true,
                    'message' => 'PDF generated successfully'
                ];

                $this->info("✓ {$locale} PDF generation: SUCCESS");

            } catch (\Exception $e) {
                $results[$locale] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                $this->error("✗ {$locale} PDF generation: FAILED");
                $this->error("Error: " . $e->getMessage());
            }
        }

        // Display summary
        $this->newLine();
        $this->info('=== Test Results Summary ===');
        
        foreach ($results as $locale => $result) {
            $status = $result['success'] ? '✓ PASS' : '✗ FAIL';
            $this->line("{$locale}: {$status}");
            
            if (!$result['success']) {
                $this->error("  Error: " . $result['error']);
            }
        }

        // Check font availability
        $this->newLine();
        $this->info('=== Font Availability Check ===');
        
        $fontDir = storage_path('app/fonts');
        $arabicFonts = [
            'NotoSansArabic-Regular.ttf',
            'NotoSansArabic-Bold.ttf'
        ];

        foreach ($arabicFonts as $font) {
            $fontPath = $fontDir . '/' . $font;
            $exists = File::exists($fontPath);
            $status = $exists ? '✓ Available' : '✗ Missing';
            $this->line("{$font}: {$status}");
            
            if (!$exists) {
                $this->warn("  Run: php artisan pdf:install-fonts --download");
            }
        }

        // Overall result
        $allPassed = collect($results)->every(fn($result) => $result['success']);
        
        if ($allPassed) {
            $this->info('🎉 All tests passed! Arabic PDF generation is working correctly.');
            return 0;
        } else {
            $this->error('❌ Some tests failed. Please check the errors above.');
            return 1;
        }
    }
}