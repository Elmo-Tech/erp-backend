<?php

use Illuminate\Support\Facades\Route;

// Public operational probe only. Never expose secrets or database connection data.
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'elmo-erp-api',
    'version' => 'v2-foundation',
]));
// Protected company and accounting endpoints are intentionally NOT exposed yet.

