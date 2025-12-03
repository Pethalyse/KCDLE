<?php

use App\Http\Controllers\Api\DailyGameController;
use App\Http\Controllers\Api\GameGuessController;
use App\Http\Controllers\Api\GamePlayerController;
use Illuminate\Support\Facades\Route;

Route::prefix('daily')->group(function () {
    Route::get('{game}', [DailyGameController::class, 'show']);
//    Route::get('{game}/history', [DailyGameController::class, 'history']);
});

Route::get('/players/{game}', [GamePlayerController::class, 'index']);

Route::post('/games/{game}/guess', [GameGuessController::class, 'store'])
    ->middleware('throttle:game-guess');
