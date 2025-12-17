<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpMatchEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Exposes PvP round endpoints for match participants.
 *
 * This controller is transport-only and delegates round orchestration to the engine.
 */
class PvpRoundController extends Controller
{
    public function __construct(private readonly PvpMatchEngineService $engine)
    {
    }

    /**
     * Return the current round public state for the authenticated participant.
     *
     * @param PvpMatch $match   Route-bound match model.
     * @param Request  $request Current HTTP request.
     *
     * @return JsonResponse Current round state payload.
     */
    public function show(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->engine->currentRoundState($match, (int) $user->id));
    }

    /**
     * Submit an action for the current round for the authenticated participant.
     *
     * @param PvpMatch $match Route-bound match model.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse Action result payload.
     * @throws Throwable
     */
    public function action(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $action = (array) $request->input('action', []);

        return response()->json($this->engine->handleRoundAction($match, (int) $user->id, $action));
    }
}
