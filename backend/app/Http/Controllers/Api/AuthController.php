<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\AchievementService;
use App\Services\PendingGuessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * This controller is responsible for user authentication flows:
     * registration, login, logout and retrieving the authenticated user's
     * profile. It also imports pending anonymous guesses (stored under an
     * IP-based anonymous key) into the newly authenticated account and
     * triggers achievement unlocking when appropriate.
     *
     * @param PendingGuessService $pendingGuesses Service used to import pending
     *                                            guesses into a user account.
     */
    public function __construct(
        protected PendingGuessService  $pendingGuesses,
    ) {}

    /**
     * Register a new user and issue a Sanctum token.
     *
     * This endpoint:
     * - validates the registration payload (name, unique email, password),
     * - creates a new User with a hashed password,
     * - revokes any existing tokens for safety (should not exist for new users,
     *   but keeps behavior consistent with login),
     * - creates a new personal access token using Sanctum,
     * - imports any pending anonymous guesses for the caller's IP, linking them
     *   to the new account and unlocking achievements if conditions are met,
     * - returns the created user in a normalized shape and the API token.
     *
     * Validation rules:
     * - name: required, string, max 20 characters.
     * - email: required, valid email, max 255 characters, unique in users.
     * - password: required, string, minimum 8 characters.
     *
     * Response JSON payload:
     * - 'user'         => array{ id:int, name:string, email:string|null }
     * - 'token'        => string  Newly issued API token.
     * - 'achievements' => array[] List of unlocked achievements from imported guesses.
     *
     * @param Request $request Incoming HTTP request containing registration data.
     *
     * @return JsonResponse JSON response with created user, token and unlocked achievements.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:20'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('kcdle-app')->plainTextToken;
        $unlocked = $this->pendingGuesses->import($user, $request);

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
            'unlocked_achievements' => $unlocked->values(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Authenticate an existing user and issue a new Sanctum token.
     *
     * This endpoint:
     * - validates the login credentials (email, password),
     * - locates the user by email,
     * - verifies the provided password against the stored password hash,
     * - returns a validation error if credentials are invalid,
     * - revokes any existing tokens for the user,
     * - creates a new Sanctum personal access token,
     * - imports any pending anonymous guesses associated with the caller's IP
     *   into the account and evaluates achievements on those imported wins,
     * - returns the authenticated user, the new token and unlocked achievements.
     *
     * Validation rules:
     * - email: required, valid email.
     * - password: required, string.
     *
     * On invalid credentials, the response is:
     * - HTTP 422 Unprocessable Entity
     * - JSON: { "message": "Identifiants invalides." }
     *
     * On success, Response JSON payload:
     * - 'user'         => array{ id:int, name:string, email:string|null }
     * - 'token'        => string
     * - 'achievements' => array[] Newly unlocked achievements from imported guesses.
     *
     * @param Request $request Incoming HTTP request with login credentials.
     *
     * @return JsonResponse JSON response containing the user, token and achievements.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->getAttribute('password'))) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->tokens()->delete();

        $token = $user->createToken('kcdle-app')->plainTextToken;

        $unlocked = $this->pendingGuesses->import($user, $request);

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
            'unlocked_achievements' => $unlocked->values(),
        ]);
    }

    /**
     * Revoke all API tokens for the authenticated user.
     *
     * This endpoint deletes every Sanctum token associated with the currently
     * authenticated user, effectively logging them out from all devices
     * and clients.
     *
     * If no user is authenticated, it still returns a success response.
     *
     * Response JSON payload:
     * - 'message' => string  Confirmation message.
     *
     * @param Request $request HTTP request used to resolve the authenticated user.
     *
     * @return JsonResponse JSON response confirming logout completion.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Return the profile of the currently authenticated user.
     *
     * This endpoint:
     * - retrieves the user attached to the incoming request,
     * - returns a normalized representation of the user, or null if no user
     *   is authenticated (depending on route middleware configuration).
     *
     * Response JSON payload:
     * - 'user' => array{ id:int, name:string, email:string|null }
     *
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response containing the current user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? $this->formatUser($user) : null,
        ]);
    }

    /**
     * Normalize a User model into a public API-safe array.
     *
     * The normalized representation exposes only the fields needed by the
     * frontend: identifier, display name and email address. Sensitive fields
     * (password hash, tokens, timestamps, etc.) are intentionally omitted.
     *
     * Returned array structure:
     * - 'id'    => int          User primary key.
     * - 'name'  => string       User display name.
     * - 'email' => string|null  User email address if set.
     *
     * @param User $user User instance to normalize.
     *
     * @return array{id:int, name:string, email:string|null} Normalized user data.
     */
    protected function formatUser(User $user): array
    {
        return [
            'id'    => $user->getAttribute("id"),
            'name'  => $user->getAttribute("name"),
            'email' => $user->getAttribute("email"),
        ];
    }
}
