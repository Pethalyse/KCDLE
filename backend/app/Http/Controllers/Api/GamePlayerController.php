<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamePlayerController extends Controller
{
    public function index(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json([
                'message' => 'Unknown game.',
            ], 404);
        }

        $onlyActive = filter_var($request->query('active', '1'), FILTER_VALIDATE_BOOL);

        $players = match ($game) {
            'kcdle'  => $this->getKcdlePlayers($onlyActive),
            'lfldle' => $this->getLfldlePlayers($onlyActive),
            'lecdle' => $this->getLecdllePlayers($onlyActive),
        };

        return response()->json([
            'game'    => $game,
            'active'  => $onlyActive,
            'players' => $players,
        ]);
    }

    protected function getKcdlePlayers(bool $onlyActive): Collection
    {
        return KcdlePlayer::query()
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }

    protected function getLfldlePlayers(bool $onlyActive): Collection
    {
        return LoldlePlayer::query()
            ->whereHas('league', fn ($q) => $q->where('code', 'LFL'))
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }

    protected function getLecdllePlayers(bool $onlyActive): Collection
    {
        return LoldlePlayer::query()
            ->whereHas('league', fn ($q) => $q->where('code', 'LEC'))
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }
}
