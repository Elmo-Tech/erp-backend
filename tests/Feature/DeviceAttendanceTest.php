<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\DeviceAccessEvent;
use App\Models\DeviceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeviceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'manage_devices']);
        Permission::firstOrCreate(['name' => 'manage_attendance']);
    }

    public function test_device_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/devices')->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list_devices(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/devices')
            ->assertForbidden();
    }

    public function test_admin_can_list_devices(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage_devices');

        Device::factory()->create(['name' => 'Main Entrance']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Main Entrance');
    }

    public function test_admin_can_create_a_device(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage_devices');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', [
                'name' => 'Warehouse',
                'ipAddress' => '10.0.0.9',
                'port' => 80,
                'username' => 'admin',
                'password' => 'secret',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Warehouse')
            ->assertJsonPath('data.ipAddress', '10.0.0.9');

        $this->assertDatabaseHas('devices', ['name' => 'Warehouse', 'ip_address' => '10.0.0.9']);
    }

    public function test_existing_employee_number_can_be_linked_without_contacting_device_and_conflicts_are_rejected(): void
    {
        Http::fake();
        $device = Device::factory()->create();
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage_devices');
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $payload = ['userId' => $employee->id, 'deviceEmployeeNo' => '007A'];
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/devices/{$device->id}/users/map-existing", $payload)->assertForbidden();
        $this->actingAs($manager, 'sanctum')->postJson("/api/v1/devices/{$device->id}/users/map-existing", $payload)->assertOk();
        $this->postJson("/api/v1/devices/{$device->id}/users/map-existing", $payload)->assertOk();
        $this->postJson("/api/v1/devices/{$device->id}/users/map-existing", ['userId' => $other->id, 'deviceEmployeeNo' => '007A'])->assertUnprocessable();
        $this->postJson("/api/v1/devices/{$device->id}/users/map-existing", ['userId' => $employee->id, 'deviceEmployeeNo' => 'other'])->assertUnprocessable();
        $this->assertDatabaseCount('device_users', 1);
        $this->assertDatabaseHas('device_users', ['device_id' => $device->id, 'user_id' => $employee->id, 'device_employee_no' => '007A']);
        Http::assertNothingSent();
    }

    public function test_device_password_is_encrypted_at_rest(): void
    {
        $device = Device::factory()->create(['password' => 'super-secret']);

        $this->assertNotSame('super-secret', $device->getRawOriginal('password'));
        $this->assertSame('super-secret', $device->fresh()->password);
    }

    public function test_first_authorised_punch_creates_the_attendance_record(): void
    {
        $device = Device::factory()->create();
        $user = $this->employee();

        $this->linkUser($device, $user, '101');

        $this->pushPunch($device, '101', Carbon::parse('2026-01-05T09:58:00'));

        $attendance = Attendance::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($attendance);
        $this->assertSame('device', $attendance->source);
        $this->assertSame($device->id, $attendance->device_id);
        $this->assertSame('09:58:00', $attendance->clock_in->format('H:i:s'));
        $this->assertNull($attendance->clock_out);
    }

    public function test_second_authorised_punch_closes_the_day(): void
    {
        $device = Device::factory()->create();
        $user = $this->employee();

        $this->linkUser($device, $user, '101');

        $this->pushPunch($device, '101', Carbon::parse('2026-01-05T09:58:00'));
        $this->pushPunch($device, '101', Carbon::parse('2026-01-05T18:05:00'));

        $attendance = Attendance::query()->where('user_id', $user->id)->first();

        $this->assertSame('09:58:00', $attendance->clock_in->format('H:i:s'));
        $this->assertSame('18:05:00', $attendance->clock_out->format('H:i:s'));
    }

    public function test_a_repeated_punch_does_not_create_a_second_record(): void
    {
        $device = Device::factory()->create();
        $user = $this->employee();

        $this->linkUser($device, $user, '101');

        $this->pushPunch($device, '101', Carbon::parse('2026-01-05T09:58:00'));
        $this->pushPunch($device, '101', Carbon::parse('2026-01-05T09:58:00'));

        $this->assertSame(1, Attendance::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, DeviceAccessEvent::query()->count());
    }

    public function test_an_unauthorised_punch_is_stored_but_does_not_create_attendance(): void
    {
        $device = Device::factory()->create();
        $user = $this->employee();

        $this->linkUser($device, $user, '101');

        $this->pushPunch($device, '101', Carbon::parse('2026-01-05T09:58:00'), 'Invalid');

        $this->assertSame(1, DeviceAccessEvent::query()->count());
        $this->assertSame(0, Attendance::query()->where('user_id', $user->id)->count());
    }

    public function test_a_punch_of_an_unmapped_employee_is_stored_without_attendance(): void
    {
        $device = Device::factory()->create();

        $this->pushPunch($device, '999', Carbon::parse('2026-01-05T09:58:00'));

        $this->assertSame(1, DeviceAccessEvent::query()->count());
        $this->assertNull(DeviceAccessEvent::query()->first()->user_id);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_the_webhook_rejects_an_unknown_token(): void
    {
        $this->postJson('/api/v1/device-events/unknown-token', [
            'employeeNoString' => '101',
            'dateTime' => '2026-01-05T09:58:00',
        ])->assertNotFound();
    }

    public function test_the_webhook_accepts_a_pushed_event(): void
    {
        $device = Device::factory()->create();
        $user = $this->employee();

        $this->linkUser($device, $user, '101');

        $this->postJson("/api/v1/device-events/{$device->webhook_token}", [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2026-01-05T09:58:00',
            'AccessControllerEvent' => [
                'employeeNoString' => '101',
                'doorNo' => 1,
                'attendanceStatus' => 'CheckIn',
            ],
        ])->assertOk();

        $this->assertSame(1, Attendance::query()->where('user_id', $user->id)->count());
    }

    public function test_the_webhook_does_not_require_a_sanctum_token(): void
    {
        $device = Device::factory()->create();

        $this->postJson("/api/v1/device-events/{$device->webhook_token}", [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2026-01-05T09:58:00',
            'AccessControllerEvent' => [
                'employeeNoString' => '101',
            ],
        ])->assertOk();
    }

    public function test_the_polling_job_reads_events_from_the_device(): void
    {
        $device = Device::factory()->create();
        $user = $this->employee();

        $this->linkUser($device, $user, '101');

        Http::fake([
            '*' => Http::response([
                'AcsEvent' => [
                    [
                        'major' => 5,
                        'minor' => 75,
                        'dateTime' => '2026-01-05T09:58:00',
                        'employeeNoString' => '101',
                        'cardNo' => '0001234567',
                        'statusValue' => 281,
                    ],
                ],
            ]),
        ]);

        $this->artisan('devices:sync')->assertSuccessful();

        $this->assertSame(1, DeviceAccessEvent::query()->count());
    }

    private function employee(): User
    {
        return User::factory()->create([
            'is_employee' => true,
            'work_start_time' => '10:00:00',
            'grace_days_allowed' => 5,
            'grace_days_used' => 0,
        ]);
    }

    private function linkUser(Device $device, User $user, string $employeeNo): DeviceUser
    {
        return DeviceUser::query()->create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'device_employee_no' => $employeeNo,
        ]);
    }

    private function pushPunch(
        Device $device,
        string $employeeNo,
        Carbon $occurredAt,
        string $status = 'Valid'
    ): void {
        $this->postJson("/api/v1/device-events/{$device->webhook_token}", [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => $occurredAt->format('Y-m-d\TH:i:s'),
            'AccessControllerEvent' => [
                'employeeNoString' => $employeeNo,
                'doorNo' => 1,
                'status' => $status,
            ],
        ])->assertOk();
    }
}
