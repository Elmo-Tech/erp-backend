<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BaseDictionaryModel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (BaseDictionaryModel $model): void {
            if ($model->isDirty('name') && blank($model->slug)) {
                $model->slug = Str::slug((string) $model->name);
            }
        });
    }

    public function useTable(string $table): static
    {
        $this->setTable($table);

        return $this;
    }

    public function newInstance($attributes = [], $exists = false): static
    {
        $model = parent::newInstance($attributes, $exists);
        $model->setTable($this->getTable());

        return $model;
    }
}
