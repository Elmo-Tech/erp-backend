<?php

namespace Tests\Feature\Lead;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Meetings\Models\Meeting;
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
    public function test_pipeline_creation_stores_a_private_downloadable_document(
        string $resource,
        string $documentType,
        LeadStage $fromStage,
        LeadStage $toStage,
        array $permissions
    ): void {
        Storage::fake('local');
        $user = $this->userWithPermissions($permissions);
        $lead = Lead::create([
            'title' => 'Enterprise CRM',
            'stage' => $fromStage,
            'status' => LeadStatus::ACTIVE,
        ]);

        $this->completedMeeting($lead);
        if ($documentType === 'contract') {
            Quotation::create([
                'lead_id' => $lead->id,
                'status' => 'accepted',
                'creation_type' => DocumentCreationType::SKIPPED->value,
                'currency' => 'USD',
            ]);
        }

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/leads/{$lead->id}/stage", [
                'new_stage' => $toStage->value,
                'stage_results' => [[
                    'stage' => $toStage->value,
                    'type' => $documentType,
                    'creation_type' => 'direct_upload',
                    'upload_file' => UploadedFile::fake()->createWithContent('sales-document.pdf', '%PDF-sales-document'),
                ]],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/leads/{$lead->id}/{$resource}")
            ->assertOk()
            ->assertJsonPath('data.0.file_name', 'sales-document.pdf');

        $this->actingAs($user, 'sanctum')
            ->get($response->json('data.0.file_path'))
            ->assertOk()
            ->assertStreamedContent('%PDF-sales-document');
    }

    /**
     * @return array<string, array{string, string, LeadStage, LeadStage, array<int, string>}>
     */
    public static function salesDocumentUploadCases(): array
    {
        return [
            'proposal' => ['proposals', 'proposal', LeadStage::MEETING, LeadStage::PROPOSAL, ['manage_leads']],
            'quotation' => ['quotations', 'quotation', LeadStage::PROPOSAL, LeadStage::QUOTATION, ['edit_leads', 'create_quotations', 'view_quotations']],
            'contract' => ['contracts', 'contract', LeadStage::QUOTATION, LeadStage::CONTRACT, ['edit_leads', 'create_contracts', 'view_contracts']],
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

        $this->storedProposal($lead, 'winning-proposal.pdf', '%PDF-winning-proposal');

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

        $proposal = $this->storedProposal($lead, 'proposal.pdf', '%PDF-private');
        $downloadUrl = "/api/v1/leads/{$lead->id}/documents/proposal/{$proposal->id}/download";

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

        $documentId = $this->storedProposal($lead, 'proposal.pdf', '%PDF-private')->id;

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

    private function completedMeeting(Lead $lead): void
    {
        Meeting::create([
            'model_id' => $lead->id,
            'model_type' => Lead::class,
            'status' => 'completed',
            'title' => 'Discovery',
            'scheduled_at' => now(),
        ]);
    }

    private function storedProposal(Lead $lead, string $fileName, string $contents): Proposal
    {
        $path = "lead-documents/{$lead->id}/proposals/{$fileName}";
        Storage::disk('local')->put($path, $contents);

        return Proposal::create([
            'lead_id' => $lead->id,
            'status' => 'draft',
            'creation_type' => DocumentCreationType::DIRECT_UPLOAD->value,
            'file_path' => $path,
            'file_name' => $fileName,
            'file_mime_type' => 'application/pdf',
            'file_size' => strlen($contents),
        ]);
    }
}
