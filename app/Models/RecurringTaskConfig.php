<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class RecurringTaskConfig extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'template_task_id',
        'frequency',
        'interval',
        'days_of_week',
        'day_of_month',
        'start_date',
        'end_date',
        'max_occurrences',
        'occurrences_created',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'interval' => 'integer',
        'day_of_month' => 'integer',
        'max_occurrences' => 'integer',
        'occurrences_created' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Frequency constants
     */
    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_BIWEEKLY = 'biweekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_QUARTERLY = 'quarterly';
    const FREQUENCY_YEARLY = 'yearly';

    /**
     * Get the template task
     */
    public function templateTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'template_task_id');
    }

    /**
     * Get generated task instances
     */
    public function generatedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'recurring_config_id');
    }

    /**
     * Scope for active configs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if should generate new task for a given date
     */
    public function shouldGenerateForDate(Carbon $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check start date
        if ($date->lt($this->start_date)) {
            return false;
        }

        // Check end date
        if ($this->end_date && $date->gt($this->end_date)) {
            return false;
        }

        // Check max occurrences
        if ($this->max_occurrences && $this->occurrences_created >= $this->max_occurrences) {
            return false;
        }

        return $this->matchesFrequency($date);
    }

    /**
     * Check if date matches the frequency pattern
     */
    protected function matchesFrequency(Carbon $date): bool
    {
        $startDate = Carbon::parse($this->start_date);
        $interval = $this->interval ?? 1;

        return match ($this->frequency) {
            self::FREQUENCY_DAILY => $startDate->diffInDays($date) % $interval === 0,
            
            self::FREQUENCY_WEEKLY => $this->matchesWeekly($date, $startDate, $interval),
            
            self::FREQUENCY_BIWEEKLY => $this->matchesWeekly($date, $startDate, 2),
            
            self::FREQUENCY_MONTHLY => $this->matchesMonthly($date, $startDate, $interval),
            
            self::FREQUENCY_QUARTERLY => $this->matchesMonthly($date, $startDate, 3),
            
            self::FREQUENCY_YEARLY => $this->matchesYearly($date, $startDate, $interval),
            
            default => false,
        };
    }

    /**
     * Check weekly match
     */
    protected function matchesWeekly(Carbon $date, Carbon $startDate, int $interval): bool
    {
        // Check if day of week is in allowed days
        if ($this->days_of_week && !in_array($date->dayOfWeekIso, $this->days_of_week)) {
            return false;
        }

        // Check interval
        $weeksDiff = $startDate->diffInWeeks($date);
        return $weeksDiff % $interval === 0;
    }

    /**
     * Check monthly match
     */
    protected function matchesMonthly(Carbon $date, Carbon $startDate, int $interval): bool
    {
        $dayOfMonth = $this->day_of_month ?? $startDate->day;
        
        // Adjust for months with fewer days
        $lastDayOfMonth = $date->daysInMonth;
        $targetDay = min($dayOfMonth, $lastDayOfMonth);

        if ($date->day !== $targetDay) {
            return false;
        }

        $monthsDiff = $startDate->diffInMonths($date);
        return $monthsDiff % $interval === 0;
    }

    /**
     * Check yearly match
     */
    protected function matchesYearly(Carbon $date, Carbon $startDate, int $interval): bool
    {
        if ($date->month !== $startDate->month || $date->day !== $startDate->day) {
            return false;
        }

        $yearsDiff = $startDate->diffInYears($date);
        return $yearsDiff % $interval === 0;
    }

    /**
     * Generate next task instance
     */
    public function generateNextTask(): ?Task
    {
        $template = $this->templateTask;
        if (!$template) {
            return null;
        }

        $nextDate = $this->getNextOccurrenceDate();
        if (!$nextDate) {
            return null;
        }

        $task = Task::create([
            'project_id' => $template->project_id,
            'parent_id' => $template->parent_id,
            'title' => $template->title,
            'description' => $template->description,
            'status' => 'pending',
            'type' => $template->type,
            'priority' => $template->priority,
            'task_category' => $template->task_category,
            'due_date' => $nextDate,
            'start_date' => $nextDate,
            'estimated_hours' => $template->estimated_hours,
            'recurring_config_id' => $this->id,
            'template_id' => $template->template_id,
            'is_recurring_instance' => true,
            'occurrence_number' => $this->occurrences_created + 1,
        ]);

        // Copy assignees
        foreach ($template->assignees as $assignee) {
            $task->assignees()->create([
                'user_id' => $assignee->user_id,
            ]);
        }

        $this->increment('occurrences_created');

        return $task;
    }

    /**
     * Get next occurrence date
     */
    public function getNextOccurrenceDate(): ?Carbon
    {
        $lastTask = $this->generatedTasks()->latest('due_date')->first();
        $startDate = $lastTask ? Carbon::parse($lastTask->due_date) : Carbon::parse($this->start_date);
        
        // Find next valid date
        $maxIterations = 365; // Prevent infinite loop
        $iterations = 0;
        
        while ($iterations < $maxIterations) {
            $startDate->addDay();
            
            if ($this->shouldGenerateForDate($startDate)) {
                return $startDate;
            }
            
            $iterations++;
        }

        return null;
    }

    /**
     * Get frequency label
     */
    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            self::FREQUENCY_DAILY => 'Daily',
            self::FREQUENCY_WEEKLY => 'Weekly',
            self::FREQUENCY_BIWEEKLY => 'Bi-weekly',
            self::FREQUENCY_MONTHLY => 'Monthly',
            self::FREQUENCY_QUARTERLY => 'Quarterly',
            self::FREQUENCY_YEARLY => 'Yearly',
            default => 'Unknown',
        };
    }
}
