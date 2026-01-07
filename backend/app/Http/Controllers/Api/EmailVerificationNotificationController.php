<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Resend the email verification notification.
     *
     * This endpoint accepts an email address and sends a new verification
     * email if the account exists and is not yet verified.
     *
     * @param Request $request Incoming HTTP request.
     *
     * @return JsonResponse JSON response.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'email' => ['required', 'string', 'email', 'max:255'],
            ],
            [
                'email.required' => 'L’adresse e-mail est obligatoire.',
                'email.email' => 'L’adresse e-mail est invalide.',
                'email.max' => 'L’adresse e-mail ne peut pas dépasser :max caractères.',
            ],
            [
                'email' => 'adresse e-mail',
            ],
        );

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Si un compte existe avec cette adresse, un e-mail a été envoyé.',
            ], Response::HTTP_OK);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Cette adresse e-mail est déjà vérifiée.',
                'code' => 'already_verified',
            ], Response::HTTP_OK);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'E-mail de vérification renvoyé.',
            'code' => 'sent',
        ], Response::HTTP_OK);
    }
}
