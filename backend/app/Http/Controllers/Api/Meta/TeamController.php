<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    /**
     * List all teams with their logo.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $items = Team::query()
            ->select(['id', 'slug', 'display_name', 'short_name', 'country_code'])
            ->orderBy('display_name')
            ->get()
            ->map(fn (Team $t) => [
                'id' => (int) $t->id,
                'slug' => $t->slug,
                'display_name' => $t->display_name,
                'short_name' => $t->short_name,
                'country_code' => $t->country_code,
                'logo_url' => $t->logo_url,
            ])
            ->values()
            ->all();

        return response()->json(['teams' => $items]);
    }
}
