<?php

namespace App\Http\Controllers\Api\Discord;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\DiscordOAuthService;
use App\Services\PendingGuessService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discord OAuth controller.
 *
 * Provides:
 * - authorization URL generation (with short-lived state stored in cache)
 * - code exchange for login or link modes
 * - unlink endpoint for authenticated users
 */
class DiscordAuthController extends Controller
{
    /**
     * @param DiscordOAuthService $discord Discord OAuth client.
     * @param PendingGuessService $pendingGuesses Pending guess importer (IP-based).
     */
    public function __construct(
        protected DiscordOAuthService $discord,
        protected PendingGuessService $pendingGuesses
    )
    {
    }

    /**
     * Return the Discord authorization URL.
     *
     * Modes:
     * - login: logs in (or creates) an account from a Discord identity.
     * - link: links the Discord identity to the currently authenticated user, or
     *         if already linked, switches the session to the linked account.
     *
     * Request query:
     * - mode: 'login'|'link'
     *
     * Response:
     * - url: string
     * - state: string
     *
     * @param Request $request Incoming request.
     *
     * @return JsonResponse
     */
    public function url(Request $request): JsonResponse
    {
        $mode = (string)$request->query('mode', 'login');

        if (!in_array($mode, ['login', 'link'], true)) {
            return response()->json([
                'message' => 'Invalid mode.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = null;

        if ($mode === 'link') {
            $user = $this->resolveBearerUser($request);

            if (!($user instanceof User)) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        $state = Str::random(40);
        $cacheKey = $this->stateCacheKey($state);

        Cache::put($cacheKey, [
            'mode' => $mode,
            'user_id' => $user instanceof User ? (int)$user->getAttribute('id') : null,
        ], now()->addMinutes(10));

        return response()->json([
            'url' => $this->discord->buildAuthorizationUrl($state),
            'state' => $state,
        ], Response::HTTP_OK);
    }

    /**
     * Exchange a Discord OAuth code for an application login or link action.
     *
     * Payload:
     * - code: string
     * - state: string
     *
     * Behavior:
     * - login:
     *   - if users.discord_id exists -> login to that user
     *   - else if an account exists with the same email -> link discord_id to it, verify email if needed, then login
     *   - else -> create an account (verified email) and login
     * - link:
     *   - requires a logged-in user_id in state
     *   - if discord_id is already linked to another user -> login to that linked user (switch account)
     *   - else -> link current user to discord_id and return updated user
     *
     * @param Request $request Incoming request.
     *
     * @return JsonResponse
     * @throws RandomException
     * @throws ConnectionException
     */
    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $state = (string)$data['state'];
        $cacheKey = $this->stateCacheKey($state);

        $statePayload = Cache::get($cacheKey);
        if (!is_array($statePayload) || !isset($statePayload['mode'])) {
            return response()->json([
                'message' => 'Invalid or expired state.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        Cache::forget($cacheKey);

        $mode = (string)($statePayload['mode'] ?? 'login');
        $linkUserId = $statePayload['user_id'] ?? null;

        $tokenRes = $this->discord->exchangeCode((string)$data['code']);
        if (!$tokenRes['ok']) {
            return response()->json($tokenRes['payload'], (int)($tokenRes['status'] ?? 400));
        }

        $accessToken = (string)($tokenRes['payload']['access_token'] ?? '');
        if ($accessToken === '') {
            return response()->json([
                'message' => 'Discord token response missing access_token.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userRes = $this->discord->fetchUser($accessToken);
        if (!$userRes['ok']) {
            return response()->json($userRes['payload'], (int)($userRes['status'] ?? 400));
        }

        $discord = $userRes['payload'];
        $discordId = (string)($discord['id'] ?? '');
        $discordEmail = (string)($discord['email'] ?? '');
        $discordUsername = (string)(($discord['global_name'] ?? '') !== '' ? ($discord['global_name'] ?? '') : ($discord['username'] ?? ''));
        $discordAvatarHashRaw = $discord['avatar'] ?? null;
        $discordAvatarHash = is_string($discordAvatarHashRaw) && $discordAvatarHashRaw !== '' ? $discordAvatarHashRaw : null;

        if ($discordId === '') {
            return response()->json([
                'message' => 'Discord profile missing id.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($mode === 'link') {
            if (!is_int($linkUserId) && !ctype_digit((string)$linkUserId)) {
                return response()->json([
                    'message' => 'Invalid link state.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            /** @var User|null $currentUser */
            $currentUser = User::query()->find((int)$linkUserId);
            if (!$currentUser instanceof User) {
                return response()->json([
                    'message' => 'User not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            /** @var User|null $alreadyLinkedUser */
            $alreadyLinkedUser = User::query()->where('discord_id', $discordId)->first();

            if ($alreadyLinkedUser instanceof User && (int)$alreadyLinkedUser->getAttribute('id') !== (int)$currentUser->getAttribute('id')) {
                $this->syncDiscordIdentity($alreadyLinkedUser, $discordId, $discordAvatarHash);

                return $this->respondLogin($alreadyLinkedUser, $request);
            }

            $this->syncDiscordIdentity($currentUser, $discordId, $discordAvatarHash);

            $fresh = $currentUser->fresh();
            if (!$fresh instanceof User) {
                return response()->json([
                    'message' => 'Unexpected error.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'user' => $this->formatUser($fresh),
            ], Response::HTTP_OK);
        }

        /** @var User|null $linked */
        $linked = User::query()->where('discord_id', $discordId)->first();

        if ($linked instanceof User) {
            $this->syncDiscordIdentity($linked, $discordId, $discordAvatarHash);

            return $this->respondLogin($linked, $request);
        }

        if ($discordEmail === '') {
            return response()->json([
                'message' => 'Discord did not provide an email for this user.',
                'code' => 'discord_email_missing',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User|null $existingByEmail */
        $existingByEmail = User::query()->where('email', $discordEmail)->first();

        if ($existingByEmail instanceof User) {
            $existingDiscordId = $existingByEmail->getAttribute('discord_id');

            if ($existingDiscordId && (string)$existingDiscordId !== $discordId) {
                return response()->json([
                    'message' => 'This email is already used by an account linked to another Discord identity.',
                    'code' => 'discord_email_already_used',
                ], Response::HTTP_CONFLICT);
            }

            $this->syncDiscordIdentity($existingByEmail, $discordId, $discordAvatarHash);

            if (!$existingByEmail->hasVerifiedEmail()) {
                $existingByEmail->setAttribute('email_verified_at', now());
                $existingByEmail->save();
            }

            return $this->respondLogin($existingByEmail, $request, Response::HTTP_OK);
        }

        $baseName = trim($discordUsername);
        if ($baseName === '') {
            $baseName = 'discord';
        }

        $name = $this->generateUniqueName($baseName);

        $user = User::query()->create([
            'name' => $name,
            'email' => $discordEmail,
            'password' => Hash::make(Str::random(64)),
            'discord_id' => $discordId,
            'discord_avatar_hash' => $discordAvatarHash,
        ]);
        $user->setAttribute('email_verified_at', now());
        $user->save();

        return $this->respondLogin($user, $request, Response::HTTP_CREATED);
    }

    /**
     * Unlink the authenticated user's Discord account.
     *
     * @param Request $request Incoming request.
     *
     * @return JsonResponse
     */
    public function unlink(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->setAttribute('discord_id', null);
        $user->setAttribute('discord_avatar_hash', null);
        $user->save();

        $fresh = $user->fresh();
        if (!$fresh instanceof User) {
            return response()->json([
                'message' => 'Unexpected error.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'user' => $this->formatUser($fresh),
        ], Response::HTTP_OK);
    }

    /**
     * Resolve the authenticated user from the bearer token, without relying on
     * middleware groups or the default guard.
     *
     * @param Request $request HTTP request possibly carrying a bearer token.
     *
     * @return User|null
     */
    protected function resolveBearerUser(Request $request): ?User
    {
        $bearer = $request->bearerToken() ?? '';
        if ($bearer === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);
        if (!$token) {
            return null;
        }

        $tokenable = $token->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }

    /**
     * Synchronize the Discord identity data on a user.
     *
     * @param User $user Target user.
     * @param string $discordId Discord user id.
     * @param string|null $discordAvatarHash Discord avatar hash.
     *
     * @return void
     */
    protected function syncDiscordIdentity(User $user, string $discordId, ?string $discordAvatarHash): void
    {
        $user->setAttribute('discord_id', $discordId);
        $user->setAttribute('discord_avatar_hash', $discordAvatarHash);

        if ($user->isDirty(['discord_id', 'discord_avatar_hash'])) {
            $user->save();
        }
    }

    /**
     * Create a fresh token for a user and return a consistent login payload.
     *
     * @param User $user User to authenticate.
     * @param Request $request HTTP request for pending guesses import.
     * @param int $status HTTP response status code.
     *
     * @return JsonResponse
     */
    protected function respondLogin(User $user, Request $request, int $status = Response::HTTP_OK): JsonResponse
    {
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Adresse e-mail non vérifiée.',
                'code' => 'email_not_verified',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->tokens()->delete();

        $token = $user->createToken('kcdle-app')->plainTextToken;
        $unlocked = $this->pendingGuesses->import($user, $request);

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
            'unlocked_achievements' => $unlocked->values(),
        ], $status);
    }

    /**
     * Normalize a User model into a public API-safe array.
     *
     * @param User $user User instance to normalize.
     *
     * @return array{id:int, name:string, email:string|null, email_verified:bool, is_admin:bool, avatar_url:string, avatar_frame_color:string, discord_id:string|null}
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => (int)$user->getAttribute('id'),
            'name' => (string)$user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'email_verified' => $user->hasVerifiedEmail(),
            'is_admin' => (bool)$user->getAttribute('is_admin'),
            'avatar_url' => (string)$user->getAttribute('avatar_url'),
            'avatar_frame_color' => (string)$user->getAttribute('avatar_frame_color'),
            'discord_id' => $user->getAttribute('discord_id'),
        ];
    }

    /**
     * Build the cache key for an OAuth state token.
     *
     * @param string $state State token.
     *
     * @return string
     */
    protected function stateCacheKey(string $state): string
    {
        return 'discord_oauth_state:' . $state;
    }

    /**
     * Generate a unique username based on a Discord display name.
     *
     * @param string $base Proposed base name.
     *
     * @return string
     * @throws RandomException
     */
    protected function generateUniqueName(string $base): string
    {
        $base = trim(preg_replace('/\\s+/', ' ', $base) ?? '');
        if ($base === '') {
            $base = 'discord';
        }

        $base = mb_substr($base, 0, 20);

        if (!User::query()->where('name', $base)->exists()) {
            return $base;
        }

        for ($i = 0; $i < 60; $i++) {
            $suffix = (string)random_int(10, 99);
            $maxBaseLen = 20 - (1 + strlen($suffix));
            $candidateBase = mb_substr($base, 0, max(1, $maxBaseLen));
            $candidate = $candidateBase . '_' . $suffix;

            if (!User::query()->where('name', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'discord_' . random_int(1000, 9999);
    }
}
