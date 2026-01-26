<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the request is coming from the Discord bot by validating a shared secret.
 *
 * The bot must send the secret in the `X-Discord-Bot-Secret` header.
 */
class EnsureDiscordBotSecret
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request Incoming request.
     * @param Closure(Request): mixed $next Next middleware.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = (string) config('services.discord.bot_secret', '');
        $provided = (string) $request->header('X-Discord-Bot-Secret', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
