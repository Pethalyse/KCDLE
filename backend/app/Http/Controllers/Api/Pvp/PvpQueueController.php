<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Services\Pvp\PvpMatchmakingService;
use App\Services\Pvp\PvpMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Exposes PvP queue endpoints (join/leave) and resume helper for authenticated users.
 *
 * This controller remains transport-only and delegates business logic to services.
 */
class PvpQueueController extends Controller
{
    public function __construct(
        private readonly PvpMatchmakingService $matchmaking,
        private readonly PvpMatchService $matches
    ) {
    }

    /**
     * Join the PvP queue for a specific game and best-of format and attempt immediate matchmaking.
     *
     * @param string $game Game identifier (kcdle, lecdle, lfldle).
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse JSON payload containing matchmaking result.
     * @throws Throwable
     */
    public function join(string $game, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $bestOf = (int) $request->input('best_of', (int) config('pvp.default_best_of'));

        return response()->json($this->matchmaking->joinQueue($user, $game, $bestOf));
    }

    /**
     * Leave the PvP queue for a specific game.
     *
     * @param string  $game    Game identifier.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse JSON payload confirming removal from queue.
     */
    public function leave(string $game, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $this->matchmaking->leaveQueue($user, $game);

        return response()->json(['status' => 'left']);
    }

    /**
     * Check whether the authenticated user has an active PvP match to resume.
     *
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse Resume payload.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->matches->buildResumePayload((int) $user->id));
    }
}
