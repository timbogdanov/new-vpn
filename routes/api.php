<?php

use App\Http\Controllers\MiniApp\BootstrapController;
use App\Http\Controllers\MiniApp\ProfileController;
use App\Http\Controllers\MiniApp\ServerController;
use App\Http\Controllers\MiniApp\ToolsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/miniapp')
    ->middleware(['telegram.initdata'])
    ->group(function () {
        Route::get('/bootstrap', BootstrapController::class);

        Route::get('/servers', [ServerController::class, 'index']);
        Route::post('/servers/{slug}/connect', [ServerController::class, 'connect']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::post('/tools/ip-check', [ToolsController::class, 'ipCheck']);
        Route::post('/tools/speed-test', [ToolsController::class, 'speedTest']);
    });
