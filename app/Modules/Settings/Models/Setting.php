<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Generic key-value settings store.
 *
 * Unlike the dictionary tables (BaseDictionaryModel) which hold enumerable
 * lists, this model stores singleton scalar configuration values that must
 * be controllable at runtime without a code deploy.
 *
 * Usage:
 *   Setting::getValue('gamification_point_value', 50)  // returns int|string
 *   Setting::setValue('gamification_point_value', 75)
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string|null $description
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    // Cache TTL in seconds (1 hour). Keeps DB reads minimal for hot paths
    // like payroll calculation that call getValue() in a tight loop.
    private const CACHE_TTL = 3600;

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve a setting value by key, with an optional default.
     *
     * Results are cached to avoid repeated DB hits during batch operations
     * (e.g., generating payslips for all employees in one command run).
     *
     * @param  mixed  $default  Returned when the key does not exist in DB.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";

        $raw = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            return static::where('key', $key)->value('value');
        });

        return $raw ?? $default;
    }

    /**
     * Persist a setting value and bust its cache entry.
     */
    public static function setValue(string $key, mixed $value): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );

        Cache::forget("setting:{$key}");

        return $setting;
    }
}
