<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    /**
     * List all games with their icon.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $items = Game::query()
            ->select(['id', 'code', 'name', 'icon_slug'])
            ->orderBy('name')
            ->get()
            ->map(fn (Game $g) => [
                'id' => (int) $g->id,
                'code' => $g->code,
                'name' => $g->name,
                'icon_slug' => $g->icon_slug,
                'logo_url' => $g->logo_url,
            ])
            ->values()
            ->all();

        return response()->json(['games' => $items]);
    }
}
