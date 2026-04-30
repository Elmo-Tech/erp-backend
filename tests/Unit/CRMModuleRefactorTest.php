<?php

namespace Tests\Unit;

use App\Modules\Proposal\Models\Proposal;
use App\Modules\Quotation\Models\Quotation;
use Tests\TestCase;

class CRMModuleRefactorTest extends TestCase
{
    public function test_core_crm_models_exist(): void
    {
        $this->assertTrue(class_exists(\App\Modules\CRM\Models\Client::class));
        $this->assertTrue(class_exists(\App\Modules\CRM\Models\Contact::class));
    }

    public function test_downstream_models_use_client_relationships(): void
    {
        $proposal = new Proposal;
        $quotation = new Quotation;

        $this->assertContains('client_id', $proposal->getFillable());
        $this->assertNotContains('lead_id', $proposal->getFillable());
        $this->assertTrue(method_exists($proposal, 'client'));
        $this->assertFalse(method_exists($proposal, 'lead'));

        $this->assertContains('client_id', $quotation->getFillable());
        $this->assertNotContains('lead_id', $quotation->getFillable());
        $this->assertTrue(method_exists($quotation, 'client'));
        $this->assertFalse(method_exists($quotation, 'lead'));
    }

    public function test_schema_factories_and_seeders_no_longer_use_exact_lead_id_dependencies(): void
    {
        $paths = [
            base_path('database/migrations'),
            base_path('database/factories'),
            base_path('database/seeders'),
        ];

        foreach ($paths as $path) {
            foreach ($this->phpFiles($path) as $file) {
                $contents = file_get_contents($file);

                $this->assertStringNotContainsString("foreignId('lead_id')", $contents, $file);
                $this->assertStringNotContainsString("constrained('leads')", $contents, $file);
                $this->assertDoesNotMatchRegularExpression('/(?<![a-z_])lead_id(?![a-z_])/i', $contents, $file);
            }
        }
    }

    public function test_downstream_modules_import_unified_crm_client_model(): void
    {
        foreach ($this->downstreamPhpFiles() as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('App\\Modules\\Lead\\Models\\Lead', $contents, $file);
            $this->assertStringNotContainsString('App\\Modules\\Client\\Models\\Client', $contents, $file);
        }
    }

    /**
     * @return list<string>
     */
    private function downstreamPhpFiles(): array
    {
        return array_merge(
            $this->phpFiles(base_path('app/Modules/Proposal')),
            $this->phpFiles(base_path('app/Modules/Quotation')),
            $this->phpFiles(base_path('app/Modules/Project')),
            $this->phpFiles(base_path('app/Modules/Contract')),
            $this->phpFiles(base_path('app/Modules/Activity'))
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
