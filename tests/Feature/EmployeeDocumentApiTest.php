<?php

namespace Tests\Feature;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_upload_list_and_download_their_own_private_document(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $employee = User::factory()->create(['is_employee' => true]);
        $content = "%PDF-1.4\nprivate employee document\n%%EOF";

        $upload = $this->actingAs($employee, 'sanctum')
            ->post("/api/v1/employees/{$employee->id}/documents", [
                'name' => 'Government ID',
                'documentType' => 'identification',
                'file' => UploadedFile::fake()->createWithContent('identity.pdf', $content),
            ])
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.name', 'Government ID')
            ->assertJsonPath('data.documentType', 'identification')
            ->assertJsonPath('data.originalFilename', 'identity.pdf');

        $document = EmployeeDocument::findOrFail($upload->json('data.id'));

        $this->assertSame('local', $document->disk);
        $this->assertStringStartsWith("employee-documents/{$employee->id}/", $document->path);
        Storage::disk('local')->assertExists($document->path);
        Storage::disk('public')->assertMissing($document->path);
        $this->assertArrayNotHasKey('url', $upload->json('data'));
        $this->assertArrayNotHasKey('path', $upload->json('data'));
        $this->assertArrayNotHasKey('disk', $upload->json('data'));

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.originalFilename', 'identity.pdf');

        $download = $this->actingAs($employee, 'sanctum')
            ->get("/api/v1/employee-documents/{$document->id}/download")
            ->assertOk()
            ->assertDownload('identity.pdf');

        $this->assertSame($content, $download->streamedContent());
    }

    public function test_manager_can_manage_a_subordinate_document_and_delete_removes_the_file(): void
    {
        Storage::fake('local');
        $manager = $this->employeeManager();
        $employee = User::factory()->create(['is_employee' => true]);

        $upload = $this->actingAs($manager, 'sanctum')
            ->post("/api/v1/employees/{$employee->id}/documents", [
                'name' => 'Signed contract',
                'documentType' => 'contract',
                'file' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
            ])
            ->assertCreated();

        $document = EmployeeDocument::findOrFail($upload->json('data.id'));

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.0.uploadedBy', $manager->id);

        $this->actingAs($manager, 'sanctum')
            ->get("/api/v1/employee-documents/{$document->id}/download")
            ->assertOk()
            ->assertDownload('contract.pdf');

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/employee-documents/{$document->id}")
            ->assertOk();

        Storage::disk('local')->assertMissing($document->path);
        $this->assertSoftDeleted('employee_documents', ['id' => $document->id]);
        $this->assertNull(EmployeeDocument::find($document->id));
    }

    public function test_employee_cannot_access_another_employees_documents_or_delete_their_own(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create(['is_employee' => true]);
        $otherEmployee = User::factory()->create(['is_employee' => true]);
        $document = $this->documentFor($otherEmployee);
        $ownDocument = $this->documentFor($employee);

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$otherEmployee->id}/documents")
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->post("/api/v1/employees/{$otherEmployee->id}/documents", [
                'name' => 'Other document',
                'documentType' => 'contract',
                'file' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->get("/api/v1/employee-documents/{$document->id}/download")
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->deleteJson("/api/v1/employee-documents/{$ownDocument->id}")
            ->assertForbidden();
    }

    public function test_manager_must_pass_target_hierarchy_authorization(): void
    {
        Storage::fake('local');
        $manager = $this->employeeManager();
        $peerManager = $this->employeeManager();
        $document = $this->documentFor($peerManager);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$peerManager->id}/documents")
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->post("/api/v1/employees/{$peerManager->id}/documents", [
                'name' => 'Peer document',
                'documentType' => 'contract',
                'file' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->get("/api/v1/employee-documents/{$document->id}/download")
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/employee-documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_document_upload_validates_the_required_fields_type_and_size(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create(['is_employee' => true]);
        $url = "/api/v1/employees/{$employee->id}/documents";

        $this->actingAs($employee, 'sanctum')
            ->post($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'documentType', 'file']);
        $this->actingAs($employee, 'sanctum')
            ->post($url, [
                'name' => 'Invalid file',
                'documentType' => 'contract',
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->actingAs($employee, 'sanctum')
            ->post($url, [
                'name' => 'Large file',
                'documentType' => 'contract',
                'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    private function employeeManager(): User
    {
        $manager = User::factory()->create(['is_employee' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function documentFor(User $employee): EmployeeDocument
    {
        $path = "employee-documents/{$employee->id}/document.pdf";
        Storage::disk('local')->put($path, 'private document');

        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'name' => 'Private document',
            'document_type' => 'contract',
            'original_filename' => 'document.pdf',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 16,
            'uploaded_by' => $employee->id,
        ]);
    }
}
