<?php

namespace App\Modules\Meetings\Actions;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientActivityLogger;
use App\Modules\Lead\Models\Lead;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Services\MeetingService;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ScheduleMeetingAction
{
    public function __construct(
        private MeetingService $meetingService,
        private ClientActivityLogger $clientActivityLogger
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $user): Meeting
    {
        /** @var class-string<Model> $subjectClass */
        $subjectClass = $data['subjectType'];
        $subject = $subjectClass::findOrFail($data['subjectId']);
        $this->authorizeSubject($subject, $user);

        $meeting = $this->meetingService->scheduleMeeting($subject, $data, $user);
        $this->logClientMeetingActivity($meeting, $subject, $user);

        return $meeting;
    }

    private function authorizeSubject(Model $subject, User $user): void
    {
        $allowed = match (true) {
            $subject instanceof Client, $subject instanceof Project => Gate::forUser($user)->allows('view', $subject),
            $subject instanceof Lead => $user->can('view_leads') || $user->can('manage_leads'),
            default => false,
        };

        abort_unless($allowed, 403, 'Unauthorized meeting subject');
    }

    private function logClientMeetingActivity(Meeting $meeting, Model $subject, User $user): void
    {
        $client = null;

        if ($subject instanceof Client) {
            $client = $subject;
        }

        if ($subject instanceof Project) {
            $client = $subject->relationLoaded('client') ? $subject->client : $subject->client()->first();
        }

        if ($client) {
            $this->clientActivityLogger->meetingScheduled($meeting, $client, $user);
        }
    }
}
