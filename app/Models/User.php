<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'internal_hourly_cost',
        'skills',
        'is_active',
        'is_employee',
        'salary',
        'work_hours_per_day',
        'work_start_time',
        'grace_days_allowed',
        'grace_days_used',
        'grace_days_reset_date',
        'total_points',
        'total_deductions',
        'bonus_base_amount',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
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
            'password' => 'hashed',
            'internal_hourly_cost' => 'decimal:2',
            'salary' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'skills' => 'array',
            'is_active' => 'boolean',
            'is_employee' => 'boolean',
            'work_hours_per_day' => 'integer',
            'grace_days_allowed' => 'integer',
            'grace_days_used' => 'integer',
            'grace_days_reset_date' => 'date',
        ];
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

    /**
     * Calculate expected clock-out time based on work hours
     */
    public function getExpectedClockOutAttribute(): ?string
    {
        if (!$this->work_start_time) {
            return null;
        }
        
        return Carbon::parse($this->work_start_time)
            ->addHours($this->work_hours_per_day ?? 8)
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
     * Reset grace days if it's a new month
     */
    public function resetGraceDaysIfNewMonth(): void
    {
        $now = Carbon::now();
        $resetDate = $this->grace_days_reset_date;
        
        if (!$resetDate || $resetDate->month !== $now->month || $resetDate->year !== $now->year) {
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
     * Get the projects where this user is the client
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    /**
     * Get the tasks assigned to this user
     */
    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /**
     * Get the time entries for this user
     */
    public function timeEntries()
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
     * Get the client profile linked to this user (for portal access)
     */
    public function clientProfile()
    {
        return $this->hasOne(Client::class);
    }

    /**
     * Check if user is a client with portal access
     */
    public function isClientPortalUser(): bool
    {
        return !$this->is_employee && $this->clientProfile !== null;
    }
}
