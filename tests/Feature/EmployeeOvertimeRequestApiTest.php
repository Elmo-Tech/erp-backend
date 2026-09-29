<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EmployeeOvertimeRequest;
use App\Models\HrPolicy;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\HR\OvertimeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeOvertimeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_can_manage_their_own_history_while_cross_employee_and_peer_manager_access_is_denied(): void
    {
        $employee = $this->employee();
        $otherEmployee = $this->employee();
        $manager = $this->employeeManager();
        $peerManager = $this->employeeManager();
        $date = $this->overtimeDate();

        $requestId = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload($date))
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $employee->id)
            ->assertJsonPath('data.requestedMinutes', 120)
            ->assertJsonPath('data.status', EmployeeOvertimeRequest::STATUS_PENDING)
            ->json('data.id');

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}/overtime-requests?year={$date->year}&month={$date->month}")
            ->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.id', $requestId)
            ->assertJsonPath('data.requests.0.unapprovedMinutes', null)
            ->assertJsonPath('data.summary.requestedMinutes', 120)
            ->assertJsonPath('data.summary.pendingCount', 1);
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/overtime-requests", $this->payload($date))
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/employees/{$otherEmployee->id}/overtime-requests?year={$date->year}&month={$date->month}")
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$otherEmployee->id}/overtime-requests", $this->payload($date))
            ->assertCreated();
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$otherEmployee->id}/overtime-requests?year={$date->year}&month={$date->month}")
            ->assertOk()
            ->assertJsonCount(1, 'data.requests');
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$peerManager->id}/overtime-requests", $this->payload($date))
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/employees/{$peerManager->id}/overtime-requests?year={$date->year}&month={$date->month}")
            ->assertForbidden();
    }

    public function test_only_hierarchy_authorized_managers_can_review_and_they_cannot_review_their_own_requests(): void
    {
        $employee = $this->employee();
        $manager = $this->employeeManager();
        $date = $this->overtimeDate();
        $requestId = $this->createRequest($employee, $date);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/approve")
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/approve", ['reviewNote' => 'Approved.'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeOvertimeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewedBy', $manager->id);
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $managerCreatedRequestId = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload($date, [
                'startTime' => '20:00',
                'endTime' => '21:00',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', EmployeeOvertimeRequest::STATUS_PENDING)
            ->json('data.id');
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$managerCreatedRequestId}/approve")
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$managerCreatedRequestId}/reject")
            ->assertForbidden();
        $secondManager = $this->employeeManager();
        $this->actingAs($secondManager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$managerCreatedRequestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.reviewedBy', $secondManager->id);

        $ownRequestId = $this->createRequest($manager, $date, ['startTime' => '20:00', 'endTime' => '21:00']);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$ownRequestId}/approve")
            ->assertForbidden();
    }

    public function test_submissions_validate_preapproval_date_authorization_window_and_server_derived_duration(): void
    {
        $employee = $this->employee();
        $date = $this->overtimeDate();

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload(today()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('overtimeDate');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload($date, [
                'startTime' => '17:00',
                'endTime' => '19:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('startTime');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload($date, [
                'endTime' => '18:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endTime');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", [
                'overtimeDate' => $date->toDateString(),
                'startTime' => '18:00',
                'endTime' => '20:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload($date, [
                'requestedMinutes' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requestedMinutes');
    }

    public function test_approval_snapshots_policy_rate_and_pays_only_actual_time_inside_the_approved_window(): void
    {
        $date = $this->overtimeDate();
        $employee = $this->employee([
            'salary' => 16000,
            'work_hours_per_day' => 8,
            'work_start_time' => '10:00:00',
        ]);
        $manager = $this->employeeManager();
        $policy = HrPolicy::create([
            'settings' => [
                'overtimeRequiresPreapproval' => true,
                'dailyRateDivisor' => 20,
                'overtimeMultiplier' => 2,
            ],
            'effective_from' => $date->toDateString(),
        ]);
        Attendance::create([
            'user_id' => $employee->id,
            'date' => $date->toDateString(),
            'clock_in' => $date->copy()->setTime(10, 0),
            'clock_out' => $date->copy()->setTime(22, 0),
            'expected_clock_in' => '10:00:00',
            'expected_clock_out' => '18:00:00',
        ]);
        $requestId = $this->createRequest($employee, $date, [
            'startTime' => '18:00',
            'endTime' => '21:00',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/approve", ['reviewNote' => 'Approved window.'])
            ->assertOk()
            ->assertJsonPath('data.actualMinutes', 240)
            ->assertJsonPath('data.approvedMinutes', 180)
            ->assertJsonPath('data.unapprovedMinutes', 60)
            ->assertJsonPath('data.policyVersionId', $policy->id)
            ->assertJsonPath('data.hourlyRate', 100)
            ->assertJsonPath('data.overtimeMultiplier', 2)
            ->assertJsonPath('data.payableAmount', 600);

        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'user_id' => $employee->id,
            'adjustment_type' => SalaryAdjustment::TYPE_OVERTIME,
            'reference_type' => EmployeeOvertimeRequest::class,
            'reference_id' => $requestId,
            'amount' => 600,
            'payroll_month' => $date->month,
            'payroll_year' => $date->year,
            'status' => SalaryAdjustment::STATUS_APPROVED,
        ]);
    }

    public function test_rejected_requests_never_create_salary_adjustments(): void
    {
        $employee = $this->employee();
        $manager = $this->employeeManager();
        $requestId = $this->createRequest($employee, $this->overtimeDate());

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/reject", ['reviewNote' => 'Not required.'])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeOvertimeRequest::STATUS_REJECTED);

        $this->assertDatabaseMissing('salary_adjustment_transactions', [
            'reference_type' => EmployeeOvertimeRequest::class,
            'reference_id' => $requestId,
        ]);
    }

    public function test_approved_requests_without_closed_attendance_are_unpayable_then_sync_idempotently_on_attendance_changes(): void
    {
        $date = $this->overtimeDate();
        $employee = $this->employee([
            'salary' => 24000,
            'work_hours_per_day' => 8,
            'work_start_time' => '10:00:00',
        ]);
        $manager = $this->employeeManager();
        $requestId = $this->createRequest($employee, $date);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.actualMinutes', null)
            ->assertJsonPath('data.approvedMinutes', null)
            ->assertJsonPath('data.payableAmount', null);
        $this->assertDatabaseCount('salary_adjustment_transactions', 0);

        $attendance = app(AttendanceService::class)->createAttendance([
            'userId' => $employee->id,
            'date' => $date->toDateString(),
            'clockIn' => $date->copy()->setTime(10, 0)->toDateTimeString(),
            'clockOut' => $date->copy()->setTime(21, 0)->toDateTimeString(),
        ], $manager);
        $overtimeRequest = EmployeeOvertimeRequest::findOrFail($requestId);

        $this->assertSame(180, $overtimeRequest->actual_minutes);
        $this->assertSame(120, $overtimeRequest->approved_minutes);
        $this->assertSame(60, $overtimeRequest->unapproved_minutes);
        $this->assertSame('300.00', $overtimeRequest->payable_amount);
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);

        app(OvertimeRequestService::class)->synchronize($overtimeRequest);
        app(OvertimeRequestService::class)->synchronize($overtimeRequest);
        $this->assertDatabaseCount('salary_adjustment_transactions', 1);

        app(AttendanceService::class)->updateAttendance($attendance, [
            'clockOut' => $date->copy()->setTime(18, 0)->toDateTimeString(),
        ], $manager);

        $this->assertDatabaseHas('employee_overtime_requests', [
            'id' => $requestId,
            'actual_minutes' => 0,
            'approved_minutes' => 0,
            'payable_amount' => 0,
        ]);
        $this->assertDatabaseHas('salary_adjustment_transactions', [
            'reference_type' => EmployeeOvertimeRequest::class,
            'reference_id' => $requestId,
            'status' => SalaryAdjustment::STATUS_CANCELLED,
        ]);
    }

    public function test_sync_never_mutates_a_processed_overtime_adjustment(): void
    {
        $date = $this->overtimeDate();
        $employee = $this->employee(['salary' => 24000]);
        $manager = $this->employeeManager();
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'date' => $date->toDateString(),
            'clock_in' => $date->copy()->setTime(10, 0),
            'clock_out' => $date->copy()->setTime(20, 0),
            'expected_clock_in' => '10:00:00',
            'expected_clock_out' => '18:00:00',
        ]);
        $requestId = $this->createRequest($employee, $date);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/employee-overtime-requests/{$requestId}/approve")
            ->assertOk();
        $adjustment = SalaryAdjustment::sole();
        $this->assertTrue($adjustment->markAsProcessed());

        app(AttendanceService::class)->updateAttendance($attendance, [
            'clockOut' => $date->copy()->setTime(18, 0)->toDateTimeString(),
        ], $manager);

        $this->assertSame(SalaryAdjustment::STATUS_PROCESSED, $adjustment->fresh()->status);
        $this->assertSame('300.00', $adjustment->fresh()->amount);
        $this->assertDatabaseHas('employee_overtime_requests', [
            'id' => $requestId,
            'actual_minutes' => 0,
            'approved_minutes' => 0,
            'payable_amount' => 0,
        ]);
    }

    private function employee(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_employee' => true,
            'work_hours_per_day' => 8,
            'work_start_time' => '10:00:00',
        ], $attributes));
    }

    private function employeeManager(): User
    {
        $manager = $this->employee();
        $manager->givePermissionTo(Permission::findOrCreate('manage_employees', 'web'));

        return $manager;
    }

    private function createRequest(User $employee, Carbon $date, array $overrides = []): int
    {
        return $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/employees/{$employee->id}/overtime-requests", $this->payload($date, $overrides))
            ->assertCreated()
            ->json('data.id');
    }

    private function overtimeDate(): Carbon
    {
        return today()->addDay()->startOfDay();
    }

    private function payload(Carbon $date, array $overrides = []): array
    {
        return array_merge([
            'overtimeDate' => $date->toDateString(),
            'startTime' => '18:00',
            'endTime' => '20:00',
            'reason' => 'Release support coverage.',
        ], $overrides);
    }
}
