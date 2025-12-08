<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    protected AchievementService $achievements;

    /**
     * @param AchievementService $achievements
     */
    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    /**
     * List all achievements with user status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            $user = null;
        }

        $data = $this->achievements->listAllForUser($user);

        return response()->json([
            'data' => $data,
        ]);
    }
}
