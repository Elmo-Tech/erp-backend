<?php

namespace App\Modules\Project\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_default
 * @property int|null $created_by
 * @property-read Collection<int, WorkflowTemplateStage> $stages
 */
class WorkflowTemplate extends Model
{
    protected $fillable = ['name', 'is_default', 'created_by'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return HasMany<WorkflowTemplateStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowTemplateStage::class)->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
