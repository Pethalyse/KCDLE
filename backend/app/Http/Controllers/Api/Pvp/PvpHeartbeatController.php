<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpMatch;
use App\Services\Pvp\PvpHeartbeatService;
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
        private readonly PvpMatchService $matches
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

        $heartbeat = $this->heartbeat->heartbeat($match, $uid);

        if (! $request->boolean('include_state', false)) {
            return response()->json($heartbeat);
        }

        return response()->json([
            'heartbeat' => $heartbeat,
            'state' => $this->buildMinimalState($match, $uid),
        ]);
    }

    /**
     * Build a lightweight state payload suitable for frequent polling endpoints.
     *
     * @param PvpMatch $match Match instance.
     * @param int $userId Authenticated participant id.
     *
     * @return array{id:int, match_id:int, status:string, best_of:int, current_round:int}
     */
    private function buildMinimalState(PvpMatch $match, int $userId): array
    {
        $this->matches->assertParticipant($match->id, $userId);

        return [
            'id' => (int) $match->id,
            'match_id' => (int) $match->id,
            'status' => (string) $match->status,
            'best_of' => (int) $match->best_of,
            'current_round' => (int) $match->current_round,
        ];
    }
}
