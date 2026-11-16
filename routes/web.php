<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'Elmo Tech ERP V2 API',
    'status' => 'foundation',
]));

