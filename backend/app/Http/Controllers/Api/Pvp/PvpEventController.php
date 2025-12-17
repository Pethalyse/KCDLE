<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpEventQueryService;
use App\Services\Pvp\PvpMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        private readonly PvpMatchService $matches
    ) {
    }

    /**
     * Fetch match events after a given cursor for the authenticated participant.
     *
     * Query params:
     * - after_id: int (default 0)
     * - limit: int (default 50, max 200)
     *
     * @param PvpMatch $match   Route-bound match model.
     * @param Request  $request Current HTTP request.
     *
     * @return JsonResponse JSON response containing events and cursor.
     */
    public function index(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $this->matches->assertParticipant($match->id, (int) $user->id);

        $afterId = (int) $request->query('after_id', 0);
        $limit = (int) $request->query('limit', 50);

        return response()->json($this->events->fetchAfter((int) $match->id, $afterId, $limit));
    }
}
