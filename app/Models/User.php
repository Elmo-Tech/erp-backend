<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $employee_no
 * @property Carbon|null $email_verified_at
 * @property string|null $avatar
 * @property array<int, string>|null $skills
 * @property string|null $specialization
 * @property bool $is_active
 * @property float|int|string|null $salary
 * @property float|int|string|null $hourly_rate
 * @property float|int|string|null $total_deductions
 * @property float|null $work_hours_per_day
 * @property string|null $work_start_time
 * @property int|null $grace_days_allowed
 * @property int|null $grace_days_used
 * @property int|null $total_points
 * @property float|int|string|null $internal_hourly_cost
 * @property Carbon|null $grace_days_reset_date
 * @property bool $is_employee
 * @property string|null $expected_clock_out
 * @property-read Client|null $client
 * @property-read Client|null $clientProfile
 * @property-read Collection<int, Role> $roles
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'employee_no',
        'password',
        'avatar',
        'internal_hourly_cost',
        'skills',
        'specialization',
        'is_active',
        'is_employee',
        'salary',
        'hourly_rate',
        'work_hours_per_day',
        'work_start_time',
        'grace_days_allowed',
        'grace_days_used',
        'grace_days_reset_date',
        'total_points',
        'total_deductions',
        'bonus_base_amount',
        'job_title', 'work_arrangement', 'birth_date', 'appointment_date', 'residence',
        'national_id', 'mobile', 'other_phone', 'education',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'national_id', 'birth_date', 'appointment_date', 'residence', 'mobile', 'other_phone', 'education',
        'password',
        'remember_token',
        'internal_hourly_cost',
        'salary',
        'total_deductions',
        'bonus_base_amount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'appointment_date' => 'date',
            'password' => 'hashed',
            'internal_hourly_cost' => 'decimal:2',
            'salary' => 'decimal:2',
            'hourly_rate' => 'decimal:4',
            'total_deductions' => 'decimal:2',
            'skills' => 'array',
            'specialization' => 'string',
            'is_active' => 'boolean',
            'is_employee' => 'boolean',
            'work_hours_per_day' => 'float',
            'grace_days_allowed' => 'integer',
            'grace_days_used' => 'integer',
            'grace_days_reset_date' => 'date',
            'total_points' => 'integer',
        ];
    }

    /**
     * Get the avatar URL accessor.
     * Automatically converts avatar path to full URL.
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Storage::disk('public')->url($value) : '',
        );
    }

    /**
     * Scope for employees only
     */
    public function scopeEmployees($query)
    {
        return $query->where('is_employee', true);
    }

    /**
     * Scope for non-employees (client accounts)
     */
    public function scopeNonEmployees($query)
    {
        return $query->where('is_employee', false);
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function employmentContracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class, 'employee_id');
    }

    public function currentEmploymentContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class, 'employee_id')->where('status', EmploymentContract::STATUS_ACTIVE);
    }

    public function employeePartialPays(): HasMany
    {
        return $this->hasMany(EmployeePartialPay::class, 'employee_id');
    }

    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function uploadedEmployeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'uploaded_by');
    }

    public function employeeLeaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class, 'employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class, 'employee_id');
    }

    public function reviewedEmployeeLeaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class, 'reviewed_by');
    }

    public function employeeShortPermissions(): HasMany
    {
        return $this->hasMany(EmployeeShortPermission::class, 'employee_id');
    }

    public function permissionAllowances(): HasMany
    {
        return $this->hasMany(EmployeePermissionAllowance::class, 'employee_id');
    }

    public function reviewedEmployeeShortPermissions(): HasMany
    {
        return $this->hasMany(EmployeeShortPermission::class, 'reviewed_by');
    }

    public function employeeOvertimeRequests(): HasMany
    {
        return $this->hasMany(EmployeeOvertimeRequest::class, 'employee_id');
    }

    public function reviewedEmployeeOvertimeRequests(): HasMany
    {
        return $this->hasMany(EmployeeOvertimeRequest::class, 'reviewed_by');
    }

    public function employeeAdvances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class, 'employee_id');
    }

    public function approvedEmployeeAdvances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class, 'approved_by');
    }

    public function disbursedEmployeeAdvances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class, 'disbursed_by');
    }

    public function settledEmployeeAdvanceSettlements(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceSettlement::class, 'settled_by');
    }

    public function employeeLateDeductions(): HasMany
    {
        return $this->hasMany(EmployeeLateDeduction::class, 'employee_id');
    }

    /**
     * Calculate expected clock-out time based on work hours
     */
    public function getExpectedClockOutAttribute(): ?string
    {
        if (! $this->work_start_time) {
            return null;
        }

        return Carbon::parse($this->work_start_time)
            ->addMinutes((int) round(($this->work_hours_per_day ?? 8) * 60))
            ->format('H:i:s');
    }

    /**
     * Check if user has remaining grace days this month
     */
    public function hasGraceDaysRemaining(): bool
    {
        $this->resetGraceDaysIfNewMonth();

        return $this->grace_days_used < $this->grace_days_allowed;
    }

    /**
     * Use a grace day
     */
    public function useGraceDay(): bool
    {
        $this->resetGraceDaysIfNewMonth();

        if ($this->grace_days_used >= $this->grace_days_allowed) {
            return false;
        }

        $this->increment('grace_days_used');

        return true;
    }

    /**
     * Restore a grace day (when attendance is updated to not late)
     */
    public function restoreGraceDay(): bool
    {
        if ($this->grace_days_used <= 0) {
            return false;
        }

        $this->decrement('grace_days_used');

        return true;
    }

    /**
     * Reset grace days if it's a new month
     */
    public function resetGraceDaysIfNewMonth(): void
    {
        $now = Carbon::now();
        $resetDate = $this->grace_days_reset_date;

        if (! $resetDate || $resetDate->month !== $now->month || $resetDate->year !== $now->year) {
            $this->update([
                'grace_days_used' => 0,
                'grace_days_reset_date' => $now->startOfMonth(),
            ]);
        }
    }

    /**
     * Add points to user
     */
    public function addPoints(int $points): void
    {
        $this->increment('total_points', $points);
    }

    /**
     * Deduct points from user
     */
    public function deductPoints(int $points): void
    {
        $this->decrement('total_points', $points);
    }

    /**
     * Add deduction amount
     */
    public function addDeduction(float $amount): void
    {
        $this->increment('total_deductions', $amount);
    }

    /**
     * Make internal_hourly_cost visible for serialization.
     * This should only be called when the requesting user is a Super Admin.
     *
     * @return $this
     */
    public function makeInternalCostVisible(): self
    {
        return $this->makeVisible('internal_hourly_cost');
    }

    /**
     * Convert the model to an array with internal cost visible.
     * This should only be used when the requesting user is a Super Admin.
     *
     * @return array<string, mixed>
     */
    public function toArrayWithInternalCost(): array
    {
        return $this->makeVisible('internal_hourly_cost')->toArray();
    }

    /**
     * Get the client profile associated with this user (if user is a client)
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class, 'user_id');
    }

    /**
     * Get the projects where this user is a team member
     */
    public function teamProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members', 'user_id', 'project_id')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the tasks assigned to this user
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function supervisedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'supervisor_id');
    }

    public function taskHelpRequests(): HasMany
    {
        return $this->hasMany(TaskHelpRequest::class, 'requested_by');
    }

    public function acknowledgedTaskHelpRequests(): HasMany
    {
        return $this->hasMany(TaskHelpRequest::class, 'acknowledged_by');
    }

    public function resolvedTaskHelpRequests(): HasMany
    {
        return $this->hasMany(TaskHelpRequest::class, 'resolved_by');
    }

    public function taskSupervisionSessions(): HasMany
    {
        return $this->hasMany(TaskSupervisionSession::class, 'supervisor_id');
    }

    /**
     * Get the time entries for this user
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Get the attendance records for this user
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the performance evaluations for this user
     */
    public function performanceEvaluations()
    {
        return $this->hasMany(PerformanceEvaluation::class);
    }

    /**
     * Get the innovation logs for this user
     */
    public function innovationLogs()
    {
        return $this->hasMany(InnovationLog::class);
    }

    /**
     * Get the code reviews where this user is the developer
     */
    public function codeReviewsAsDeveloper()
    {
        return $this->hasMany(CodeReview::class, 'developer_id');
    }

    /**
     * Get the code reviews where this user is the reviewer
     */
    public function codeReviewsAsReviewer()
    {
        return $this->hasMany(CodeReview::class, 'reviewer_id');
    }

    /**
     * Get the point transactions for this user
     */
    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * Get the user points history for this user
     */
    public function userPoints()
    {
        return $this->hasMany(UserPoint::class);
    }

    /**
     * Get the client profile linked to this user (for portal access)
     */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /**
     * Check if user is a client with portal access
     */
    public function isClientPortalUser(): bool
    {
        return ! $this->is_employee && $this->clientProfile !== null;
    }

    /**
     * Get the leads created by this user (as sales rep)
     */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    /**
     * Get the leads where this user is the tech lead
     */
    public function techLeadLeads()
    {
        return $this->hasMany(Lead::class, 'tech_lead_id');
    }

    /**
     * Get the projects pinned by this user
     */
    public function pinnedProjects()
    {
        return $this->belongsToMany(Project::class, 'pinned_projects')
            ->withTimestamps();
    }
}
