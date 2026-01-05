<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'reviewer_id',
        'developer_id',
        'code_quality_score',
        'readability_score',
        'scalability_score',
        'testing_score',
        'documentation_score',
        'average_score',
        'review_notes',
        'improvement_suggestions',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'code_quality_score' => 'integer',
            'readability_score' => 'integer',
            'scalability_score' => 'integer',
            'testing_score' => 'integer',
            'documentation_score' => 'integer',
            'average_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    public function calculateAverageScore(): void
    {
        $scores = [
            $this->code_quality_score,
            $this->readability_score,
            $this->scalability_score,
            $this->testing_score,
            $this->documentation_score,
        ];

        $validScores = array_filter($scores, fn($s) => $s > 0);
        
        if (count($validScores) > 0) {
            $this->average_score = array_sum($validScores) / count($validScores);
        } else {
            $this->average_score = 0;
        }
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeNeedsRevision($query)
    {
        return $query->where('status', 'needs_revision');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function needsRevision(): bool
    {
        return $this->status === 'needs_revision';
    }
}
