<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait HasCreatedUpdatedBy
{
    /**
     * Boot the trait and register model events.
     */
    protected static function bootHasCreatedUpdatedBy(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                if (Schema::hasColumn($model->getTable(), 'created_by')) {
                    $model->created_by = Auth::id();
                }

                if (Schema::hasColumn($model->getTable(), 'updated_by')) {
                    $model->updated_by = Auth::id();
                }
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && Schema::hasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who created this model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
