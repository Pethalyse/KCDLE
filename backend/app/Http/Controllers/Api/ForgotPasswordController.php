<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handle password reset link requests.
 *
 * This controller exposes a public API endpoint used by the frontend
 * to initiate the "forgot password" flow. The endpoint always returns
 * a generic success response to avoid disclosing whether an email exists.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Send a password reset link to the provided email address.
     *
     * Request payload:
     * - email: string
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
                'email' => ['required', 'string', 'email'],
            ],
            [
                'email.required' => 'L’adresse e-mail est obligatoire.',
                'email.email' => 'L’adresse e-mail est invalide.',
            ],
            [
                'email' => 'adresse e-mail',
            ],
        );

        Password::sendResetLink(['email' => $data['email']]);

        return response()->json([
            'message' => 'Si un compte existe avec cette adresse e-mail, un lien de réinitialisation a été envoyé.',
            'code' => 'reset_link_sent',
        ], Response::HTTP_OK);
    }
}
