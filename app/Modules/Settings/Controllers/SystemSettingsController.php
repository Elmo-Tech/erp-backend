<?php

namespace App\Modules\Settings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System Settings Controller
 *
 * Exposes runtime-configurable scalar settings (key/value pairs stored in the
 * `settings` table) via a thin REST interface.  Only admin-level users with the
 * `manage_payroll` permission may read or write these values.
 *
 * Endpoints
 *   GET  /api/v1/settings/system/{key}   → read a single setting
 *   PUT  /api/v1/settings/system/{key}   → update a single setting
 *
 * Currently supported keys:
 *   - gamification_point_value  (integer, default 50)
 */
class SystemSettingsController extends Controller
{
    /**
     * Whitelist of keys that may be read/written through this endpoint.
     * Prevents arbitrary key enumeration or injection.
     */
    private const ALLOWED_KEYS = [
        'gamification_point_value',
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/settings/system/{key}
    // -------------------------------------------------------------------------

    /**
     * Return the current value of a system setting.
     *
     * Requires: manage_payroll permission.
     */
    public function show(string $key): JsonResponse
    {
        abort_if(! request()->user()->can('manage_payroll'), 403, 'Unauthorized');

        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return ApiResponse::notFound("Setting key '{$key}' not found.");
        }

        $value = Setting::getValue($key);

        return ApiResponse::success(
            ['key' => $key, 'value' => $value],
            'Setting retrieved successfully'
        );
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/settings/system/{key}
    // -------------------------------------------------------------------------

    /**
     * Update the value of a system setting.
     *
     * Requires: manage_payroll permission.
     *
     * Request body:
     *   { "value": <integer> }
     */
    public function update(Request $request, string $key): JsonResponse
    {
        abort_if(! request()->user()->can('manage_payroll'), 403, 'Unauthorized');

        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return ApiResponse::notFound("Setting key '{$key}' not found.");
        }

        $validated = $request->validate([
            'value' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $setting = Setting::setValue($key, $validated['value']);

        return ApiResponse::success(
            ['key' => $setting->key, 'value' => $setting->value],
            'Setting updated successfully'
        );
    }
}
