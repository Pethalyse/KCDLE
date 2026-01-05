<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Services\Dle\GamePlayerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamePlayerController extends Controller
{
    public function __construct(private readonly GamePlayerService $players)
    {
    }

    /**
     * List players for a given game identifier.
     *
     * Supported games are strictly:
     * - 'kcdle'
     * - 'lfldle'
     * - 'lecdle'
     *
     * The endpoint supports a query parameter:
     * - active: boolean-like value (default: '1')
     *   When true, only players marked as active are returned.
     *
     * Behavior:
     * - Returns HTTP 404 with { "message": "Unknown game." } if the game is not supported.
     * - Otherwise returns the requested players as an Eloquent collection serialized to JSON.
     *
     * For each game:
     * - 'kcdle'  => KcdlePlayer models.
     * - 'lfldle' => LoldlePlayer models filtered to league.code = 'LFL'.
     * - 'lecdle' => LoldlePlayer models filtered to league.code = 'LEC'.
     *
     * Response JSON:
     * - 'game'    => string  Game identifier.
     * - 'active'  => bool    Whether the active filter was applied.
     * - 'players' => array<int, array<string, mixed>> Serialized player models.
     *
     * @param string  $game    Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request used to read the 'active' query parameter.
     *
     * @return JsonResponse JSON response containing the player list.
     */
    public function index(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json(['message' => 'Unknown game.'], 404);
        }

        $onlyActive = filter_var($request->query('active', '1'), FILTER_VALIDATE_BOOL);

        return response()->json([
            'game' => $game,
            'active' => $onlyActive,
            'players' => $this->players->listPlayers($game, $onlyActive),
        ]);
    }

    /**
     * Retrieve KCDLE players with an optional active-only filter.
     *
     * Players are ordered by their database id in ascending order.
     * When $onlyActive is true, the query filters on active = true.
     *
     * @param bool $onlyActive Whether to return only players marked as active.
     *
     * @return Collection<int, KcdlePlayer> Collection of KcdlePlayer models.
     */
    protected function getKcdlePlayers(bool $onlyActive): Collection
    {
        return KcdlePlayer::query()
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }

    /**
     * Retrieve LOL DLE players restricted to the LFL league with an optional active-only filter.
     *
     * The method filters players using the related league model:
     * - league.code must equal 'LFL'.
     *
     * Players are ordered by their database id in ascending order.
     * When $onlyActive is true, the query filters on active = true.
     *
     * @param bool $onlyActive Whether to return only players marked as active.
     *
     * @return Collection<int, LoldlePlayer> Collection of LoldlePlayer models in the LFL league.
     */
    protected function getLfldlePlayers(bool $onlyActive): Collection
    {
        return LoldlePlayer::query()
            ->whereHas('league', fn ($q) => $q->where('code', 'LFL'))
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }

    /**
     * Retrieve LOL DLE players restricted to the LEC league with an optional active-only filter.
     *
     * The method filters players using the related league model:
     * - league.code must equal 'LEC'.
     *
     * Players are ordered by their database id in ascending order.
     * When $onlyActive is true, the query filters on active = true.
     *
     * @param bool $onlyActive Whether to return only players marked as active.
     *
     * @return Collection<int, LoldlePlayer> Collection of LoldlePlayer models in the LEC league.
     */
    protected function getLecdllePlayers(bool $onlyActive): Collection
    {
        return LoldlePlayer::query()
            ->whereHas('league', fn ($q) => $q->where('code', 'LEC'))
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }
}
