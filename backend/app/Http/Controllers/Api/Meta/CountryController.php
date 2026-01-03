<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * List all countries with their flag.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $items = Country::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Country $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'flag_url' => $c->flag_url,
            ])
            ->values()
            ->all();

        return response()->json(['countries' => $items]);
    }
}
