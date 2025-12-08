<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserAchievementController extends Controller
{
    /**
     * List unlocked achievements for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $achievements = $user->achievements()
            ->orderBy('user_achievements.unlocked_at', 'desc')
            ->get()
            ->map(function (Achievement $achievement) {
                return [
                    'id' => $achievement->getAttribute('id'),
                    'key' => $achievement->getAttribute('key'),
                    'name' => $achievement->getAttribute('name'),
                    'description' => $achievement->getAttribute('description'),
                    'game' => $achievement->getAttribute('game'),
                    'unlocked_at' => $achievement->pivot->unlocked_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $achievements,
        ]);
    }
}
