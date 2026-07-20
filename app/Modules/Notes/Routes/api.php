<?php

use App\Modules\Notes\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'locale'])
    ->group(function (): void {
        Route::get('notes', [NoteController::class, 'index'])
            ->middleware('permission:view_internal_notes')
            ->name('notes.index');
        Route::post('notes', [NoteController::class, 'store'])
            ->middleware('permission:create_internal_notes')
            ->name('notes.store');
    });
