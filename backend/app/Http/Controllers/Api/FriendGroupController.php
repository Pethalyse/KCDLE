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
     * Create a new FriendGroupController instance.
     *
     * This controller exposes endpoints to manage friend groups and memberships
     * (list, create, join, leave, delete) and to retrieve a group-scoped leaderboard.
     * All leaderboard calculations are delegated to the UserLeaderboardService.
     *
     * @param UserLeaderboardService $leaderboard Service used to build group leaderboards.
     */
    public function __construct(UserLeaderboardService $leaderboard)
    {
        $this->leaderboard = $leaderboard;
    }

    /**
     * List friend groups for the authenticated user.
     *
     * This endpoint requires an authenticated user resolved from the request.
     * It returns all groups the user belongs to, including:
     * - the group's basic attributes (id, name, slug, join_code),
     * - the user's role within the group (from the pivot table),
     * - the group owner's minimal identity (id, name).
     *
     * On unauthenticated requests, it returns HTTP 401 with { "message": "Unauthenticated." }.
     *
     * Response JSON:
     * - 'groups' => array<int, array{
     *     id:int,
     *     name:string,
     *     slug:string,
     *     join_code:string,
     *     role:string|null,
     *     owner:array{id:int|null, name:string|null}
     * }>
     *
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response containing the user's groups.
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
     * Create a new friend group owned by the authenticated user.
     *
     * This endpoint requires authentication.
     *
     * Steps performed:
     * - Validates the payload:
     *   - name: required string, max 20 chars.
     * - Generates a unique slug based on Str::slug(name), adding a numeric suffix
     *   until it is unique in the friend_groups table.
     * - Generates a unique join code:
     *   - 8 characters, uppercase, random,
     *   - re-generated until unique in the friend_groups table.
     * - Creates the FriendGroup and attaches the creator in the pivot table
     *   with role = 'owner' inside a database transaction.
     *
     * Response:
     * - HTTP 201 Created
     * - 'group' => array{id:int, name:string, slug:string, join_code:string}
     *
     * On unauthenticated requests:
     * - HTTP 401 with { "message": "Unauthenticated." }.
     *
     * @param Request $request HTTP request containing the group name.
     *
     * @return JsonResponse JSON response containing the created group.
     *
     * @throws Throwable If the transaction fails or an unexpected error occurs.
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
     * Show details of a friend group (members-only).
     *
     * This endpoint requires authentication and membership in the target group.
     *
     * Steps performed:
     * - Loads the group by slug with:
     *   - owner relation (id, name),
     *   - users relation (id, name, email) including the pivot role.
     * - Returns 404 if the group does not exist.
     * - Checks if the authenticated user is a member of the group.
     * - Returns 403 if the user is not a member.
     * - Returns:
     *   - group metadata (id, name, slug, join_code, owner_id),
     *   - members list with each member's id, name, email and pivot role.
     *
     * Response JSON:
     * - 'group' => array{
     *     id:int,
     *     name:string,
     *     slug:string,
     *     join_code:string,
     *     owner_id:int
     * }
     * - 'members' => array<int, array{
     *     id:int,
     *     name:string,
     *     email:string|null,
     *     role:string|null
     * }>
     *
     * Error responses:
     * - 401 { "message": "Unauthenticated." }
     * - 404 { "message": "Group not found." }
     * - 403 { "message": "Forbidden." }
     *
     * @param string  $slug    Group slug identifier.
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response containing group details and members.
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
     * Join a friend group using a join code.
     *
     * This endpoint requires authentication.
     *
     * Steps performed:
     * - Validates the payload:
     *   - join_code: required string.
     * - Looks up the group by join_code (uppercased).
     * - Returns 404 if the join code is invalid.
     * - Checks if the user is already a member:
     *   - if yes, returns HTTP 200 with a message and does not change the database.
     * - Otherwise attaches the user to the group with pivot role = 'member'.
     *
     * Response JSON on success:
     * - HTTP 200
     * - 'group' => array{id:int, name:string, slug:string, join_code:string}
     *
     * Response JSON if already a member:
     * - HTTP 200
     * - { "message": "Already a member of this group." }
     *
     * Error responses:
     * - 401 { "message": "Unauthenticated." }
     * - 404 { "message": "Invalid join code." }
     *
     * @param Request $request HTTP request containing the join code.
     *
     * @return JsonResponse JSON response confirming membership or returning an error.
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
     *
     * This endpoint requires authentication and membership in the target group.
     *
     * Behavior:
     * - If the authenticated user is not the owner:
     *   - the user is detached from the group,
     *   - returns HTTP 200 with "You left the group."
     *
     * - If the authenticated user is the owner:
     *   - attempts to find the next owner as the oldest remaining member
     *     (ordered by pivot created_at ascending),
     *   - if no other member exists:
     *       - the group is deleted,
     *       - returns HTTP 200 with a message indicating deletion.
     *   - otherwise (a next owner exists):
     *       - inside a DB transaction:
     *           - group.owner_id is updated to the next owner's id,
     *           - next owner's pivot role is updated to 'owner',
     *           - current owner is detached from the group,
     *       - returns HTTP 200 and the updated group summary.
     *
     * Error responses:
     * - 401 { "message": "Unauthenticated." }
     * - 404 { "message": "Group not found." }
     * - 403 { "message": "Not a member of this group." }
     *
     * @param string  $slug    Group slug identifier.
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response describing the result of the leave operation.
     *
     * @throws Throwable If the ownership transfer transaction fails or an unexpected error occurs.
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
     * Delete a friend group (owner-only).
     *
     * This endpoint requires authentication.
     *
     * Steps performed:
     * - Loads the group by slug.
     * - Returns 404 if not found.
     * - Checks that the authenticated user is the group owner (owner_id match).
     * - Returns 403 if not the owner.
     * - Deletes the group.
     *
     * Response JSON:
     * - HTTP 200
     * - { "message": "Group deleted." }
     *
     * Error responses:
     * - 401 { "message": "Unauthenticated." }
     * - 404 { "message": "Group not found." }
     * - 403 { "message": "Forbidden." }
     *
     * @param string  $slug    Group slug identifier.
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response confirming deletion or returning an error.
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
     * Retrieve a leaderboard for a specific game restricted to a friend group.
     *
     * This endpoint requires authentication and membership in the group.
     *
     * Steps performed:
     * - Validates that $game is one of: 'kcdle', 'lfldle', 'lecdle'.
     * - Loads the group by slug (404 if not found).
     * - Ensures the authenticated user is a member (403 if not).
     * - Reads pagination parameters:
     *   - per_page (default 50, coerced to 50 if <= 0),
     *   - page (default 1, coerced to 1 if <= 0).
     * - Delegates the computation to UserLeaderboardService::getGroupLeaderboard().
     * - Formats the paginator items into:
     *   - rank (computed from page/per_page and index),
     *   - user, wins, average_guesses, base_score, weight, final_score.
     * - Returns group metadata, data and pagination meta.
     *
     * Response JSON:
     * - 'group' => array{id:int, name:string, slug:string}
     * - 'data'  => array<int, array{
     *     rank:int,
     *     user:array|null,
     *     wins:int,
     *     average_guesses:float|null,
     *     base_score:float,
     *     weight:float,
     *     final_score:float
     * }>
     * - 'meta'  => array{
     *     current_page:int,
     *     last_page:int,
     *     per_page:int,
     *     total:int
     * }
     *
     * Error responses:
     * - 401 { "message": "Unauthenticated." }
     * - 404 { "message": "Unknown game." } or { "message": "Group not found." }
     * - 403 { "message": "Forbidden." }
     *
     * @param string  $slug    Group slug identifier.
     * @param string  $game    Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request used for authentication and pagination parameters.
     *
     * @return JsonResponse JSON response containing the group leaderboard.
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
