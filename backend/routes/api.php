<?php

use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DailyGameController;
use App\Http\Controllers\Api\GameGuessController;
use App\Http\Controllers\Api\GamePlayerController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\PrivacyPolicyController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('daily')->group(function () {
    Route::get('{game}', [DailyGameController::class, 'show']);
//    Route::get('{game}/history', [DailyGameController::class, 'history']);
});

Route::get('/players/{game}', [GamePlayerController::class, 'index']);

Route::post('/games/{game}/guess', [GameGuessController::class, 'store'])
    ->middleware('throttle:game-guess');

Route::get('/credits', [CreditController::class, 'index']);
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show']);
Route::get('/legal', [LegalController::class, 'show']);
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $db = true;
    } catch (Throwable) {
        $db = false;
    }

    return response()->json([
        'app' => true,
        'db' => $db,
    ], $db ? 200 : 500);
});
