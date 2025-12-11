<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FriendGroup;
use App\Models\User;
use App\Services\UserLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FriendGroupController extends Controller
{
    protected UserLeaderboardService $leaderboard;

    /**
     * @param UserLeaderboardService $leaderboard
     */
    public function __construct(UserLeaderboardService $leaderboard)
    {
        $this->leaderboard = $leaderboard;
    }

    /**
     * List friend groups for the authenticated user.
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

        $groups = $user->friendGroups()
            ->with('owner:id,name')
            ->get()
            ->map(function (FriendGroup $group) {
                return [
                    'id'        => $group->getAttribute('id'),
                    'name'      => $group->getAttribute('name'),
                    'slug'      => $group->getAttribute('slug'),
                    'join_code' => $group->getAttribute('join_code'),
                    'role'      => $group->getRelationValue('pivot')->role ?? null,
                    'owner'     => [
                        'id'   => $group->getRelationValue('owner')?->getAttribute('id'),
                        'name' => $group->getRelationValue('owner')?->getAttribute('name'),
                    ],
                ];
            })
            ->values();

        return response()->json([
            'groups' => $groups,
        ]);
    }

    /**
     * Create a new friend group for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
        ]);

        $slugBase = Str::slug($data['name']);
        $slug = $slugBase;
        $suffix = 1;

        while (FriendGroup::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        $joinCode = Str::upper(Str::random(8));
        while (FriendGroup::query()->where('join_code', $joinCode)->exists()) {
            $joinCode = Str::upper(Str::random(8));
        }

        /** @var FriendGroup|null $group */
        $group = null;

        DB::transaction(function () use ($user, $data, $slug, $joinCode, &$group) {
            $group = FriendGroup::query()->create([
                'owner_id'  => $user->getAttribute('id'),
                'name'      => $data['name'],
                'slug'      => $slug,
                'join_code' => $joinCode,
            ]);

            $group->users()->attach($user->getAttribute('id'), [
                'role' => 'owner',
            ]);
        });

        return response()->json([
            'group' => [
                'id'        => $group->getAttribute('id'),
                'name'      => $group->getAttribute('name'),
                'slug'      => $group->getAttribute('slug'),
                'join_code' => $group->getAttribute('join_code'),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a friend group detail (only for members).
     *
     * @param string $slug
     * @param Request $request
     * @return JsonResponse
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $group = FriendGroup::query()
            ->with([
                'owner:id,name',
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email');
                },
            ])
            ->where('slug', $slug)
            ->first();

        if (!$group) {
            return response()->json([
                'message' => 'Group not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $isMember = $group->users()
            ->where('users.id', $user->getAttribute('id'))
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        $members = $group->users->map(function (User $member) {
            return [
                'id'    => $member->getAttribute('id'),
                'name'  => $member->getAttribute('name'),
                'email' => $member->getAttribute('email'),
                'role'  => $member->pivot?->role,
            ];
        })->values();

        return response()->json([
            'group' => [
                'id'        => $group->getAttribute('id'),
                'name'      => $group->getAttribute('name'),
                'slug'      => $group->getAttribute('slug'),
                'join_code' => $group->getAttribute('join_code'),
                'owner_id'  => $group->getAttribute('owner_id'),
            ],
            'members' => $members,
        ]);
    }

    /**
     * Join a friend group using its join code.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->validate([
            'join_code' => ['required', 'string'],
        ]);

        $group = FriendGroup::query()
            ->where('join_code', strtoupper($data['join_code']))
            ->first();

        if (!$group) {
            return response()->json([
                'message' => 'Invalid join code.',
            ], Response::HTTP_NOT_FOUND);
        }

        $alreadyMember = $group->users()
            ->where('users.id', $user->getAttribute('id'))
            ->exists();

        if ($alreadyMember) {
            return response()->json([
                'message' => 'Already a member of this group.',
            ], Response::HTTP_OK);
        }

        $group->users()->attach($user->getAttribute('id'), [
            'role' => 'member',
        ]);

        return response()->json([
            'group' => [
                'id'        => $group->getAttribute('id'),
                'name'      => $group->getAttribute('name'),
                'slug'      => $group->getAttribute('slug'),
                'join_code' => $group->getAttribute('join_code'),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Leave a friend group.
     * If the user is the owner:
     *   - if there is another member, ownership is transferred to the oldest member
     *   - otherwise the group is deleted
     *
     * @param string $slug
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function leave(string $slug, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $group = FriendGroup::query()
            ->where('slug', $slug)
            ->first();

        if (!$group) {
            return response()->json([
                'message' => 'Group not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $membership = $group->users()
            ->where('users.id', $user->getAttribute('id'))
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'Not a member of this group.',
            ], Response::HTTP_FORBIDDEN);
        }

        $role = $membership->pivot?->role ?? 'member';

        if ($role !== 'owner') {
            $group->users()->detach($user->getAttribute('id'));

            return response()->json([
                'message' => 'You left the group.',
            ], Response::HTTP_OK);
        }

        $nextOwner = $group->users()
            ->where('users.id', '!=', $user->getAttribute('id'))
            ->orderBy('friend_group_users.created_at')
            ->first();

        if (!$nextOwner) {
            $group->delete();

            return response()->json([
                'message' => 'Group deleted because you were the last member.',
            ], Response::HTTP_OK);
        }

        DB::transaction(function () use ($group, $user, $nextOwner) {
            $group->setAttribute('owner_id', $nextOwner->getAttribute('id'));
            $group->save();

            $group->users()->updateExistingPivot($nextOwner->getAttribute('id'), [
                'role' => 'owner',
            ]);

            $group->users()->detach($user->getAttribute('id'));
        });

        return response()->json([
            'message' => 'You left the group. Ownership has been transferred to another member.',
            'group'   => [
                'id'       => $group->getAttribute('id'),
                'name'     => $group->getAttribute('name'),
                'slug'     => $group->getAttribute('slug'),
                'owner_id' => $group->getAttribute('owner_id'),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Delete a friend group (only owner).
     *
     * @param string $slug
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(string $slug, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $group = FriendGroup::query()
            ->where('slug', $slug)
            ->first();

        if (!$group) {
            return response()->json([
                'message' => 'Group not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ((int) $group->getAttribute('owner_id') !== (int) $user->getAttribute('id')) {
            return response()->json([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        $group->delete();

        return response()->json([
            'message' => 'Group deleted.',
        ], Response::HTTP_OK);
    }

    /**
     * Get the leaderboard for a given game within a friend group.
     *
     * @param string $slug
     * @param string $game
     * @param Request $request
     * @return JsonResponse
     */
    public function leaderboard(string $slug, string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json([
                'message' => 'Unknown game.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $group = FriendGroup::query()
            ->where('slug', $slug)
            ->first();

        if (!$group) {
            return response()->json([
                'message' => 'Group not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $isMember = $group->users()
            ->where('users.id', $user->getAttribute('id'))
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        $perPage = (int) $request->query('per_page', 50);
        if ($perPage <= 0) {
            $perPage = 50;
        }

        $page = (int) $request->query('page', 1);
        if ($page <= 0) {
            $page = 1;
        }

        $paginator = $this->leaderboard->getGroupLeaderboard($game, $group, $perPage, $page);

        $data = [];
        $rankOffset = ($paginator->currentPage() - 1) * $paginator->perPage();
        $index = 0;

        foreach ($paginator->items() as $row) {
            $data[] = [
                'rank'            => $rankOffset + (++$index),
                'user'            => $row['user'],
                'wins'            => $row['wins'],
                'average_guesses' => $row['average_guesses'],
                'base_score'      => $row['base_score'],
                'weight'          => $row['weight'],
                'final_score'     => $row['final_score'],
            ];
        }

        return response()->json([
            'group' => [
                'id'   => $group->getAttribute('id'),
                'name' => $group->getAttribute('name'),
                'slug' => $group->getAttribute('slug'),
            ],
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
