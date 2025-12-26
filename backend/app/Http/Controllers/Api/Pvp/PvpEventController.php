<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpEventQueryService;
use App\Services\Pvp\PvpMatchEngineService;
use App\Services\Pvp\PvpMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Exposes match event polling endpoints for PvP participants.
 *
 * Clients poll this endpoint with an after_id cursor to receive incremental updates
 * (opponent guesses, round transitions, match finished) without reloading the round state.
 */
class PvpEventController extends Controller
{
    public function __construct(
        private readonly PvpEventQueryService $events,
        private readonly PvpMatchService $matches,
        private readonly PvpMatchEngineService $engine,
    ) {
    }

    /**
     * Fetch match events after a given cursor for the authenticated participant.
     *
     * Query params:
     * - after_id: int (default 0)
     * - limit: int (default 50, max 200)
     * - include_state: bool (default false)
     *
     * @param PvpMatch $match   Route-bound match model.
     * @param Request  $request Current HTTP request.
     *
     * @return JsonResponse JSON response containing events and cursor.
     * @throws Throwable
     */
    public function index(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $uid = (int) $user->id;

        $this->matches->assertParticipant($match->id, $uid);

        $afterId = (int) $request->query('after_id', 0);
        $limit = (int) $request->query('limit', 50);

        $state = $this->engine->buildMatchPayload($match, $uid);
        $events = $this->events->fetchAfter((int) $match->id, $afterId, $limit);

        if (! $request->boolean('include_state', false)) {
            return response()->json($events);
        }

        return response()->json([
            'events' => $events['events'] ?? $events,
            'last_id' => $events['last_id'] ?? $afterId,
            'state' => $state,
        ]);
    }
}
