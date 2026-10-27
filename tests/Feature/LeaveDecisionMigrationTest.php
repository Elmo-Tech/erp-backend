<?php

namespace Tests\Feature;

use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveDecisionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_requests_default_to_no_decision_and_database_rejects_pending(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $attributes = [
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::where('slug', 'annual')->firstOrFail()->id,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02',
            'working_days' => 1, 'is_paid' => true,
        ];
        $leave = EmployeeLeave::create($attributes)->fresh();
        $this->assertNull($leave->status);
        $this->assertTrue($leave->needsReview());
        $this->expectException(QueryException::class);
        EmployeeLeave::create($attributes + ['status' => 'pending']);
    }

    public function test_existing_requests_are_converted_without_changing_decisions_usage_or_audit_fields(): void
    {
        $employee = User::factory()->create(['is_employee' => true]);
        $type = LeaveType::where('slug', 'annual')->firstOrFail();
        $migration = require database_path('migrations/2026_10_27_000001_remove_pending_leave_status.php');
        $migration->down();
        $records = collect(['pending', 'approved', 'rejected', 'cancelled'])->mapWithKeys(function (string $status) use ($employee, $type): array {
            $record = EmployeeLeave::create([
                'employee_id' => $employee->id, 'leave_type_id' => $type->id,
                'start_date' => '2026-11-02', 'end_date' => '2026-11-02',
                'working_days' => 1, 'is_paid' => true, 'status' => $status,
                'reason' => 'Retain the original request', 'review_note' => 'Retain the audit note',
            ]);

            return [$status => $record];
        });
        $balance = EmployeeLeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'year' => 2026, 'allocated_days' => 21, 'used_days' => 5,
        ]);
        $original = $records['pending']->getRawOriginal();

        $migration->up();

        $this->assertNull($records['pending']->fresh()->status);
        $this->assertTrue($records['pending']->fresh()->needsReview());
        foreach (['approved', 'rejected', 'cancelled'] as $status) {
            $this->assertSame($status, $records[$status]->fresh()->status);
            $this->assertFalse($records[$status]->fresh()->needsReview());
        }
        foreach (['reason', 'review_note', 'created_at', 'updated_at'] as $field) {
            $this->assertSame($original[$field], $records['pending']->fresh()->getRawOriginal($field));
        }
        $this->assertSame('5.00', $balance->fresh()->used_days);
        $this->actingAs($employee, 'sanctum')->getJson("/api/v1/employees/{$employee->id}/leaves?year=2026")
            ->assertOk()->assertJsonMissing(['status' => 'pending']);

        $migration->down();
        $this->assertSame('pending', $records['pending']->fresh()->status);
        $migration->up();
        $this->assertNull($records['pending']->fresh()->status);
    }
}
