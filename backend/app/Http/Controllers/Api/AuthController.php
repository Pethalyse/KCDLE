<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Register a new user and log them in.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $this->formatUser($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Log in an existing user with email and password.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, true)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Log out the current authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the current authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Format user data for API responses.
     *
     * @param User $user
     * @return array<string, mixed>
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->getAttribute('id'),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
        ];
    }
}
