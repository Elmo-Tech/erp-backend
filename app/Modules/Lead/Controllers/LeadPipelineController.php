<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\LeadStage;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\ProcessLeadStageUpdateAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Requests\UpdateLeadStageRequest;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeadPipelineController extends Controller
{
    private const PIPELINE_ORDER = ['new_lead', 'contacted', 'meeting', 'proposal', 'quotation', 'contract', 'won'];

    private const INTERACTIVE_STAGES = ['meeting', 'proposal', 'quotation', 'contract'];

    /**
     * Update the pipeline stage of a lead.
     *
     * POST /api/v1/leads/{lead}/stage
     *
     * Accepts the new stage and an array of stage results (documents produced
     * during the transition). Delegates all business logic to
     * ProcessLeadStageUpdateAction, which runs inside a DB transaction.
     */
    public function updateStage(
        UpdateLeadStageRequest $request,
        Lead $lead,
        ProcessLeadStageUpdateAction $action,
        MeetingService $meetingService
    ): JsonResponse {
        abort_unless($request->user()?->canAny(['edit_leads', 'manage_leads']), 403, 'Unauthorized');

        $newStage = LeadStage::from($request->validated('new_stage'));
        $stageResults = $request->validated('stage_results');
        $scheduledMeeting = $request->validated('scheduled_meeting');

        if ($newStage === LeadStage::WON) {
            abort_unless($request->user()?->canAny(['convert_leads', 'manage_leads']), 403, 'Unauthorized');
        }

        $this->authorizeStageResults($request, $stageResults);

        if (is_array($scheduledMeeting)) {
            abort_unless($request->user()?->can('schedule_meetings'), 403, 'Unauthorized');
        }

        if ($response = $this->validateStageGate($lead, $newStage)) {
            return $response;
        }

        if ($response = $this->validateTransition($lead, $newStage, $stageResults, $scheduledMeeting)) {
            return $response;
        }

        $updatedLead = DB::transaction(function () use ($action, $lead, $meetingService, $newStage, $request, $scheduledMeeting, $stageResults) {
            $updatedLead = $action->execute($lead->id, $newStage, $stageResults);

            if (is_array($scheduledMeeting)) {
                $meetingService->scheduleMeeting($lead, $this->meetingPayload($scheduledMeeting), $request->user());
            }

            return $updatedLead;
        });

        return ApiResponse::success(
            [
                'id' => $updatedLead->id,
                'stage' => $updatedLead->stage->value,
            ],
            'Lead stage updated successfully.'
        );
    }

    private function validateStageGate(Lead $lead, LeadStage $newStage): ?JsonResponse
    {
        $requiresMeeting = in_array($newStage, [LeadStage::PROPOSAL, LeadStage::QUOTATION, LeadStage::CONTRACT, LeadStage::WON], true);

        if ($requiresMeeting && ! $this->hasCompletedMeeting($lead)) {
            $message = 'Cannot advance: Complete the meeting and add notes first.';

            return ApiResponse::error($message, 422, ['stage' => [$message]]);
        }

        $requiresAcceptedDocument = in_array($newStage, [LeadStage::CONTRACT, LeadStage::WON], true);

        if ($requiresAcceptedDocument && ! $this->hasAcceptedProposalOrQuotation($lead)) {
            $message = 'Cannot advance: An accepted proposal or quotation is required first.';

            return ApiResponse::error($message, 422, ['stage' => [$message]]);
        }

        return null;
    }

    /**
     * Reject backward/terminal moves and require explicit results for every
     * interactive stage crossed by a forward jump.
     *
     * @param  array<int, array<string, mixed>>  $stageResults
     * @param  array<string, mixed>|null  $scheduledMeeting
     */
    private function validateTransition(Lead $lead, LeadStage $newStage, array $stageResults, ?array $scheduledMeeting): ?JsonResponse
    {
        $currentStage = $lead->stage;

        if (in_array($currentStage, [LeadStage::WON, LeadStage::LOST], true)) {
            $message = 'Cannot move a lead after it reaches a terminal stage.';

            return ApiResponse::error($message, 422, ['stage' => [$message]]);
        }

        if ($newStage === LeadStage::LOST) {
            return null;
        }

        $currentIndex = array_search($currentStage->value, self::PIPELINE_ORDER, true);
        $newIndex = array_search($newStage->value, self::PIPELINE_ORDER, true);

        if ($currentIndex === false || $newIndex === false || $newIndex <= $currentIndex) {
            $message = 'Lead stages can only move forward.';

            return ApiResponse::error($message, 422, ['stage' => [$message]]);
        }

        $missingStages = $this->missingStageEvidence(
            $currentIndex,
            $newIndex,
            $stageResults,
            is_array($scheduledMeeting)
        );

        if ($missingStages !== []) {
            $labels = implode(', ', array_map(fn (string $stage) => LeadStage::from($stage)->label(), $missingStages));
            $message = "Cannot advance: complete or explicitly skip these stages first: {$labels}.";

            return ApiResponse::error($message, 422, ['stage_results' => [$message]]);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stageResults
     * @return array<int, string>
     */
    private function missingStageEvidence(int $currentIndex, int $newIndex, array $stageResults, bool $meetingScheduled): array
    {
        $crossedStages = array_slice(self::PIPELINE_ORDER, $currentIndex + 1, $newIndex - $currentIndex);
        $requiredStages = array_values(array_intersect($crossedStages, self::INTERACTIVE_STAGES));
        $completedStages = collect($stageResults)->pluck('stage')->filter()->all();

        if ($meetingScheduled) {
            $completedStages[] = LeadStage::MEETING->value;
        }

        return array_values(array_diff($requiredStages, $completedStages));
    }

    /**
     * @param  array<int, array<string, mixed>>  $stageResults
     */
    private function authorizeStageResults(UpdateLeadStageRequest $request, array $stageResults): void
    {
        foreach ($stageResults as $stageResult) {
            $permissions = match ($stageResult['type']) {
                'quotation' => ['create_quotations', 'manage_leads'],
                'contract' => ['create_contracts', 'manage_contracts'],
                default => ['manage_leads'],
            };

            abort_unless($request->user()?->canAny($permissions), 403, 'Unauthorized');

            if ($stageResult['type'] === 'contract' && ($stageResult['status'] ?? null) === 'signed') {
                abort_unless($request->user()->canAny(['sign_contracts', 'manage_contracts']), 403, 'Unauthorized');
            }
        }
    }

    private function hasCompletedMeeting(Lead $lead): bool
    {
        return Meeting::query()
            ->where('model_type', Lead::class)
            ->where('model_id', $lead->id)
            ->where('status', 'completed')
            ->exists();
    }

    private function hasAcceptedProposalOrQuotation(Lead $lead): bool
    {
        return $lead->proposals()->where('status', 'accepted')->exists()
            || $lead->quotations()->where('status', 'accepted')->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function meetingPayload(array $data): array
    {
        $meetingType = (string) ($data['meeting_type'] ?? 'meeting');
        $title = str($meetingType)
            ->replace(['-', '_'], ' ')
            ->title()
            ->append(' Meeting')
            ->toString();
        $internalAttendees = collect($data['internal_attendees'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        return [
            'title' => $title,
            'meetingType' => $meetingType,
            'description' => $data['notes'] ?? null,
            'location' => $data['location_type'] ?? null,
            'locationDetails' => $data['location_details'] ?? null,
            'internalAttendees' => $internalAttendees,
            'externalAttendees' => $data['external_attendees'] ?? [],
            'assignedTo' => $internalAttendees[0] ?? null,
            'scheduledAt' => Carbon::parse(($data['date'] ?? now()->toDateString()).' '.($data['time'] ?? '00:00')),
        ];
    }
}
