<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PendingGuessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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
     * @param PendingGuessService $pendingGuesses Service used to import pending guesses into a user account.
     */
    public function __construct(
        protected PendingGuessService $pendingGuesses,
    ) {}

    /**
     * Register a new user.
     *
     * This endpoint validates the registration payload, creates the account
     * and sends an email verification notification.
     *
     * The newly created user is NOT authenticated immediately: no Sanctum
     * token is issued until the email has been verified.
     *
     * Response JSON payload:
     * - 'user'                        => array Normalized user.
     * - 'requires_email_verification' => bool Indicates that an email was sent and the account is not verified.
     *
     * @param Request $request Incoming HTTP request containing registration data.
     *
     * @return JsonResponse JSON response with created user.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:20', 'unique:users,name'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(10)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
            ],
            [
                'name.required' => 'Le pseudo est obligatoire.',
                'name.string' => 'Le pseudo est invalide.',
                'name.max' => 'Le pseudo ne peut pas dépasser :max caractères.',
                'name.unique' => 'Ce pseudo est déjà utilisé.',
                'email.required' => 'L’adresse e-mail est obligatoire.',
                'email.email' => 'L’adresse e-mail est invalide.',
                'email.max' => 'L’adresse e-mail ne peut pas dépasser :max caractères.',
                'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
                'password.required' => 'Le mot de passe est obligatoire.',
                'password.confirmed' => 'Les mots de passe ne correspondent pas.',
                'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',
                'password.mixed' => 'Le mot de passe doit contenir une minuscule et une majuscule.',
                'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
                'password.symbols' => 'Le mot de passe doit contenir au moins un symbole.',
                'password.uncompromised' => 'Ce mot de passe a déjà fuité. Choisis-en un autre.',
            ],
            [
                'name' => 'pseudo',
                'email' => 'adresse e-mail',
                'password' => 'mot de passe',
            ],
        );

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'user' => $this->formatUser($user),
            'requires_email_verification' => ! $user->hasVerifiedEmail(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Authenticate an existing user and issue a new Sanctum token.
     *
     * If the provided credentials are invalid, a validation error is returned.
     * If the email is not verified, a 403 response is returned.
     *
     * @param Request $request Incoming HTTP request with login credentials.
     *
     * @return JsonResponse JSON response containing the user, token and unlocked achievements.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(
            [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ],
            [
                'email.required' => 'L’adresse e-mail est obligatoire.',
                'email.email' => 'L’adresse e-mail est invalide.',
                'password.required' => 'Le mot de passe est obligatoire.',
            ],
            [
                'email' => 'adresse e-mail',
                'password' => 'mot de passe',
            ],
        );

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], (string) $user->getAttribute('password'))) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
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
        ]);
    }

    /**
     * Revoke the current API token for the authenticated user.
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
     * @param User $user User instance to normalize.
     *
     * @return array{id:int, name:string, email:string|null, email_verified:bool} Normalized user data.
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => (int) $user->getAttribute('id'),
            'name' => (string) $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
}
