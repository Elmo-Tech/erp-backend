<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalendarEventAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_update_and_delete_their_event(): void
    {
        $owner = $this->calendarUser();
        $event = $this->eventFor($owner, 'Owner Event');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/calendar/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Owner Event');

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/calendar/events/{$event->id}", ['title' => 'Updated Owner Event'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Owner Event');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/calendar/events/{$event->id}")
            ->assertOk();

        $this->assertSoftDeleted($event);
    }

    public function test_foreign_user_cannot_read_an_event(): void
    {
        $owner = $this->calendarUser();
        $otherUser = $this->calendarUser();
        $event = $this->eventFor($owner, 'Private Event');

        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/calendar/events/{$event->id}")
            ->assertForbidden();
    }

    public function test_foreign_user_cannot_update_an_event(): void
    {
        $owner = $this->calendarUser();
        $otherUser = $this->calendarUser();
        $event = $this->eventFor($owner, 'Original Title');

        $this->actingAs($otherUser, 'sanctum')
            ->patchJson("/api/v1/calendar/events/{$event->id}", ['title' => 'Hijacked Title'])
            ->assertForbidden();

        $this->assertSame('Original Title', $event->fresh()->title);
    }

    public function test_foreign_user_cannot_delete_an_event(): void
    {
        $owner = $this->calendarUser();
        $otherUser = $this->calendarUser();
        $event = $this->eventFor($owner, 'Persistent Event');

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/calendar/events/{$event->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted($event);
    }

    public function test_event_feed_contains_only_the_callers_events(): void
    {
        $owner = $this->calendarUser(includeEventFeed: true);
        $otherUser = $this->calendarUser();
        $ownEvent = $this->eventFor($owner, 'Visible Event');
        $foreignEvent = $this->eventFor($otherUser, 'Foreign Event');

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/calendar?startDate=2026-07-01&endDate=2026-07-31')
            ->assertOk();

        $ids = collect($response->json('data.items'))->pluck('id');

        $this->assertTrue($ids->contains('event-'.$ownEvent->id));
        $this->assertFalse($ids->contains('event-'.$foreignEvent->id));
    }

    private function calendarUser(bool $includeEventFeed = false): User
    {
        $user = User::factory()->create();
        $permissions = ['view_calendar'];

        if ($includeEventFeed) {
            $permissions[] = 'view_calendar_events';
        }

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    private function eventFor(User $user, string $title): CalendarEvent
    {
        return CalendarEvent::create([
            'user_id' => $user->id,
            'title' => $title,
            'start_time' => '2026-07-16 10:00:00',
            'end_time' => '2026-07-16 11:00:00',
            'event_type' => 'custom',
        ]);
    }
}
