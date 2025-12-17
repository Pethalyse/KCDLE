<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpMatchLifecycleService;
use App\Services\Pvp\PvpMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Exposes PvP match network endpoints (match load, event polling, heartbeat, leave).
 *
 * This controller delegates domain and authorization checks to dedicated services.
 */
class PvpMatchController extends Controller
{
    public function __construct(
        private readonly PvpMatchService $matches,
        private readonly PvpMatchLifecycleService $lifecycle
    ) {
    }

    /**
     * Return the current match payload for the authenticated participant.
     *
     * @param PvpMatch $match   Route-bound match model.
     * @param Request  $request Current HTTP request.
     *
     * @return JsonResponse Match payload for the frontend.
     */
    public function show(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->matches->buildMatchPayload($match, (int) $user->id));
    }

    /**
     * Poll events emitted by the match after a given event id.
     *
     * @param PvpMatch $match   Route-bound match model.
     * @param Request  $request Current HTTP request.
     *
     * @return JsonResponse List of new events.
     */
    public function events(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $afterId = (int) $request->query('after_id', 0);

        return response()->json($this->matches->pollEvents($match, (int) $user->id, $afterId));
    }

    /**
     * Update the participant heartbeat for AFK detection.
     *
     * @param PvpMatch $match   Route-bound match model.
     * @param Request  $request Current HTTP request.
     *
     * @return JsonResponse Acknowledgement payload.
     */
    public function heartbeat(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->matches->heartbeat($match, (int) $user->id));
    }

    /**
     * Forfeit the match for the authenticated participant.
     *
     * This endpoint is intended for explicit player abandonment (manual leave).
     *
     * @param PvpMatch $match Route-bound match model.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse Forfeit result payload.
     * @throws Throwable
     */
    public function leave(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->lifecycle->forfeit($match, (int) $user->id, 'leave'));
    }
}
