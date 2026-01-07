<?php

namespace App\Http\Controllers\Api\Pvp;

use App\Http\Controllers\Controller;
use App\Models\PvpLobby;
use App\Services\Pvp\PvpLobbyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PvpLobbyController extends Controller
{
    public function __construct(
        private readonly PvpLobbyService $lobbies
    ) {}

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $lobby = $this->lobbies->findMyOpenLobby($user);

        if (! $lobby) {
            return response()->json([
                'status' => 'none',
            ]);
        }

        return response()->json([
            'status' => 'in_lobby',
            'lobby' => $this->lobbies->buildLobbyPayload($lobby, (int) $user->id),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $game = (string) $request->input('game', '');
        $bestOf = (int) $request->input('best_of', (int) config('pvp.default_best_of'));

        $lobby = $this->lobbies->createLobby($user, $game, $bestOf);

        return $this->getLobbyJson($lobby, (int) $user->id);
    }

    /**
     * @param string $code
     * @param Request $request
     * @return JsonResponse
     */
    public function showByCode(string $code, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $lobby = $this->lobbies->getByCode($code);

        return $this->getLobbyJson($lobby, (int) $user->id);
    }

    /**
     * @param string $code
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function joinByCode(string $code, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $lobby = $this->lobbies->joinLobby($user, $code);

        return $this->getLobbyJson($lobby, (int) $user->id);
    }

    /**
     * @param PvpLobby $lobby
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function leave(PvpLobby $lobby, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $lobby = $this->lobbies->leaveLobby($user, $lobby);

        return response()->json($lobby);
    }

    /**
     * @param PvpLobby $lobby
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function close(PvpLobby $lobby, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $lobby = $this->lobbies->closeLobby($user, $lobby);

        return response()->json($lobby);
    }

    /**
     * @param PvpLobby $lobby
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function start(PvpLobby $lobby, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $result = $this->lobbies->startLobby($user, $lobby);

        return response()->json($result);
    }

    /**
     * @param PvpLobby $lobby
     * @param Request $request
     * @return JsonResponse
     */
    public function show(PvpLobby $lobby, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return response()->json($this->lobbies->buildLobbyPayload($lobby, (int) $user->id));
    }

    /**
     * @param PvpLobby $lobby
     * @param Request $request
     * @return JsonResponse
     */
    public function events(PvpLobby $lobby, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $this->lobbies->assertParticipant($lobby, (int) $user->id);

        $afterId = (int) $request->query('after_id', 0);
        $limit = (int) $request->query('limit', 50);

        return response()->json([
            'events' => $this->lobbies->listEvents($lobby, $afterId, $limit),
        ]);
    }

    /**
     * @param string $code
     * @return JsonResponse
     */
    public function peekByCode(string $code): JsonResponse
    {
        $lobby = $this->lobbies->getByCode($code);

        if ($lobby->status !== 'open') {
            abort(404);
        }

        $lobby->loadMissing(['host:id,name']);

        return response()->json([
            'code' => $lobby->code,
            'game' => $lobby->game,
            'best_of' => (int) $lobby->best_of,
            'status' => $lobby->status,
            'host' => [
                'name' => (string) ($lobby->host?->name ?? ''),
            ],
            'created_at' => $lobby->created_at?->toISOString(),
        ]);
    }

    private function getLobbyJson(PvpLobby $lobby, int $userId): JsonResponse
    {
        return response()->json($this->lobbies->buildLobbyPayload($lobby, $userId));
    }
}
