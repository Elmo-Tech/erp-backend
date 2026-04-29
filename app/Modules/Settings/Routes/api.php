<?php

use App\Modules\Settings\Controllers\DictionaryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'locale'])
    ->group(function (): void {
        Route::controller(DictionaryController::class)
            ->prefix('settings/{entity}')
            ->name('settings.dictionary.')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->name('update');
                Route::patch('/{id}', 'update')->name('patch');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });
    });
