<?php

use App\Modules\Notes\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'locale', 'permission:view_internal_notes'])
    ->group(function (): void {
        Route::get('notes', [NoteController::class, 'index'])->name('notes.index');
        Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
    });
