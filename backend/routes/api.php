<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DailyGameController;
use App\Http\Controllers\Api\FriendGroupController;
use App\Http\Controllers\Api\GameGuessController;
use App\Http\Controllers\Api\GamePlayerController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\Meta\CountryController;
use App\Http\Controllers\Api\Meta\GameController;
use App\Http\Controllers\Api\Meta\LolRoleController;
use App\Http\Controllers\Api\Meta\RoleController;
use App\Http\Controllers\Api\Meta\TeamController;
use App\Http\Controllers\Api\PrivacyPolicyController;
use App\Http\Controllers\Api\Pvp\PvpEventController;
use App\Http\Controllers\Api\Pvp\PvpHeartbeatController;
use App\Http\Controllers\Api\Pvp\PvpLobbyController;
use App\Http\Controllers\Api\Pvp\PvpMatchController;
use App\Http\Controllers\Api\Pvp\PvpQueueController;
use App\Http\Controllers\Api\Pvp\PvpRoundController;
use App\Http\Controllers\Api\UserAchievementController;
use App\Http\Controllers\Api\UserGameStatsController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('games')
    ->group(function () {
        Route::get('{game}/daily', [DailyGameController::class, 'show']);
        Route::get('{game}/players', [GamePlayerController::class, 'index']);
        Route::post('{game}/guess', [GameGuessController::class, 'store'])
            ->middleware(['throttle:game-guess']);
    });

Route::prefix('auth')
    ->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

Route::prefix('auth')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

Route::prefix('user')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('profile', [UserProfileController::class, 'show']);
        Route::get('games/{game}/today', [GameGuessController::class, 'today']);
        Route::get('games/{game}/stats', [UserGameStatsController::class, 'show']);
        Route::get('games/{game}/history', [GameGuessController::class, 'history']);
        Route::get('games/{game}/history/{date}', [GameGuessController::class, 'historyByDate']);
        Route::get('achievements', [UserAchievementController::class, 'index']);
    });

Route::get('leaderboards/{game}', [LeaderboardController::class, 'show']);
Route::get('achievements', [AchievementController::class, 'index']);

Route::get('countries', [CountryController::class, 'index']);
Route::get('teams', [TeamController::class, 'index']);
Route::get('games', [GameController::class, 'index']);
Route::get('roles', [RoleController::class, 'index']);
Route::get('lol-roles', [LolRoleController::class, 'index']);

Route::prefix('friend-groups')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/', [FriendGroupController::class, 'index']);
        Route::post('/', [FriendGroupController::class, 'store']);
        Route::post('/join', [FriendGroupController::class, 'join']);

        Route::get('{slug}', [FriendGroupController::class, 'show']);
        Route::post('{slug}/leave', [FriendGroupController::class, 'leave']);
        Route::delete('{slug}', [FriendGroupController::class, 'destroy']);

        Route::get('{slug}/leaderboards/{game}', [FriendGroupController::class, 'leaderboard']);
    });

Route::prefix('pvp')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('games/{game}/queue/join', [PvpQueueController::class, 'join']);
        Route::post('games/{game}/queue/leave', [PvpQueueController::class, 'leave']);

        Route::get('matches/{match}', [PvpMatchController::class, 'show']);
        Route::post('matches/{match}/leave', [PvpMatchController::class, 'leave']);

        Route::get('matches/{match}/round', [PvpRoundController::class, 'show']);
        Route::post('matches/{match}/round/action', [PvpRoundController::class, 'action']);

        Route::get('matches/{match}/events', [PvpEventController::class, 'index']);
        Route::post('matches/{match}/heartbeat', [PvpHeartbeatController::class, 'store']);

        Route::get('resume', [PvpQueueController::class, 'resume']);

        Route::post('lobbies', [PvpLobbyController::class, 'store']);
        Route::get('lobbies/code/{code}', [PvpLobbyController::class, 'showByCode']);
        Route::post('lobbies/code/{code}/join', [PvpLobbyController::class, 'joinByCode']);
        Route::post('lobbies/{lobby}/start', [PvpLobbyController::class, 'start']);
        Route::get('lobbies/me', [PvpLobbyController::class, 'me']);
        Route::post('lobbies/{lobby}/leave', [PvpLobbyController::class, 'leave']);
        Route::post('lobbies/{lobby}/close', [PvpLobbyController::class, 'close']);
        Route::get('lobbies/{lobby}', [PvpLobbyController::class, 'show']);
        Route::get('lobbies/{lobby}/events', [PvpLobbyController::class, 'events']);
    });
Route::prefix('pvp')
    ->group(function () {
        Route::get('pvp/lobbies/code/{code}/peek', [PvpLobbyController::class, 'peekByCode']);
    });

Route::get('/credits', [CreditController::class, 'show']);
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show']);
Route::get('/legal', [LegalController::class, 'show']);

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $db = true;
    } catch (\Throwable) {
        $db = false;
    }

    return response()->json([
        'app' => true,
        'db'  => $db,
    ], $db ? 200 : 500);
});
