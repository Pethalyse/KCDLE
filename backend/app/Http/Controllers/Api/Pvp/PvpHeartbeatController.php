<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpHeartbeatService;
use App\Services\Pvp\PvpMatchEngineService;
use App\Services\Pvp\PvpMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Exposes a heartbeat endpoint for PvP match participants.
 *
 * Clients send periodic heartbeats while a match view is open to enable AFK detection.
 */
class PvpHeartbeatController extends Controller
{
    public function __construct(
        private readonly PvpHeartbeatService $heartbeat,
        private readonly PvpMatchService $matches,
        private readonly PvpMatchEngineService $engine,
    ) {
    }

    /**
     * Record a heartbeat for the authenticated participant.
     *
     * @param PvpMatch $match Route-bound match model.
     * @param Request $request Current HTTP request.
     *
     * @return JsonResponse Heartbeat acknowledgement.
     * @throws Throwable
     */
    public function store(PvpMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $uid = (int) $user->id;

        $this->matches->assertParticipant($match->id, $uid);
        $state = $this->engine->buildMatchPayload($match, $uid);
        $heartbeat = $this->heartbeat->heartbeat($match, $uid);

        if (! $request->boolean('include_state', false)) {
            return response()->json($heartbeat);
        }

        return response()->json([
            'heartbeat' => $heartbeat,
            'state' => $state,
        ]);
    }
}
