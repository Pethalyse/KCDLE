<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

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
        $user = null;
        $plainToken = $request->bearerToken();
        if ($plainToken !== null) {
            $accessToken = PersonalAccessToken::findToken($plainToken);
            if ($accessToken !== null && $accessToken->getAttribute("tokenable") instanceof User) {
                $user = $accessToken->getAttribute("tokenable");
            }
        }

        $data = $this->achievements->listAllForUser($user);

        return response()->json([
            'data' => $data,
        ]);
    }
}
