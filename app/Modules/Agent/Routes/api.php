<?php

use App\Modules\Agent\Controllers\AgentActionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/agent')
    ->middleware(['auth:sanctum', 'locale', 'permission:use_ai_agent'])
    ->group(function () {
        Route::post('parse', [AgentActionController::class, 'parse'])->name('agent.parse');
        Route::post('actions/preview', [AgentActionController::class, 'preview'])->name('agent.actions.preview');
        Route::post('actions/execute', [AgentActionController::class, 'execute'])->name('agent.actions.execute');
        Route::get('actions/logs', [AgentActionController::class, 'logs'])->name('agent.actions.logs');
    });
