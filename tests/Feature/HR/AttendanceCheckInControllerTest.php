<?php

namespace Tests\Feature\HR;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceCheckInControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_check_in_for_today(): void
    {
        Carbon::setTestNow('2026-05-13 09:05:00');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.isClockedIn', true);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-05-13 00:00:00',
            'status' => 'present',
        ]);

        Carbon::setTestNow();
    }

    public function test_check_in_is_idempotent_for_existing_open_attendance(): void
    {
        Carbon::setTestNow('2026-05-13 09:05:00');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/hr/attendance/check-in')->assertOk();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/hr/attendance/check-in')->assertOk();

        $this->assertDatabaseCount('attendances', 1);

        Carbon::setTestNow();
    }
}
