<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LolRoleController extends Controller
{
    /**
     * List LoL roles (static enum) with their icons.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $roles = (array) config('pvp.whois.lol_roles', []);
        $items = [];

        foreach ($roles as $role) {
            $code = strtoupper(trim((string) $role));
            if ($code === '') {
                continue;
            }

            $path = "storage/roles/{$code}.png";
            $url = file_exists(public_path($path)) ? asset($path) : null;

            $items[] = [
                'code' => $code,
                'label' => $code,
                'icon_url' => $url,
            ];
        }

        return response()->json(['lol_roles' => $items]);
    }
}
