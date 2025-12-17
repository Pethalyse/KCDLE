<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpMatchEngineService;
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
        private readonly PvpMatchLifecycleService $lifecycle,
        private readonly PvpMatchEngineService $engine
    ) {
    }

    /**
     * Return the current match payload for the authenticated participant.
     *
     * @param PvpMatch $match Route-bound match model.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse Match payload for the frontend.
     * @throws Throwable
     */
    public function show(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->engine->buildMatchPayload($match, (int) $user->id));
    }

    /**
     * Poll events emitted by the match after a given event id.
     *
     * Optional:
     * - include_state=1 -> also returns the canonical match payload (engine buildMatchPayload).
     *
     * @param PvpMatch $match Route-bound match model.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse List of new events or {events,state}.
     * @throws Throwable
     */
    public function events(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $uid = (int) $user->id;
        $afterId = (int) $request->query('after_id', 0);

        $events = $this->matches->pollEvents($match, $uid, $afterId);

        $includeState = $request->boolean('include_state', false);
        if (! $includeState) {
            return response()->json($events);
        }

        return response()->json([
            'events' => $events,
            'state' => $this->engine->buildMatchPayload($match, $uid),
        ]);
    }

    /**
     * Update the participant heartbeat for AFK detection.
     *
     * Optional:
     * - include_state=1 -> also returns the canonical match payload (engine buildMatchPayload).
     *
     * @param PvpMatch $match Route-bound match model.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse Acknowledgement payload or {heartbeat,state}.
     * @throws Throwable
     */
    public function heartbeat(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $uid = (int) $user->id;

        $heartbeat = $this->matches->heartbeat($match, $uid);

        $includeState = $request->boolean('include_state', false);
        if (! $includeState) {
            return response()->json($heartbeat);
        }

        return response()->json([
            'heartbeat' => $heartbeat,
            'state' => $this->engine->buildMatchPayload($match, $uid),
        ]);
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
