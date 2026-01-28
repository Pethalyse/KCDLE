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
    ) {}

    /**
     * Return the Discord authorization URL.
     *
     * Modes:
     * - login: creates or logs into an account linked by discord_id.
     * - link: links the Discord account to the currently authenticated user.
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
        $mode = (string) $request->query('mode', 'login');

        if (! in_array($mode, ['login', 'link'], true)) {
            return response()->json([
                'message' => 'Invalid mode.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();
        if ($mode === 'link' && ! ($user instanceof User)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $state = Str::random(40);
        $cacheKey = $this->stateCacheKey($state);

        Cache::put($cacheKey, [
            'mode' => $mode,
            'user_id' => $user instanceof User ? (int) $user->getAttribute('id') : null,
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
     * For mode=login:
     * - returns { user, token, unlocked_achievements }
     * - creates an account if no discord_id match and the Discord email is not used.
     * - if the Discord email already exists but is not linked, returns 409 (link required).
     *
     * For mode=link:
     * - requires an authenticated user in the cached state
     * - returns { user }
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

        $state = (string) $data['state'];
        $cacheKey = $this->stateCacheKey($state);

        $statePayload = Cache::get($cacheKey);
        if (! is_array($statePayload) || ! isset($statePayload['mode'])) {
            return response()->json([
                'message' => 'Invalid or expired state.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        Cache::forget($cacheKey);

        $mode = (string) ($statePayload['mode'] ?? 'login');
        $linkUserId = $statePayload['user_id'] ?? null;

        $tokenRes = $this->discord->exchangeCode((string) $data['code']);
        if (! $tokenRes['ok']) {
            return response()->json($tokenRes['payload'], (int) ($tokenRes['status'] ?? 400));
        }

        $accessToken = (string) ($tokenRes['payload']['access_token'] ?? '');
        if ($accessToken === '') {
            return response()->json([
                'message' => 'Discord token response missing access_token.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userRes = $this->discord->fetchUser($accessToken);
        if (! $userRes['ok']) {
            return response()->json($userRes['payload'], (int) ($userRes['status'] ?? 400));
        }

        $discord = $userRes['payload'];
        $discordId = (string) ($discord['id'] ?? '');
        $discordEmail = (string) ($discord['email'] ?? '');
        $discordUsername = (string) (($discord['global_name'] ?? '') !== '' ? ($discord['global_name'] ?? '') : ($discord['username'] ?? ''));

        if ($discordId === '') {
            return response()->json([
                'message' => 'Discord profile missing id.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($mode === 'link') {
            if (! is_int($linkUserId) && ! ctype_digit((string) $linkUserId)) {
                return response()->json([
                    'message' => 'Invalid link state.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            /** @var User|null $user */
            $user = User::query()->find((int) $linkUserId);
            if (! $user instanceof User) {
                return response()->json([
                    'message' => 'User not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            $already = User::query()
                ->where('discord_id', $discordId)
                ->where('id', '!=', $user->getAttribute('id'))
                ->exists();

            if ($already) {
                return response()->json([
                    'message' => 'This Discord account is already linked to another user.',
                    'code' => 'discord_already_linked',
                ], Response::HTTP_CONFLICT);
            }

            $user->setAttribute('discord_id', $discordId);
            $user->save();

            $fresh = $user->fresh();
            if (! $fresh instanceof User) {
                return response()->json([
                    'message' => 'Unexpected error.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'user' => $this->formatUser($fresh),
            ], Response::HTTP_OK);
        }

        /** @var User|null $linked */
        $linked = User::query()
            ->where('discord_id', $discordId)
            ->first();

        if ($linked instanceof User) {
            if (! $linked->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'Adresse e-mail non vérifiée.',
                    'code' => 'email_not_verified',
                ], Response::HTTP_FORBIDDEN);
            }

            $linked->tokens()->delete();

            $token = $linked->createToken('kcdle-app')->plainTextToken;
            $unlocked = $this->pendingGuesses->import($linked, $request);

            return response()->json([
                'user' => $this->formatUser($linked),
                'token' => $token,
                'unlocked_achievements' => $unlocked->values(),
            ], Response::HTTP_OK);
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
            return response()->json([
                'message' => 'An account already exists with this email. Please login normally and link Discord from your profile.',
                'code' => 'discord_link_required',
            ], Response::HTTP_CONFLICT);
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
        ]);
        $user->setAttribute('email_verified_at', now());

        $user->tokens()->delete();

        $token = $user->createToken('kcdle-app')->plainTextToken;
        $unlocked = $this->pendingGuesses->import($user, $request);

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
            'unlocked_achievements' => $unlocked->values(),
        ], Response::HTTP_CREATED);
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

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->setAttribute('discord_id', null);
        $user->save();

        $fresh = $user->fresh();
        if (! $fresh instanceof User) {
            return response()->json([
                'message' => 'Unexpected error.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'user' => $this->formatUser($fresh),
        ], Response::HTTP_OK);
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
            'id' => (int) $user->getAttribute('id'),
            'name' => (string) $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'email_verified' => $user->hasVerifiedEmail(),
            'is_admin' => (bool) $user->getAttribute('is_admin'),
            'avatar_url' => (string) $user->getAttribute('avatar_url'),
            'avatar_frame_color' => (string) $user->getAttribute('avatar_frame_color'),
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

        if (! User::query()->where('name', $base)->exists()) {
            return $base;
        }

        for ($i = 0; $i < 60; $i++) {
            $suffix = (string) random_int(10, 99);
            $maxBaseLen = 20 - (1 + strlen($suffix));
            $candidateBase = mb_substr($base, 0, max(1, $maxBaseLen));
            $candidate = $candidateBase . '_' . $suffix;

            if (! User::query()->where('name', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'discord_' . random_int(1000, 9999);
    }
}
