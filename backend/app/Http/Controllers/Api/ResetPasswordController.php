<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handle password resets.
 *
 * This controller completes the password reset flow initiated by the
 * "forgot password" endpoint by validating the reset token and updating
 * the user's password.
 */
class ResetPasswordController extends Controller
{
    /**
     * Reset the user's password using a valid reset token.
     *
     * Request payload:
     * - token: string
     * - email: string
     * - password: string
     * - password_confirmation: string
     *
     * Response payload:
     * - message: string
     * - code: string
     *
     * @param Request $request Incoming HTTP request.
     *
     * @return JsonResponse JSON response.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'token' => ['required', 'string'],
                'email' => ['required', 'string', 'email'],
                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    PasswordRule::min(10)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
            ],
            [
                'token.required' => 'Le token est obligatoire.',
                'email.required' => 'L’adresse e-mail est obligatoire.',
                'email.email' => 'L’adresse e-mail est invalide.',
                'password.required' => 'Le mot de passe est obligatoire.',
                'password.confirmed' => 'Les mots de passe ne correspondent pas.',
                'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',
                'password.mixed' => 'Le mot de passe doit contenir une minuscule et une majuscule.',
                'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
                'password.symbols' => 'Le mot de passe doit contenir au moins un symbole.',
                'password.uncompromised' => 'Ce mot de passe a déjà fuité. Choisis-en un autre.',
            ],
            [
                'token' => 'token',
                'email' => 'adresse e-mail',
                'password' => 'mot de passe',
            ],
        );

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? null,
                'token' => $data['token'],
            ],
            function ($user) use ($data): void {
                $user->forceFill([
                    'password' => Hash::make($data['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Ce lien de réinitialisation est invalide ou a expiré.'],
            ]);
        }

        return response()->json([
            'message' => 'Mot de passe réinitialisé. Tu peux maintenant te connecter.',
            'code' => 'password_reset',
        ], Response::HTTP_OK);
    }
}
