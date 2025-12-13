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
     * Create a new AchievementController instance.
     *
     * This controller exposes read-only endpoints to retrieve achievement data.
     * It delegates all business logic to the AchievementService and optionally
     * resolves the user from a bearer token when present.
     *
     * @param AchievementService $achievements Achievement domain service.
     */
    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    /**
     * List all achievements with unlock status for the current requester.
     *
     * This endpoint is accessible without authentication middleware. If a bearer
     * token is present, it attempts to resolve a Sanctum PersonalAccessToken and
     * extract its tokenable user. If token resolution fails or the tokenable is
     * not a User instance, the requester is treated as anonymous.
     *
     * The response always returns the full achievement catalog with:
     * - per-achievement unlocked flags (only when a valid user is resolved),
     * - unlocked timestamp (only when a valid user is resolved),
     * - global unlocked percentage for each achievement.
     *
     * Response JSON:
     * - 'data' => Collection of achievement entries as returned by
     *             AchievementService::listAllForUser().
     *
     * @param Request $request Incoming HTTP request, optionally containing a bearer token.
     *
     * @return JsonResponse JSON response containing the achievements list.
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
