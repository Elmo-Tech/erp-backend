<?php

namespace Tests\Feature\Lead;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('salesDocumentUploadCases')]
    public function test_direct_creation_stores_a_private_downloadable_document(string $resource, array $permissions): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions($permissions);
        $lead = Lead::create([
            'title' => 'Enterprise CRM',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/leads/{$lead->id}/{$resource}", [
                'creation_type' => 'direct_upload',
                'upload_file' => UploadedFile::fake()->createWithContent('sales-document.pdf', '%PDF-sales-document'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.file_name', 'sales-document.pdf');

        $this->actingAs($user, 'sanctum')
            ->get($response->json('data.file_path'))
            ->assertOk()
            ->assertStreamedContent('%PDF-sales-document');
    }

    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function salesDocumentUploadCases(): array
    {
        return [
            'proposal' => ['proposals', ['manage_leads']],
            'quotation' => ['quotations', ['create_quotations', 'view_quotations']],
            'contract' => ['contracts', ['create_contracts', 'view_contracts']],
        ];
    }

    public function test_uploaded_sales_document_remains_downloadable_after_lead_conversion(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions(['manage_leads', 'view_projects', 'manage_all_projects']);
        $lead = Lead::create([
            'title' => 'Enterprise CRM',
            'stage' => LeadStage::WON,
            'status' => LeadStatus::ACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/leads/{$lead->id}/proposals", [
                'creation_type' => 'direct_upload',
                'upload_file' => UploadedFile::fake()->createWithContent('winning-proposal.pdf', '%PDF-winning-proposal'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $projectId = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertOk()
            ->json('data.project_id');

        $downloadUrl = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$projectId}/sales-documents")
            ->assertOk()
            ->assertJsonPath('data.documents.0.name', 'winning-proposal.pdf')
            ->json('data.documents.0.fileUrl');

        $this->actingAs($user, 'sanctum')
            ->get($downloadUrl)
            ->assertOk()
            ->assertStreamedContent('%PDF-winning-proposal');
    }

    public function test_sales_document_download_requires_the_matching_read_permission(): void
    {
        Storage::fake('local');
        $creator = $this->userWithPermissions(['manage_leads']);
        $unauthorizedUser = User::factory()->create();
        $lead = Lead::create([
            'title' => 'Enterprise CRM',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);

        $downloadUrl = $this->actingAs($creator, 'sanctum')
            ->post("/api/v1/leads/{$lead->id}/proposals", [
                'creation_type' => 'direct_upload',
                'upload_file' => UploadedFile::fake()->createWithContent('proposal.pdf', '%PDF-private'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.file_path');

        $this->actingAs($unauthorizedUser, 'sanctum')
            ->get($downloadUrl)
            ->assertForbidden();
    }

    public function test_sales_document_download_is_scoped_to_its_parent_lead(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions(['manage_leads']);
        $lead = Lead::create([
            'title' => 'Enterprise CRM',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);
        $otherLead = Lead::create([
            'title' => 'Other Lead',
            'stage' => LeadStage::NEW_LEAD,
            'status' => LeadStatus::ACTIVE,
        ]);

        $documentId = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/leads/{$lead->id}/proposals", [
                'creation_type' => 'direct_upload',
                'upload_file' => UploadedFile::fake()->createWithContent('proposal.pdf', '%PDF-private'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->get("/api/v1/leads/{$otherLead->id}/documents/proposal/{$documentId}/download")
            ->assertNotFound();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
