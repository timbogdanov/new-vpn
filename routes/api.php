<?php

use App\Http\Controllers\MiniApp\AnnouncementsController;
use App\Http\Controllers\MiniApp\BillingController;
use App\Http\Controllers\MiniApp\BootstrapController;
use App\Http\Controllers\MiniApp\MyDataController;
use App\Http\Controllers\MiniApp\OoniContributeController;
use App\Http\Controllers\MiniApp\OoniSearchController;
use App\Http\Controllers\MiniApp\OoniUrlDetailsController;
use App\Http\Controllers\MiniApp\OoniWatchlistController;
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
        Route::get('/tools/ooni-summary', [ToolsController::class, 'ooniSummary']);

        Route::get('/ooni/watchlist', [OoniWatchlistController::class, 'show']);
        Route::put('/ooni/watchlist', [OoniWatchlistController::class, 'update']);
        Route::post('/ooni/contribute', [OoniContributeController::class, 'store'])->middleware('throttle:60,1');

        Route::get('/ooni/search', [OoniSearchController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/ooni/url-details', [OoniUrlDetailsController::class, 'show'])->middleware('throttle:30,1');

        Route::get('/ooni/my-data', [MyDataController::class, 'index']);
        Route::delete('/ooni/my-data', [MyDataController::class, 'destroy']);
        Route::get('/ooni/my-data/export', [MyDataController::class, 'export']);

        Route::get('/billing/plans', [BillingController::class, 'plans']);
        Route::post('/billing/invoice', [BillingController::class, 'invoice'])->middleware('throttle:20,1');
        Route::get('/billing/history', [BillingController::class, 'history']);

        Route::get('/announcements/active', [AnnouncementsController::class, 'active']);
    });
