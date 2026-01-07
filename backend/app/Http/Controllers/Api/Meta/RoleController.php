<?php

namespace App\Http\Controllers\Api\Meta;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * List all KC roles.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $items = Role::query()
            ->select(['id', 'code', 'label'])
            ->orderBy('label')
            ->get()
            ->map(fn (Role $r) => [
                'id' => (int) $r->id,
                'code' => $r->code,
                'label' => $r->label,
            ])
            ->values()
            ->all();

        return response()->json(['roles' => $items]);
    }
}
