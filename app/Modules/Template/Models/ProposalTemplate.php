<?php

namespace App\Modules\Template\Models;

use App\Enums\TemplateType;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalTemplate extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'name',
        'type',
        'content',
        'placeholders',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TemplateType::class,
            'placeholders' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(\App\Models\Quotation::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
