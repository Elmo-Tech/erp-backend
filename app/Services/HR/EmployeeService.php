<?php

namespace App\Services\HR;

use App\Models\User;
use App\Models\TaskAssignee;
use App\Models\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    /**
     * Get all employees with pagination.
     * Shows all users except those with client role only.
     */
    public function getAllEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Get all users except those who have client role
        $query = User::with('roles')
            ->where(function ($q) {
                // Users without any roles OR users without client role
                $q->whereDoesntHave('roles')
                    ->orWhereDoesntHave('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'client');
                    });
            });

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['isActive'])) {
            $query->where('is_active', $filters['isActive']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get a single employee by ID.
     * Returns any user that is not a client.
     */
    public function getEmployee(int $id): ?User
    {
        return User::with('roles')
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereDoesntHave('roles')
                    ->orWhereDoesntHave('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'client');
                    });
            })
            ->first();
    }

    /**
     * Create a new employee.
     */
    public function createEmployee(array $data): User
    {
        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_employee' => true,
            'is_active' => $data['isActive'] ?? true,
            'salary' => $data['salary'] ?? null,
            'work_hours_per_day' => $data['workHoursPerDay'] ?? 8,
            'work_start_time' => $data['workStartTime'] ?? '10:00:00',
            'grace_days_allowed' => $data['graceDaysAllowed'] ?? 5,
            'internal_hourly_cost' => $data['internalHourlyCost'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'skills' => $data['skills'] ?? [],
        ]);

        // Assign role if provided
        if (!empty($data['role'])) {
            $employee->assignRole($data['role']);
        }

        return $employee->load('roles');
    }

    /**
     * Update an employee.
     */
    public function updateEmployee(User $employee, array $data): User
    {
        $updateData = [];

        $fieldMap = [
            'name' => 'name',
            'email' => 'email',
            'isActive' => 'is_active',
            'salary' => 'salary',
            'workHoursPerDay' => 'work_hours_per_day',
            'workStartTime' => 'work_start_time',
            'graceDaysAllowed' => 'grace_days_allowed',
            'internalHourlyCost' => 'internal_hourly_cost',
            'specialization' => 'specialization',
            'skills' => 'skills',
            'avatar' => 'avatar',
        ];

        foreach ($fieldMap as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $updateData[$snake] = $data[$camel];
            }
        }

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $employee->update($updateData);

        // Update role if provided
        if (!empty($data['role'])) {
            $employee->syncRoles([$data['role']]);
        }

        return $employee->fresh()->load('roles');
    }

    /**
     * Get employee performance summary.
     */
    public function getEmployeePerformance(User $employee, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get attendance stats
        $attendances = Attendance::where('user_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDays = $attendances->count();
        $totalLateMinutes = $attendances->sum('late_minutes');
        $totalIdleMinutes = $attendances->sum('idle_minutes');
        $totalDeductionMinutes = $attendances->sum('deduction_minutes');
        $graceDaysUsed = $attendances->where('used_grace_day', true)->count();

        // Get task points (only from primary assignees)
        $taskPoints = TaskAssignee::where('user_id', $employee->id)
            ->where('is_primary', true) // Only count points from primary assignments
            ->whereHas('task', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('updated_at', [$startDate, $endDate]);
            })
            ->sum('points_earned');

        // Calculate deduction amount
        $deductionAmount = 0;
        if ($employee->salary && $totalDeductionMinutes > 0) {
            $hourlyRate = $employee->salary / (30 * $employee->work_hours_per_day);
            $deductionAmount = ($totalDeductionMinutes / 60) * $hourlyRate;
        }

        // Get projects worked on
        $projectsWorkedOn = TaskAssignee::where('user_id', $employee->id)
            ->whereHas('task', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('updated_at', [$startDate, $endDate]);
            })
            ->with('task.project:id,title')
            ->get()
            ->pluck('task.project')
            ->filter()
            ->unique('id')
            ->values();

        return [
            'employee' => [
                'userId' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'avatar' => $employee->avatar,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
            ],
            'attendance' => [
                'totalDays' => $totalDays,
                'totalLateMinutes' => $totalLateMinutes,
                'totalIdleMinutes' => $totalIdleMinutes,
                'graceDaysUsed' => $graceDaysUsed,
                'graceDaysRemaining' => max(0, $employee->grace_days_allowed - $employee->grace_days_used),
            ],
            'deductions' => [
                'totalMinutes' => $totalDeductionMinutes,
                'totalAmount' => round($deductionAmount, 2),
            ],
            'performance' => [
                'pointsEarned' => $taskPoints,
                'totalPoints' => $employee->total_points,
                'projectsWorkedOn' => $projectsWorkedOn->count(),
                'projects' => $projectsWorkedOn->map(fn($p) => [
                    'projectId' => $p->id,
                    'title' => $p->title,
                ]),
            ],
        ];
    }

    /**
     * Get employee expected clock-out time.
     */
    public function getExpectedClockOut(User $employee): ?string
    {
        return $employee->expected_clock_out;
    }

    /**
     * Deactivate an employee.
     */
    public function deactivateEmployee(User $employee): User
    {
        $employee->update(['is_active' => false]);
        return $employee;
    }

    /**
     * Activate an employee.
     */
    public function activateEmployee(User $employee): User
    {
        $employee->update(['is_active' => true]);
        return $employee;
    }
}
